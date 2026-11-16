# prestashopheaven/runtime-cart

Cart helpers: the link that reopens a customer's cart from an email (PrestaShop's own recover_cart link, which also logs the customer in).

## Use when

A module emails a customer a link back to their cart: an abandoned-cart reminder, a quote, a cart prepared by the merchant.

## Install

```
composer require prestashopheaven/runtime-cart
```

## Usage

```php
use PrestaShopHeaven\Runtime\Cart\CartRecoveryLink;

$url = CartRecoveryLink::url($context->link, (int) $cart->id, (int) $customerLangId, (int) $cart->id_shop);
```

PrestaShop itself (`FrontController::recoverCart()`, 8.1 and 9) checks the token, refuses a cart that became an order
or whose customer is gone, logs the customer in and opens the cart page. Anyone with the link is logged in as the
customer: only send it to the customer's own address.

---

Read-only split of [`prestashopheaven/mono-runtime-commerce`](https://github.com/prestashopheaven/mono-runtime-commerce): changes,
issues and pull requests go there. MIT License.
