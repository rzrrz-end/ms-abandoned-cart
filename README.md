# MS Abandoned Cart

Плагин WordPress / WooCommerce для писем о брошенных корзинах.

Отслеживает persistent-корзины авторизованных покупателей, раз в сутки проверяет их через WP-Cron и отправляет HTML-письма с промокодами для новых и постоянных клиентов.

Сделан для реального WooCommerce-магазина и использовался в продакшене.

## Возможности

- Ежедневная проверка брошенных корзин через WP-Cron (`ms_abandoned_cart_daily_check`)
- Ручной запуск проверки из настроек WooCommerce (для тестов)
- Сегментация:
  - **Новые клиенты** — нет заказов в статусах processing/completed
  - **Постоянные клиенты** — есть история заказов
- Купоны **не создаются** автоматически — в настройках указываются уже существующие коды
- HTML-шаблоны писем (логотип, таблица корзины, кнопка CTA)
- Учёт активности через `_ms_cart_last_activity`
- Одно уведомление на брошенную сессию (`_ms_abandoned_cart_notified`)
- Лог: `wp-content/uploads/ms-abandoned-cart/abandoned-cart.log`
- Совместим с WP Mail SMTP (`WC()->mailer()->send()` → `wp_mail`)
- Заявлена совместимость с WooCommerce HPOS

## Требования

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+

## Установка

1. Загрузите папку `ms-abandoned-cart` в `wp-content/plugins/`
2. Активируйте **MS Abandoned Cart** в разделе «Плагины»
3. Откройте настройки плагина в WooCommerce
4. Включите уведомления, задайте задержку (в часах), коды купонов и тексты писем
5. Создайте соответствующие купоны в WooCommerce (например `WELCOME15`, `FLOWER5`)

Структура ZIP для загрузки:

```
ms-abandoned-cart.zip
└── ms-abandoned-cart/
    ├── ms-abandoned-cart.php
    ├── assets/
    ├── includes/
    └── templates/
```

## Как работает

1. При обновлении корзины у авторизованного пользователя сохраняется время последней активности.
2. Cron (или ручной запуск) находит пользователей с непустой persistent-корзиной.
3. Если прошло больше заданной задержки и уведомление ещё не отправлялось — уходит письмо.
4. После успешного заказа флаги уведомления сбрасываются по логике плагина.

Письма **не** уходят сразу при уходе с сайта — только после cron и истечения задержки.

## Настройки (по умолчанию)

| Параметр | Значение |
|----------|----------|
| Включено | да |
| Задержка | 24 часа |
| Купон для новых | `WELCOME15` |
| Купон для постоянных | `FLOWER5` |

## Структура репозитория

```
.
├── README.md
├── LICENSE
├── .gitignore
└── ms-abandoned-cart/          ← корень плагина (эту папку кладут в plugins)
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

## Версия

**1.2.3**

## Лицензия

GPL-2.0-or-later 
