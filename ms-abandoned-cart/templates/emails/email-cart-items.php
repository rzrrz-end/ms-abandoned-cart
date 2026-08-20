<?php
/**
 * Частичный шаблон: товары из брошенной корзины.
 *
 * @package MS_Abandoned_Cart
 * @var array  $cart_items
 * @var string $brand_green
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $cart_items ) || ! is_array( $cart_items ) ) {
	return;
}

$brand_green = isset( $brand_green ) ? $brand_green : '#197B4F';
?>
<div style="margin:0 0 28px;">
	<h2 style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;line-height:1.3;margin:0 0 16px;text-align:left;color:#000000;">
		Ваша корзина
	</h2>

	<table cellspacing="0" cellpadding="0" style="width:100%;font-family:Arial,Helvetica,sans-serif;border-collapse:collapse;" border="0">
		<thead>
			<tr>
				<th scope="col" style="text-align:left;padding:12px;border:1px solid #e5e5e5;color:#000000;font-weight:700;background:#ffffff;">Товар</th>
				<th scope="col" style="text-align:center;padding:12px;border:1px solid #e5e5e5;color:#000000;font-weight:700;background:#ffffff;">Кол-во</th>
				<th scope="col" style="text-align:right;padding:12px;border:1px solid #e5e5e5;color:#000000;font-weight:700;background:#ffffff;">Цена</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cart_items as $item ) : ?>
				<tr>
					<td style="text-align:left;vertical-align:middle;padding:12px;border:1px solid #e5e5e5;color:#000000;">
						<?php if ( ! empty( $item['image_url'] ) ) : ?>
							<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" width="48" height="48" style="vertical-align:middle;margin-right:12px;border-radius:4px;border:0;" />
						<?php endif; ?>
						<?php if ( ! empty( $item['product_url'] ) ) : ?>
							<a href="<?php echo esc_url( $item['product_url'] ); ?>" style="color:<?php echo esc_attr( $brand_green ); ?>;text-decoration:underline;font-weight:500;"><?php echo esc_html( $item['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item['name'] ); ?>
						<?php endif; ?>
					</td>
					<td style="text-align:center;vertical-align:middle;padding:12px;border:1px solid #e5e5e5;color:#000000;">
						<?php echo esc_html( (string) $item['quantity'] ); ?>
					</td>
					<td style="text-align:right;vertical-align:middle;padding:12px;border:1px solid #e5e5e5;color:#000000;">
						<?php echo wp_kses_post( $item['price_html'] ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
