<?php
/**
 * Страница настроек плагина MS Abandoned Cart.
 *
 * Добавляет вкладку в WooCommerce → Настройки.
 * Все настройки хранятся в одной опции ms_abandoned_cart_settings.
 *
 * @package MS_Abandoned_Cart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Класс MS_Abandoned_Cart_Settings.
 */
class MS_Abandoned_Cart_Settings {

	/**
	 * ID вкладки настроек.
	 *
	 * @var string
	 */
	const SECTION_ID = 'ms_abandoned_cart';

	/**
	 * Ключ единой опции настроек.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'ms_abandoned_cart_settings';

	/**
	 * Старые отдельные опции (для миграции с 1.0.0).
	 *
	 * @var array
	 */
	const LEGACY_OPTION_KEYS = array(
		'enabled'     => 'ms_abandoned_cart_enabled',
		'delay_hours' => 'ms_abandoned_cart_delay_hours',
	);

	/**
	 * Конструктор: регистрация хуков настроек WooCommerce.
	 */
	public function __construct() {
		self::maybe_migrate_legacy_options();

		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_' . self::SECTION_ID, array( $this, 'render_settings_tab' ) );
		add_action( 'woocommerce_update_options_' . self::SECTION_ID, array( $this, 'save_settings' ) );
		add_action( 'admin_post_ms_abandoned_cart_run_check', array( $this, 'handle_manual_run' ) );
		add_action( 'admin_post_ms_abandoned_cart_reset_notified', array( $this, 'handle_reset_notified' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_run_notice' ) );
	}

	/**
	 * Значения настроек по умолчанию.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'                   => 'yes',
			'delay_hours'               => 24,
			'new_customer_coupon'       => 'WELCOME15',
			'returning_customer_coupon' => 'FLOWER5',
			'email_subject_new'         => '-15% на букет в корзине',
			'email_heading_new'         => 'Ваш букет ждёт в корзине',
			'email_subject_returning'   => 'Ваш букет ждёт вас — воспользуйтесь бонусами и скидкой 5%',
			'email_heading_returning'   => 'Ваш букет ждёт в корзине',
		);
	}

	/**
	 * Получение всех настроек из одной опции.
	 *
	 * @return array
	 */
	public static function get_all_settings() {
		self::maybe_migrate_legacy_options();

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Получение одного значения настройки.
	 *
	 * @param string $key     Ключ настройки.
	 * @param mixed  $default Значение по умолчанию (если null — из defaults).
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = self::get_all_settings();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		if ( null !== $default ) {
			return $default;
		}

		$defaults = self::get_defaults();
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
	}

	/**
	 * Миграция старых отдельных опций в единый массив (один раз).
	 */
	public static function maybe_migrate_legacy_options() {
		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return;
		}

		$defaults = self::get_defaults();
		$migrated = $defaults;
		$found    = false;

		foreach ( self::LEGACY_OPTION_KEYS as $key => $legacy_option ) {
			$legacy_value = get_option( $legacy_option, null );
			if ( null !== $legacy_value && false !== $legacy_value ) {
				$migrated[ $key ] = $legacy_value;
				$found            = true;
			}
		}

		if ( ! $found ) {
			update_option( self::OPTION_KEY, $defaults );
			return;
		}

		update_option( self::OPTION_KEY, $migrated );

		foreach ( self::LEGACY_OPTION_KEYS as $legacy_option ) {
			delete_option( $legacy_option );
		}
	}

	/**
	 * Добавление вкладки «Брошенные корзины» в настройки WooCommerce.
	 *
	 * @param array $tabs Существующие вкладки.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs[ self::SECTION_ID ] = __( 'Брошенные корзины', 'ms-abandoned-cart' );
		return $tabs;
	}

	/**
	 * Вывод полей настроек + блок ручного запуска cron.
	 */
	public function render_settings_tab() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		woocommerce_admin_fields( $this->get_settings_fields() );
		$this->render_manual_run_box();
	}

	/**
	 * Блок: статус cron и кнопка «Запустить проверку сейчас».
	 */
	private function render_manual_run_box() {
		$next = wp_next_scheduled( 'ms_abandoned_cart_daily_check' );

		if ( ! $next ) {
			// Перепланируем, если событие пропало (часто после деплоя без реактивации).
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'ms_abandoned_cart_daily_check' );
			$next = wp_next_scheduled( 'ms_abandoned_cart_daily_check' );
		}

