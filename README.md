# MS Abandoned Cart

WordPress / WooCommerce plugin for abandoned cart recovery emails.

Tracks logged-in customers’ persistent carts, runs a daily check (WP-Cron), and sends branded HTML emails with preset coupon codes for new and returning customers.

Built for a real WooCommerce flower-shop store (production use).

## Features

- Daily abandoned-cart check via WP-Cron (`ms_abandoned_cart_daily_check`)
- Manual “run check” from WooCommerce settings (for testing)
- Segmentation:
  - **New customers** — no processing/completed orders
  - **Returning customers** — have order history
- Coupons are **not** auto-created; you set existing coupon codes in settings
- HTML email templates (logo, cart table, CTA)
- Activity tracking via `_ms_cart_last_activity`
- One notification per abandoned session (`_ms_abandoned_cart_notified`)
- File log: `wp-content/uploads/ms-abandoned-cart/abandoned-cart.log`
- Compatible with WP Mail SMTP (`WC()->mailer()->send()` → `wp_mail`)
- WooCommerce HPOS compatibility declared

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+

## Installation

1. Upload the `ms-abandoned-cart` folder to `wp-content/plugins/`
2. Activate **MS Abandoned Cart** in Plugins
3. Open **WooCommerce → Settings → Abandoned Cart** (or the plugin settings screen)
4. Enable notifications, set delay (hours), coupon codes, and email copy
5. Create matching coupons in WooCommerce (e.g. `WELCOME15`, `FLOWER5`)

ZIP structure for upload:

```
ms-abandoned-cart.zip
└── ms-abandoned-cart/
    ├── ms-abandoned-cart.php
    ├── assets/
    ├── includes/
    └── templates/
```

## How it works

1. When a logged-in user updates the cart, the plugin stores last activity time.
2. Cron (or manual run) finds users with a non-empty persistent cart.
3. If delay since last activity is exceeded and the user was not notified yet → send email.
4. After a successful order, notification flags are cleared as needed.

Emails are **not** sent instantly on leave — only after cron + delay.

## Settings (defaults)

| Option | Default |
|--------|---------|
| Enabled | yes |
| Delay | 24 hours |
| New customer coupon | `WELCOME15` |
| Returning customer coupon | `FLOWER5` |

## Repository layout

```
.
├── README.md
├── LICENSE
├── .gitignore
└── ms-abandoned-cart/          ← plugin root (upload this folder)
    ├── ms-abandoned-cart.php
    ├── assets/
    │   └── logo-veresk.png
    ├── includes/
    │   ├── class-ms-abandoned-cart.php
    │   └── class-ms-abandoned-cart-settings.php
    └── templates/emails/
        ├── email-wrapper.php
        ├── email-cart-items.php
        ├── abandoned-cart-new-customer.php
        └── abandoned-cart-returning-customer.php
```

## Version

**1.2.3**

## License

GPL-2.0-or-later (same spirit as WordPress plugins).
