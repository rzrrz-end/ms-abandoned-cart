<?php
/**
 * Письмо о брошенной корзине — постоянный клиент.
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

<p style="margin:0 0 16px;color:#000000;">Прекрасный выбор! Вы выбрали букет, который уже через час может порадовать получателя!</p>

<p style="margin:0 0 16px;color:#000000;">Мы сохранили его в вашей корзине — завершите покупку буквально за 2 минуты.</p>

<p style="margin:0 0 16px;color:#000000;">В знак благодарности за то, что вы уже выбирали Veresk, дарим вам промокод на 5%.</p>

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

<p style="margin:0 0 8px;color:#000000;"><strong>Почему стоит вернуться?</strong></p>
<ul style="margin:0 0 28px;padding-left:20px;color:#000000;">
	<li style="margin-bottom:6px;">фотография букета перед отправкой;</li>
	<li style="margin-bottom:6px;">только свежие цветы;</li>
	<li style="margin-bottom:6px;">бережная доставка по Москве и области.</li>
</ul>

<table border="0" cellpadding="0" cellspacing="0" width="100%">
	<tr>
		<td align="center" style="text-align:center;">
			<a href="<?php echo esc_url( $cart_link ); ?>" style="display:inline-block;padding:14px 32px;background-color:<?php echo esc_attr( $brand_green ); ?>;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:700;font-size:15px;font-family:Arial,Helvetica,sans-serif;">
				Вернуться к заказу
			</a>
		</td>
	</tr>
</table>