		$next_label = $next
			? date_i18n( 'd.m.Y H:i', $next + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) )
			: __( 'не запланирован', 'ms-abandoned-cart' );

		$run_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=ms_abandoned_cart_run_check' ),
			'ms_abandoned_cart_run_check'
		);
		$reset_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=ms_abandoned_cart_reset_notified' ),
			'ms_abandoned_cart_reset_notified'
		);
		?>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<?php esc_html_e( 'Проверка сейчас', 'ms-abandoned-cart' ); ?>
					</th>
					<td class="forminp">
						<p>
							<?php
							printf(
								/* translators: %s: next cron datetime */
								esc_html__( 'Следующий автоматический запуск WP-Cron: %s', 'ms-abandoned-cart' ),
								esc_html( $next_label )
							);
							?>
						</p>
						<p>
							<a href="<?php echo esc_url( $run_url ); ?>" class="button button-primary">
								<?php esc_html_e( 'Запустить проверку сейчас', 'ms-abandoned-cart' ); ?>
							</a>
							<a href="<?php echo esc_url( $reset_url ); ?>" class="button" onclick="return confirm('Сбросить метки notified у всех пользователей и отодвинуть активность назад? Письма уйдут снова при следующем запуске.');">
								<?php esc_html_e( 'Сбросить метки (для теста)', 'ms-abandoned-cart' ); ?>
							</a>
						</p>
						<p class="description">
							<?php esc_html_e( 'После успешной отправки ставится метка «уже отправлялось» — повторно письмо не уйдёт, пока пользователь не изменит корзину. Кнопка сброса нужна только для теста.', 'ms-abandoned-cart' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Ручной запуск проверки (admin-post).
	 */
	public function handle_manual_run() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'ms-abandoned-cart' ) );
		}

		check_admin_referer( 'ms_abandoned_cart_run_check' );

		$plugin = new MS_Abandoned_Cart();
		$plugin->log( 'Ручной запуск проверки из админки.', true );
		$plugin->run_daily_check();

		$redirect = add_query_arg(
			array(
				'page'                  => 'wc-settings',
				'tab'                   => self::SECTION_ID,
				'ms_abandoned_cart_ran' => '1',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Сброс меток notified + активность «давно» — чтобы можно было протестировать повторно.
	 */
	public function handle_reset_notified() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'ms-abandoned-cart' ) );
		}

		check_admin_referer( 'ms_abandoned_cart_reset_notified' );

		$users = get_users(
			array(
				'meta_key' => MS_Abandoned_Cart::META_NOTIFIED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'   => 'ID',
				'number'   => -1,
			)
		);

		$delay_hours = max( 1, (int) self::get_setting( 'delay_hours', 24 ) );
		$old_time    = time() - ( ( $delay_hours + 1 ) * HOUR_IN_SECONDS );
		$count       = 0;

		foreach ( $users as $user_id ) {
			delete_user_meta( (int) $user_id, MS_Abandoned_Cart::META_NOTIFIED );
			update_user_meta( (int) $user_id, MS_Abandoned_Cart::META_LAST_ACTIVITY, $old_time );
			$count++;
		}

		$plugin = new MS_Abandoned_Cart();
		$plugin->log( sprintf( 'Сброшены метки notified у %d пользователей (тест).', $count ), true );

		$redirect = add_query_arg(
			array(
				'page'                    => 'wc-settings',
				'tab'                     => self::SECTION_ID,
				'ms_abandoned_cart_reset' => '1',
				'ms_abandoned_cart_count' => $count,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Уведомление после ручного запуска / сброса.
	 */
	public function maybe_show_run_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		if ( self::SECTION_ID !== $tab ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['ms_abandoned_cart_ran'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Проверка брошенных корзин выполнена. Смотрите лог: wp-content/uploads/ms-abandoned-cart/abandoned-cart.log', 'ms-abandoned-cart' );
			echo '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['ms_abandoned_cart_reset'] ) ) {
			$count = isset( $_GET['ms_abandoned_cart_count'] ) ? absint( $_GET['ms_abandoned_cart_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				/* translators: %d: number of users */
				esc_html__( 'Метки сброшены у %d пользователей. Теперь нажмите «Запустить проверку сейчас».', 'ms-abandoned-cart' ),
				$count
			);
			echo '</p></div>';
		}
	}

	/**
	 * Сохранение настроек в одну опцию-массив.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверяет WooCommerce до этого хука.
		$raw = isset( $_POST['ms_abandoned_cart_settings'] ) ? wp_unslash( $_POST['ms_abandoned_cart_settings'] ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$defaults = self::get_defaults();
		$settings = array(
			'enabled'                   => ( isset( $raw['enabled'] ) && '1' === (string) $raw['enabled'] ) ? 'yes' : 'no',
			'delay_hours'               => max( 1, absint( isset( $raw['delay_hours'] ) ? $raw['delay_hours'] : $defaults['delay_hours'] ) ),
			'new_customer_coupon'       => sanitize_text_field( isset( $raw['new_customer_coupon'] ) ? $raw['new_customer_coupon'] : $defaults['new_customer_coupon'] ),
			'returning_customer_coupon' => sanitize_text_field( isset( $raw['returning_customer_coupon'] ) ? $raw['returning_customer_coupon'] : $defaults['returning_customer_coupon'] ),
			'email_subject_new'         => sanitize_text_field( isset( $raw['email_subject_new'] ) ? $raw['email_subject_new'] : $defaults['email_subject_new'] ),
			'email_heading_new'         => sanitize_text_field( isset( $raw['email_heading_new'] ) ? $raw['email_heading_new'] : $defaults['email_heading_new'] ),
			'email_subject_returning'   => sanitize_text_field( isset( $raw['email_subject_returning'] ) ? $raw['email_subject_returning'] : $defaults['email_subject_returning'] ),
			'email_heading_returning'   => sanitize_text_field( isset( $raw['email_heading_returning'] ) ? $raw['email_heading_returning'] : $defaults['email_heading_returning'] ),
		);

		update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Описание полей настроек для WooCommerce Settings API.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$settings = self::get_all_settings();
		$defaults = self::get_defaults();

		return array(
			array(
				'title' => __( 'Брошенные корзины', 'ms-abandoned-cart' ),
				'type'  => 'title',
				'desc'  => __( 'HTML-письма в стиле WooCommerce: отдельный шаблон для новых и постоянных клиентов, с товарами из корзины.', 'ms-abandoned-cart' ),
				'id'    => 'ms_abandoned_cart_section_title',
			),

			array(
				'title'   => __( 'Включить уведомления', 'ms-abandoned-cart' ),
				'desc'    => __( 'Отправлять письма о брошенных корзинах', 'ms-abandoned-cart' ),
				'id'      => self::OPTION_KEY . '[enabled]',
				'type'    => 'checkbox',
				'default' => 'yes',
				'value'   => $settings['enabled'],
			),

			array(
				'title'             => __( 'Задержка перед отправкой (часы)', 'ms-abandoned-cart' ),
				'desc'              => __( 'Сколько часов должно пройти с последней активности корзины', 'ms-abandoned-cart' ),
				'id'                => self::OPTION_KEY . '[delay_hours]',
				'type'              => 'number',
				'default'           => 24,
				'value'             => $settings['delay_hours'],
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'desc_tip'          => true,
			),

			array(
				'title'    => __( 'Промокод для новых клиентов', 'ms-abandoned-cart' ),
				'desc'     => __( 'Купон WooCommerce, например WELCOME15. Пусто — письмо без промокода.', 'ms-abandoned-cart' ),
				'id'       => self::OPTION_KEY . '[new_customer_coupon]',
				'type'     => 'text',
				'default'  => $defaults['new_customer_coupon'],
				'value'    => $settings['new_customer_coupon'],
				'css'      => 'width:200px;',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Промокод для постоянных клиентов', 'ms-abandoned-cart' ),
				'desc'     => __( 'Купон WooCommerce, например FLOWER5. Пусто — письмо без промокода.', 'ms-abandoned-cart' ),
				'id'       => self::OPTION_KEY . '[returning_customer_coupon]',
				'type'     => 'text',
				'default'  => $defaults['returning_customer_coupon'],
				'value'    => $settings['returning_customer_coupon'],
				'css'      => 'width:200px;',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Тема письма (новые клиенты)', 'ms-abandoned-cart' ),
				'id'       => self::OPTION_KEY . '[email_subject_new]',
				'type'     => 'text',
				'default'  => $defaults['email_subject_new'],
				'value'    => $settings['email_subject_new'],
				'css'      => 'min-width:400px;',
			),

			array(
				'title'    => __( 'Заголовок в письме (новые клиенты)', 'ms-abandoned-cart' ),
				'desc'     => __( 'Заголовок внутри HTML-шаблона WooCommerce', 'ms-abandoned-cart' ),
				'id'       => self::OPTION_KEY . '[email_heading_new]',
				'type'     => 'text',
				'default'  => $defaults['email_heading_new'],
				'value'    => $settings['email_heading_new'],
				'css'      => 'min-width:400px;',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Тема письма (постоянные клиенты)', 'ms-abandoned-cart' ),
				'id'       => self::OPTION_KEY . '[email_subject_returning]',
				'type'     => 'text',
				'default'  => $defaults['email_subject_returning'],
				'value'    => $settings['email_subject_returning'],
				'css'      => 'min-width:400px;',
			),

			array(
				'title'    => __( 'Заголовок в письме (постоянные клиенты)', 'ms-abandoned-cart' ),
				'desc'     => __( 'Заголовок внутри HTML-шаблона WooCommerce', 'ms-abandoned-cart' ),
				'id'       => self::OPTION_KEY . '[email_heading_returning]',
				'type'     => 'text',
				'default'  => $defaults['email_heading_returning'],
				'value'    => $settings['email_heading_returning'],
				'css'      => 'min-width:400px;',
				'desc_tip' => true,
			),

			array(
				'type' => 'sectionend',
				'id'   => 'ms_abandoned_cart_section_end',
			),
		);
	}
}
