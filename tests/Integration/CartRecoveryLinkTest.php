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

namespace PrestaShopHeaven\Runtime\Cart\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Cart\CartRecoveryLink;

/**
 * CartRecoveryLink in a real shop: the link of a customer's cart opens the cart page with that cart (a browser without
 * any cookie sees the product), a link with a wrong token does not, and the URL is the cart page of the shop.
 */
final class CartRecoveryLinkTest extends TestCase
{
    private \Customer $customer;

    private \Cart $cart;

    private string $productName = '';

    /** @var string[] cookie files of the test */
    private array $jars = [];

    /**
     * A customer with a cart holding one product.
     */
    protected function setUp(): void
    {
        // Cart::updateQty() runs hooks of other modules (ps_googleanalytics) that need the context currency (CLI).
        \Context::getContext()->currency = new \Currency((int) \Configuration::get('PS_CURRENCY_DEFAULT'));
        $shopId = (int) \Configuration::get('PS_SHOP_DEFAULT');
        $languageId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $this->customer = new \Customer();
        $this->customer->firstname = 'Recover';
        $this->customer->lastname = 'Fixture';
        $this->customer->email = 'recover-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->customer->passwd = md5('recover-fixture-' . random_int(1, PHP_INT_MAX));
        $this->customer->id_shop = $shopId;
        self::assertTrue((bool) $this->customer->add());
        $productId = (int) \Db::getInstance()->getValue(
            'SELECT p.id_product FROM `' . _DB_PREFIX_ . 'product` p INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = '
            . $shopId . ' WHERE ps.active = 1 AND p.cache_is_pack = 0 AND p.product_type <> \'combinations\' ORDER BY p.id_product'
        );
        self::assertGreaterThan(0, $productId);
        $this->productName = (string) \Product::getProductName($productId, null, $languageId);
        $this->cart = new \Cart();
        $this->cart->id_customer = (int) $this->customer->id;
        $this->cart->id_shop = $shopId;
        $this->cart->id_shop_group = (int) \Shop::getGroupFromShop($shopId);
        $this->cart->id_lang = $languageId;
        $this->cart->id_currency = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $this->cart->secure_key = (string) $this->customer->secure_key;
        self::assertTrue((bool) $this->cart->add());
        self::assertNotFalse($this->cart->updateQty(1, $productId));
    }

    /**
     * Removes the cart, the customer and the cookie files.
     */
    protected function tearDown(): void
    {
        $this->cart->delete();
        $this->customer->delete();
        foreach ($this->jars as $jar) {
            if (is_file($jar)) {
                unlink($jar);
            }
        }
    }

    public function testTheUrlIsTheCartPageWithTheToken(): void
    {
        $url = $this->url();

        self::assertStringContainsString('recover_cart=' . (int) $this->cart->id, $url);
        self::assertStringContainsString('token_cart=' . CartRecoveryLink::token((int) $this->cart->id), $url);
        self::assertStringContainsString('action=show', $url);
    }

    public function testTheLinkOpensTheCartOfTheCustomer(): void
    {
        $jar = $this->jar();

        $this->get($this->url(), $jar);
        $page = $this->get($this->cartPage(), $jar);

        self::assertStringContainsString(htmlspecialchars($this->productName, ENT_QUOTES), $page, 'the product of the recovered cart');
    }

    public function testALinkWithAWrongTokenOpensNothing(): void
    {
        $jar = $this->jar();
        $url = str_replace('token_cart=' . CartRecoveryLink::token((int) $this->cart->id), 'token_cart=wrong', $this->url());

        $this->get($url, $jar);
        $page = $this->get($this->cartPage(), $jar);

        self::assertStringNotContainsString(htmlspecialchars($this->productName, ENT_QUOTES), $page);
    }

    public function testAWrongLanguageOrShopIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CartRecoveryLink::url(\Context::getContext()->link, (int) $this->cart->id, 0, 1);
    }

    /**
     * The recovery URL of the test cart.
     */
    private function url(): string
    {
        return CartRecoveryLink::url(\Context::getContext()->link, (int) $this->cart->id, (int) $this->cart->id_lang, (int) $this->cart->id_shop);
    }

    /**
     * The plain cart page of the shop.
     */
    private function cartPage(): string
    {
        return \Context::getContext()->link->getPageLink('cart', null, (int) $this->cart->id_lang, ['action' => 'show'], false, (int) $this->cart->id_shop);
    }

    /**
     * A new cookie file.
     */
    private function jar(): string
    {
        $jar = (string) tempnam(sys_get_temp_dir(), 'heavenmonocommerce');
        $this->jars[] = $jar;

        return $jar;
    }

    /**
     * Gets a page of the shop through the web server of the container, with a cookie file, following redirects and the
     * "[Debug] This page has moved" page of dev mode (TEST-12).
     */
    private function get(string $url, string $jar): string
    {
        for ($hop = 0; $hop < 5; ++$hop) {
            $parts = (array) parse_url($url);
            $curl = curl_init($url);
            self::assertNotFalse($curl);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_COOKIEJAR => $jar,
                CURLOPT_COOKIEFILE => $jar,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (fixture test)',
                CURLOPT_CONNECT_TO => [($parts['host'] ?? 'localhost') . ':' . ($parts['port'] ?? 80) . ':127.0.0.1:80'],
            ]);
            $body = (string) curl_exec($curl);
            if (!preg_match('/This page has moved.*?<a href="([^"]+)"/s', $body, $match)) {
                return $body;
            }
            $url = html_entity_decode($match[1]);
        }

        return $body;
    }
}
