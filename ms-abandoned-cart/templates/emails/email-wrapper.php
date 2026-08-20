<?php
/**
 * Полная HTML-обёртка письма Veresk (белый фон, логотип, без фиолетового WC-header).
 *
 * @package MS_Abandoned_Cart
 *
 * @var string $email_heading Заголовок письма.
 * @var string $email_content HTML-тело письма.
 * @var string $logo_url      URL логотипа.
 * @var string $site_name     Название сайта.
 * @var string $brand_green   Фирменный зелёный (#197B4F).
 */

defined( 'ABSPATH' ) || exit;

$brand_green = isset( $brand_green ) ? $brand_green : '#197B4F';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo esc_html( $email_heading ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;width:100%;-webkit-text-size-adjust:100%;">
	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#ffffff;">
		<tr>
			<td align="center" style="padding:32px 16px;">
				<table border="0" cellpadding="0" cellspacing="0" width="600" style="width:100%;max-width:600px;background-color:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#000000;">
					<tr>
						<td align="center" style="padding:0 0 28px;">
							<?php if ( ! empty( $logo_url ) ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="180" style="display:block;max-width:180px;height:auto;border:0;" />
							<?php else : ?>
								<span style="font-family:Georgia,serif;font-size:28px;font-weight:bold;letter-spacing:0.08em;color:#1a2433;"><?php echo esc_html( $site_name ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td style="padding:0 0 24px;">
							<h1 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:26px;font-weight:700;line-height:1.3;color:#000000;text-align:left;">
								<?php echo esc_html( $email_heading ); ?>
							</h1>
						</td>
					</tr>
					<tr>
						<td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;color:#000000;">
							<?php echo $email_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML из шаблонов плагина. ?>
						</td>
					</tr>
					<tr>
						<td style="padding:36px 0 0;border-top:1px solid #eeeeee;margin-top:24px;font-size:12px;line-height:1.5;color:#666666;text-align:center;">
							<?php echo esc_html( $site_name ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
