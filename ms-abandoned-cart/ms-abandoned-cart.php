<?php
/**
 * Plugin Name: MS Abandoned Cart
 * Plugin URI:  https://example.com/ms-abandoned-cart
 * Description: Отслеживание брошенных корзин WooCommerce и отправка email-уведомлений с промокодами из настроек.
 * Version:     1.2.3
 * Author:      MS
 * Text Domain: ms-abandoned-cart
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package MS_Abandoned_Cart
 */

defined( 'ABSPATH' ) || exit;

// Константы плагина.
define( 'MS_ABANDONED_CART_VERSION', '1.2.3' );
define( 'MS_ABANDONED_CART_PATH', plugin_dir_path( __FILE__ ) );
define( 'MS_ABANDONED_CART_URL', plugin_dir_url( __FILE__ ) );
define( 'MS_ABANDONED_CART_FILE', __FILE__ );

/**
 * Автозагрузка классов плагина.
 *
 * @param string $class Имя класса.
 */
function ms_abandoned_cart_autoload( $class ) {
	$prefix = 'MS_Abandoned_Cart';

	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	// MS_Abandoned_Cart → class-ms-abandoned-cart.php
	// MS_Abandoned_Cart_Settings → class-ms-abandoned-cart-settings.php
	$file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
	$path = MS_ABANDONED_CART_PATH . 'includes/' . $file;

	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
spl_autoload_register( 'ms_abandoned_cart_autoload' );

/**
 * Активация плагина: планирование ежедневного cron.
 */
function ms_abandoned_cart_activate() {
	if ( ! wp_next_scheduled( 'ms_abandoned_cart_daily_check' ) ) {
		wp_schedule_event( time(), 'daily', 'ms_abandoned_cart_daily_check' );
	}
}
register_activation_hook( __FILE__, 'ms_abandoned_cart_activate' );

/**
 * Деактивация плагина: очистка cron (настройки и логи сохраняются).
 */
function ms_abandoned_cart_deactivate() {
	$timestamp = wp_next_scheduled( 'ms_abandoned_cart_daily_check' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'ms_abandoned_cart_daily_check' );
	}
	wp_clear_scheduled_hook( 'ms_abandoned_cart_daily_check' );
}
register_deactivation_hook( __FILE__, 'ms_abandoned_cart_deactivate' );

/**
 * Объявление совместимости с HPOS (до инициализации WooCommerce).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				MS_ABANDONED_CART_FILE,
				true
			);
		}
	}
);

/**
 * Инициализация плагина после загрузки всех плагинов.
 */
function ms_abandoned_cart_init() {
	// Плагин работает только при активном WooCommerce.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ms_abandoned_cart_woocommerce_missing_notice' );
		return;
	}

	new MS_Abandoned_Cart();

	// Миграция настроек в единую опцию (в т.ч. при cron / фронте).
	MS_Abandoned_Cart_Settings::maybe_migrate_legacy_options();

	// Если cron пропал (деплой без реактивации плагина) — планируем снова.
	if ( ! wp_next_scheduled( 'ms_abandoned_cart_daily_check' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'ms_abandoned_cart_daily_check' );
	}

	if ( is_admin() ) {
		new MS_Abandoned_Cart_Settings();
	}
}
add_action( 'plugins_loaded', 'ms_abandoned_cart_init' );

/**
 * Уведомление, если WooCommerce не активен.
 */
function ms_abandoned_cart_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'MS Abandoned Cart требует активный плагин WooCommerce.', 'ms-abandoned-cart' );
	echo '</p></div>';
}
