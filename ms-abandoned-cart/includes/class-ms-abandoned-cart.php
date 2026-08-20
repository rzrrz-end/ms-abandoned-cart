<?php
/**
 * Основной класс плагина MS Abandoned Cart.
 *
 * Отвечает за cron-проверку брошенных корзин, подстановку промокодов
 * из настроек, отправку email и отслеживание активности корзины.
 *
 * @package MS_Abandoned_Cart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Класс MS_Abandoned_Cart.
 */
class MS_Abandoned_Cart {

	/**
	 * Ключ мета-поля последней активности корзины.
	 *
	 * @var string
	 */
	const META_LAST_ACTIVITY = '_ms_cart_last_activity';

	/**
	 * Ключ мета-поля: уведомление уже отправлено.
	 *
	 * @var string
	 */
	const META_NOTIFIED = '_ms_abandoned_cart_notified';

	/**
	 * Конструктор: регистрация хуков cron и активности корзины.
	 */
	public function __construct() {
		add_action( 'ms_abandoned_cart_daily_check', array( $this, 'run_daily_check' ) );
		add_action( 'woocommerce_cart_updated', array( $this, 'on_cart_updated' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 10, 1 );
	}

	/**
	 * Ежедневная проверка брошенных корзин.
	 */
	public function run_daily_check() {
		$settings = $this->get_settings();

		if ( empty( $settings['enabled'] ) || 'yes' !== $settings['enabled'] ) {
			$this->log( 'Проверка пропущена: уведомления отключены в настройках.', true );
			return;
		}

		$this->log( 'Запуск ежедневной проверки брошенных корзин.', true );

		$cart_meta_key = $this->get_persistent_cart_meta_key();
		$delay_hours   = max( 1, (int) $this->get_setting( 'delay_hours', 24 ) );
		$delay_seconds = $delay_hours * HOUR_IN_SECONDS;
		$now           = time();

		$users = get_users(
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => $cart_meta_key,
						'compare' => 'EXISTS',
					),
				),
				'fields'     => array( 'ID', 'user_email' ),
				'number'     => -1,
			)
		);

		if ( empty( $users ) ) {
			$this->log( 'Пользователи с persistent cart не найдены.', true );
			return;
		}

		$this->log( sprintf( 'Найдено пользователей с persistent cart: %d.', count( $users ) ), true );

		foreach ( $users as $user ) {
			$user_id    = (int) $user->ID;
			$user_email = $user->user_email;

			try {
				$this->process_user_cart( $user_id, $user_email, $cart_meta_key, $delay_hours, $delay_seconds, $now, $settings );
			} catch ( Exception $e ) {
				$this->log(
					sprintf(
						'Пользователь ID=%d — ошибка отправки письма: %s',
						$user_id,
						$e->getMessage()
					),
					true
				);
			}
		}

		$this->log( 'Ежедневная проверка завершена.', true );
	}

	/**
	 * Обработка корзины одного пользователя.
	 *
	 * @param int    $user_id       ID пользователя.
	 * @param string $user_email    Email пользователя.
	 * @param string $cart_meta_key Ключ мета persistent cart.
	 * @param int    $delay_hours   Задержка в часах (для лога).
	 * @param int    $delay_seconds Задержка в секундах.
	 * @param int    $now           Текущий timestamp.
	 * @param array  $settings      Настройки плагина.
	 */
	private function process_user_cart( $user_id, $user_email, $cart_meta_key, $delay_hours, $delay_seconds, $now, $settings ) {
		$this->log( sprintf( 'Проверка пользователя ID=%d, email=%s', $user_id, $user_email ), true );

		$persistent_cart = get_user_meta( $user_id, $cart_meta_key, true );

		if ( empty( $persistent_cart ) || ! is_array( $persistent_cart ) ) {
			$this->log( sprintf( 'Пользователь ID=%d — корзина пуста, пропуск', $user_id ) );
			return;
		}

		$cart_contents = isset( $persistent_cart['cart'] ) ? $persistent_cart['cart'] : array();

		if ( empty( $cart_contents ) || ! is_array( $cart_contents ) ) {
			$this->log( sprintf( 'Пользователь ID=%d — корзина пуста, пропуск', $user_id ) );
			return;
		}

		$last_activity     = (int) get_user_meta( $user_id, self::META_LAST_ACTIVITY, true );
		$activity_fallback = false;

		// Нет метки, но корзина не пуста — считаем активность 30 дней назад, чтобы письмо ушло.
		if ( $last_activity <= 0 ) {
			$last_activity     = $now - ( 30 * DAY_IN_SECONDS );
			$activity_fallback = true;
			update_user_meta( $user_id, self::META_LAST_ACTIVITY, $last_activity );
		}

		$diff_seconds = $now - $last_activity;
		$diff_hours   = round( $diff_seconds / HOUR_IN_SECONDS, 2 );

		$activity_label = $activity_fallback
			? 'нет данных (установлено 30 дней назад)'
			: date( 'Y-m-d H:i:s', $last_activity );

		$this->log(
			sprintf(
				'Пользователь ID=%d — последняя активность: %s, задержка: %d часов, разница: %s часов',
				$user_id,
				$activity_label,
				$delay_hours,
				$diff_hours
			)
		);

		if ( $diff_seconds < $delay_seconds ) {
			$this->log( sprintf( 'Пользователь ID=%d — задержка не истекла, пропуск', $user_id ) );
			return;
		}

		// Уведомление уже отправлялось для этой корзины.
		$notified = get_user_meta( $user_id, self::META_NOTIFIED, true );
		if ( ! empty( $notified ) ) {
			$notified_label = $this->format_notified_label( $notified );
			$this->log(
				sprintf(
					'Пользователь ID=%d — уже отправлялось (notified: %s), пропуск',
					$user_id,
					$notified_label
				)
			);
			return;
		}

		$has_orders      = $this->has_previous_orders( $user_id );
		$is_new_customer = ! $has_orders;

		if ( $is_new_customer ) {
			$this->log( sprintf( 'Пользователь ID=%d — это новый клиент (нет заказов)', $user_id ) );
			$coupon_code = isset( $settings['new_customer_coupon'] ) ? $settings['new_customer_coupon'] : '';
		} else {
			$this->log( sprintf( 'Пользователь ID=%d — постоянный клиент (есть заказы)', $user_id ) );
			$coupon_code = isset( $settings['returning_customer_coupon'] ) ? $settings['returning_customer_coupon'] : '';
		}

		$coupon_code = is_string( $coupon_code ) ? trim( $coupon_code ) : '';

		if ( '' !== $coupon_code ) {
			$this->log( sprintf( 'Пользователь ID=%d — указан купон: %s', $user_id, $coupon_code ) );
		} else {
			$this->log( sprintf( 'Пользователь ID=%d — купон не указан, письмо без купона', $user_id ) );
		}

		// Фиксируем метку до отправки — защита от дублей при повторном cron.
		update_user_meta( $user_id, self::META_NOTIFIED, $now );

		$cart_link  = wc_get_cart_url();
		$cart_items = $this->prepare_cart_items_for_email( $cart_contents );
		$sent       = $this->send_email( $user_email, $cart_link, $coupon_code, $is_new_customer, $cart_items );

		if ( $sent ) {
			$this->log( sprintf( 'Пользователь ID=%d — письмо отправлено успешно', $user_id ), true );
			return;
		}

		// Письмо не ушло — снимаем метку, чтобы повторить при следующем cron.
		delete_user_meta( $user_id, self::META_NOTIFIED );
		$this->log( sprintf( 'Пользователь ID=%d — ошибка отправки письма: wp_mail вернул false', $user_id ), true );
	}

	/**
	 * Форматирование метки notified для лога.
	 *
	 * @param mixed $notified Значение мета-поля.
	 * @return string
	 */
	private function format_notified_label( $notified ) {
		if ( is_array( $notified ) && isset( $notified['time'] ) ) {
			return date( 'Y-m-d H:i:s', (int) $notified['time'] );
		}

		if ( is_numeric( $notified ) ) {
			return date( 'Y-m-d H:i:s', (int) $notified );
		}

		return (string) $notified;
	}

	/**
	 * Проверка наличия завершённых/оплаченных заказов (HPOS-совместимо).
	 *
	 * @param int $user_id ID пользователя.
	 * @return bool
	 */
	public function has_previous_orders( $user_id ) {
		$orders = wc_get_orders(
			array(
				'customer_id' => (int) $user_id,
				'status'      => array( 'processing', 'completed' ),
				'limit'       => 1,
				'return'      => 'ids',
			)
		);

		return ! empty( $orders );
	}

	/**
	 * Подготовка товаров корзины для HTML-письма.
	 *
	 * @param array $cart_contents Содержимое persistent cart.
	 * @return array
	 */
	private function prepare_cart_items_for_email( $cart_contents ) {
		$items = array();

		foreach ( $cart_contents as $cart_item ) {
			if ( empty( $cart_item['product_id'] ) ) {
				continue;
			}

			$product_id   = ! empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : (int) $cart_item['product_id'];
			$product      = wc_get_product( $product_id );
			$parent_id    = (int) $cart_item['product_id'];
			$parent       = $product && $product->is_type( 'variation' ) ? wc_get_product( $parent_id ) : $product;

			if ( ! $product ) {
				continue;
			}

			$quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
			$line     = isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] : ( (float) $product->get_price() * $quantity );

			$image_id  = $product->get_image_id();
			if ( ! $image_id && $parent ) {
				$image_id = $parent->get_image_id();
			}

			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

			$items[] = array(
				'name'        => $product->get_name(),
				'quantity'    => max( 1, $quantity ),
				'price_html'  => wc_price( $line ),
				'image_url'   => $image_url ? $image_url : '',
				'product_url' => $product->get_permalink(),
			);
		}

		return $items;
	}

	/**
	 * Рендер HTML-шаблона письма.
	 *
	 * @param string $template_file Имя файла в templates/emails/.
	 * @param array  $vars          Переменные для шаблона.
	 * @return string
	 */
	private function render_email_template( $template_file, $vars ) {
		$path = MS_ABANDONED_CART_PATH . 'templates/emails/' . $template_file;

		if ( ! file_exists( $path ) ) {
			$this->log( 'Шаблон письма не найден: ' . $template_file, true );
			return '';
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- переменные для шаблона письма.
		extract( $vars, EXTR_SKIP );

		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Отправка HTML-письма в фирменном стиле Veresk.
	 *
	 * Не задаёт From / From-Name — совместимо с WP Mail SMTP.
	 *
	 * @param string $user_email       Email получателя.
	 * @param string $cart_link        Ссылка на корзину.
	 * @param string $coupon_code      Код купона (может быть пустым).
	 * @param bool   $is_new_customer  Новый клиент или постоянный.
	 * @param array  $cart_items       Товары для таблицы в письме.
	 * @return bool
	 */
	public function send_email( $user_email, $cart_link, $coupon_code, $is_new_customer, $cart_items ) {
		$site_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$brand_green = '#197B4F'; // фирменный зелёный vereskflowers.ru
		$logo_path   = MS_ABANDONED_CART_PATH . 'assets/logo-veresk.png';
		$logo_url    = file_exists( $logo_path ) ? MS_ABANDONED_CART_URL . 'assets/logo-veresk.png' : '';

		if ( $is_new_customer ) {
			$subject  = $this->get_setting( 'email_subject_new', '-15% на букет в корзине' );
			$heading  = $this->get_setting( 'email_heading_new', 'Ваш букет ждёт в корзине' );
			$template = 'abandoned-cart-new-customer.php';
		} else {
			$subject  = $this->get_setting( 'email_subject_returning', 'Ваш букет ждёт вас — воспользуйтесь бонусами и скидкой 5%' );
			$heading  = $this->get_setting( 'email_heading_returning', 'Ваш букет ждёт в корзине' );
			$template = 'abandoned-cart-returning-customer.php';
		}

		$message_body = $this->render_email_template(
			$template,
			array(
				'coupon_code' => (string) $coupon_code,
				'cart_link'   => $cart_link,
				'cart_items'  => $cart_items,
				'site_name'   => $site_name,
				'brand_green' => $brand_green,
			)
		);

		if ( '' === $message_body ) {
			return false;
		}

		// Собственная белая обёртка с логотипом (без фиолетового WC-header).
		$message = $this->render_email_template(
			'email-wrapper.php',
			array(
				'email_heading' => $heading,
				'email_content' => $message_body,
				'logo_url'      => $logo_url,
				'site_name'     => $site_name,
				'brand_green'   => $brand_green,
			)
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$mailer  = WC()->mailer();

		$this->log(
			sprintf(
				'Перед wp_mail: email=%s, subject=%s, coupon=%s, scenario=%s, items=%d',
				$user_email,
				$subject,
				'' !== (string) $coupon_code ? $coupon_code : '(нет)',
				$is_new_customer ? 'new' : 'returning',
				count( $cart_items )
			),
			true
		);

		try {
			$sent = (bool) $mailer->send( $user_email, $subject, $message, $headers );
		} catch ( Exception $e ) {
			$this->log(
				sprintf(
					'После wp_mail: email=%s, результат=ошибка, ошибка=%s',
					$user_email,
					$e->getMessage()
				),
				true
			);
			throw $e;
		}

		$this->log(
			sprintf(
				'После wp_mail: email=%s, результат=%s',
				$user_email,
				$sent ? 'успех' : 'false'
			),
			true
		);

		return $sent;
	}

	/**
	 * Обновление метки активности при изменении корзины.
	 * Сбрасывает флаг уведомления, чтобы можно было уведомить снова.
	 */
	public function on_cart_updated() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		update_user_meta( $user_id, self::META_LAST_ACTIVITY, time() );
		delete_user_meta( $user_id, self::META_NOTIFIED );
	}

	/**
	 * Сброс меток после оформления заказа.
	 *
	 * @param int $order_id ID заказа.
	 */
	public function on_order_processed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		delete_user_meta( $user_id, self::META_NOTIFIED );
		delete_user_meta( $user_id, self::META_LAST_ACTIVITY );
	}

	/**
	 * Ключ мета-поля persistent cart WooCommerce.
	 *
	 * @return string
	 */
	private function get_persistent_cart_meta_key() {
		return '_woocommerce_persistent_cart_' . get_current_blog_id();
	}

	/**
	 * Получение всех настроек плагина (одна опция-массив).
	 *
	 * @return array
	 */
	public function get_settings() {
		return MS_Abandoned_Cart_Settings::get_all_settings();
	}

	/**
	 * Получение одного значения настройки.
	 *
	 * @param string $key     Ключ настройки.
	 * @param mixed  $default Значение по умолчанию.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return MS_Abandoned_Cart_Settings::get_setting( $key, $default );
	}

	/**
	 * Запись сообщения в лог-файл и (для критичных сообщений) в error_log.
	 *
	 * Папка uploads/ms-abandoned-cart/ создаётся заново, если была удалена.
	 * Если запись в файл невозможна — сообщение уходит в error_log с префиксом.
	 *
	 * @param string $message         Текст сообщения.
	 * @param bool   $also_error_log  Дублировать в error_log / debug.log.
	 */
	public function log( $message, $also_error_log = false ) {
		$prefixed = 'MS Abandoned Cart: ' . $message;

		// Критичные сообщения всегда дублируем в стандартный лог WP (debug.log при WP_DEBUG_LOG).
		if ( $also_error_log ) {
			error_log( $prefixed ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			error_log( $prefixed . ' | Не удалось получить uploads: ' . $upload_dir['error'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}

		$log_dir = trailingslashit( $upload_dir['basedir'] ) . 'ms-abandoned-cart';

		// Всегда проверяем и пересоздаём папку, если её удалили.
		if ( ! file_exists( $log_dir ) ) {
			$created = wp_mkdir_p( $log_dir );
			if ( ! $created && ! file_exists( $log_dir ) ) {
				error_log( $prefixed . ' | Не удалось создать папку логов: ' . $log_dir ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				if ( ! $also_error_log ) {
					error_log( $prefixed ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return;
			}

			// Защита директории от прямого доступа через браузер.
			$htaccess = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				@file_put_contents( $htaccess, "Deny from all\n" );
			}

			$index = $log_dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			}
		}

		$log_file = $log_dir . '/abandoned-cart.log';
		$line     = sprintf( "[%s] %s\n", date( 'Y-m-d H:i:s' ), $message );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		$written = @file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );

		if ( false === $written ) {
			error_log( $prefixed . ' | Не удалось записать в файл: ' . $log_file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			if ( ! $also_error_log ) {
				error_log( $prefixed ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
}
