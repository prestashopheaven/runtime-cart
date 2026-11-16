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

namespace PrestaShopHeaven\Runtime\Cart\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Cart\CartRecoveryLink;

/**
 * CartRecoveryLink::token() is PrestaShop's own formula (FrontController::recoverCart()); a cart id that is not positive
 * is refused. The URL needs a real shop (tests/Integration).
 */
final class CartRecoveryLinkTest extends TestCase
{
    public function testTheTokenIsTheFormulaOfPrestaShop(): void
    {
        self::assertSame(md5(_COOKIE_KEY_ . 'recover_cart_12'), CartRecoveryLink::token(12));
        self::assertNotSame(CartRecoveryLink::token(12), CartRecoveryLink::token(13));
    }

    /**
     * Cart ids that are refused.
     *
     * @return iterable<string, array{0: int}>
     */
    public static function wrongCartIds(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-4];
    }

    #[DataProvider('wrongCartIds')]
    public function testAWrongCartIdIsRefused(int $cartId): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CartRecoveryLink::token($cartId);
    }
}
