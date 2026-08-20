<?php
/**
 * Письмо о брошенной корзине — новый клиент.
 *
 * @package MS_Abandoned_Cart
 *
 * @var string $coupon_code  Код промокода.
 * @var string $cart_link    Ссылка на корзину.
 * @var array  $cart_items   Товары корзины.
 * @var string $site_name    Название сайта.
 * @var string $brand_green  Фирменный зелёный.
 */

defined( 'ABSPATH' ) || exit;

$brand_green = isset( $brand_green ) ? $brand_green : '#197B4F';
?>

<p style="margin:0 0 16px;color:#000000;">У вас отличный вкус! Порадуйте получателя сегодня — завершите заказ!</p>

<p style="margin:0 0 16px;color:#000000;">Чтобы первое знакомство с Veresk стало ещё приятнее, мы подготовили для вас скидку 15% на первый заказ.</p>

<?php if ( '' !== (string) $coupon_code ) : ?>
	<p style="margin:0 0 24px;padding:14px 16px;background-color:#f5faf7;border-left:3px solid <?php echo esc_attr( $brand_green ); ?>;color:#000000;">
		<strong>Промокод:</strong>
		<span style="font-size:16px;font-weight:700;letter-spacing:0.5px;color:<?php echo esc_attr( $brand_green ); ?>;"><?php echo esc_html( $coupon_code ); ?></span>
	</p>
<?php endif; ?>

<?php
$cart_items_template = MS_ABANDONED_CART_PATH . 'templates/emails/email-cart-items.php';
if ( file_exists( $cart_items_template ) ) {
	include $cart_items_template;
}
?>

<p style="margin:0 0 8px;color:#000000;">Мы обязательно:</p>
<ul style="margin:0 0 16px;padding-left:20px;color:#000000;">
	<li style="margin-bottom:6px;">пришлём фотографию готового букета перед доставкой;</li>
	<li style="margin-bottom:6px;">соберём его из самых свежих цветов;</li>
	<li style="margin-bottom:6px;">доставим точно в назначенное время.</li>
</ul>

<p style="margin:0 0 28px;color:#000000;">Будем рады помочь сделать чей-то день особенным.</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%">
	<tr>
		<td align="center" style="text-align:center;">
			<a href="<?php echo esc_url( $cart_link ); ?>" style="display:inline-block;padding:14px 32px;background-color:<?php echo esc_attr( $brand_green ); ?>;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:700;font-size:15px;font-family:Arial,Helvetica,sans-serif;">
				Вернуться к заказу
			</a>
		</td>
	</tr>
</table>
