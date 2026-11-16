<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author    prestahaven.com
 * @copyright Since 2026 George van Engers
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace PrestaShopHeaven\Runtime\Cart;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The link that reopens a customer's cart from an email: PrestaShop's own recover_cart link (FrontController::
 * recoverCart() in 8.1 and 9), which checks the token, refuses a cart that became an order or whose customer is gone,
 * logs the customer in and makes the cart the current one. The link opens the cart page.
 *
 * Anyone with the link is logged in as the cart's customer: send it only to the customer's own address.
 */
final class CartRecoveryLink
{
    /**
     * The token PrestaShop expects for a cart (token_cart).
     *
     * @throws \InvalidArgumentException when the cart id is not positive
     */
    public static function token(int $cartId): string
    {
        if ($cartId <= 0) {
            throw new \InvalidArgumentException(sprintf('A cart id is positive, not %d.', $cartId));
        }

        return md5(_COOKIE_KEY_ . 'recover_cart_' . $cartId);
    }

    /**
     * The URL of the cart page that reopens the cart, in a language and on the domain of a shop (MS-10).
     *
     * @throws \InvalidArgumentException when an id is not positive
     */
    public static function url(\Link $link, int $cartId, int $langId, int $shopId): string
    {
        if ($langId <= 0 || $shopId <= 0) {
            throw new \InvalidArgumentException('A cart recovery link needs a language and a shop.');
        }

        return $link->getPageLink(
            'cart',
            true,
            $langId,
            ['action' => 'show', 'recover_cart' => $cartId, 'token_cart' => self::token($cartId)],
            false,
            $shopId
        );
    }
}
