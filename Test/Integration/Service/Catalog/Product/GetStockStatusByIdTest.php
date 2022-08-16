<?php

declare(strict_types=1);

namespace Klevu\Msi\Test\Integration\Catalog\Product;

use Klevu\Msi\Service\Catalog\Product\GetStockStatusById;
use Klevu\Search\Api\Service\Catalog\Product\GetStockStatusByIdInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class GetStockStatusByIdTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function testCanBeInstantiated(): void
    {
        $getKmcUrlService = $this->instantiateGetStockStatusByIdService();

        $this->assertInstanceOf(GetStockStatusById::class, $getKmcUrlService);
    }

    public function testEmptyArrayWhenNoProductIdsAreProvided(): void
    {
        $getStockStatusById = $this->instantiateGetStockStatusByIdService();
        $stockStatus = $getStockStatusById->execute([]);

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(0, $stockStatus);
    }

    public function testEmptyArrayIsReturnedWhenStoreDoesNotExist(): void
    {
        $getStockStatusById = $this->instantiateGetStockStatusByIdService();
        $stockStatus = $getStockStatusById->execute(['1'], 99999999999);

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(0, $stockStatus);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testEmptyArrayIsReturnedWhenProductIdsDoNotExist(): void
    {
        $website = $this->getWebsite('klevu_test_website_1');

        $getStockStatusById = $this->instantiateGetStockStatusByIdService();
        $stockStatus = $getStockStatusById->execute([9999999999999], (int)$website->getId());

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(0, $stockStatus);

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadProductFixtures
     * @magentoDataFixture loadInventoryFixtures
     */
    public function testReturnsArrayWhenProductsAreSaleable(): void
    {
        $website = $this->getWebsite('klevu_test_website_1');
        $product1 = $this->getProduct('klevu_simple_1');
        $product2 = $this->getProduct('klevu_simple_2');
        $product3 = $this->getProduct('klevu_simple_3');
        $product4 = $this->getProduct('klevu_simple_4');

        $getStockStatusById = $this->instantiateGetStockStatusByIdService();
        $stockStatus = $getStockStatusById->execute(
            [
                $product1->getId(),
                $product2->getId(),
                $product3->getId(),
                $product4->getId(),
            ],
            (int)$website->getId()
        );

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(4, $stockStatus);

        $this->assertArrayHasKey($product1->getId(), $stockStatus);
        $this->assertTrue($stockStatus[$product1->getId()]);

        $this->assertArrayHasKey($product2->getId(), $stockStatus);
        $this->assertFalse($stockStatus[$product2->getId()]);

        $this->assertArrayHasKey($product3->getId(), $stockStatus);
        $this->assertFalse($stockStatus[$product3->getId()]);

        $this->assertArrayHasKey($product4->getId(), $stockStatus);
        $this->assertFalse($stockStatus[$product4->getId()]);

        static::loadInventoryFixturesRollback();
        static::loadProductFixturesRollback();
        static::loadWebsiteFixturesRollback();
    }

    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
    }

    private function instantiateGetStockStatusByIdService(): GetStockStatusByIdInterface
    {
        return $this->objectManager->create(GetStockStatusByIdInterface::class);
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getWebsite(string $websiteCode): WebsiteInterface
    {
        /** @var WebsiteRepositoryInterface $websiteRepository */
        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);

        return $websiteRepository->get($websiteCode);
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getProduct(string $sku): ProductInterface
    {
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);

        return $productRepository->get($sku);
    }

    /**
     * Loads MSI scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadInventoryFixtures(): void
    {
        include __DIR__ . '/../../../_files/source_items_on_default_source.php';
    }

    /**
     * Rolls back MSI creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadInventoryFixturesRollback(): void
    {
        include __DIR__ . '/../../../_files/source_items_on_default_source_rollback.php';
    }

    /**
     * Loads website product scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadProductFixtures(): void
    {
        include __DIR__ . '/../../../_files/productFixtures.php';
    }

    /**
     * Rolls back product creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadProductFixturesRollback(): void
    {
        include __DIR__ . '/../../../_files/productFixtures_rollback.php';
    }

    /**
     * Loads website creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadWebsiteFixtures(): void
    {
        include __DIR__ . '/../../../_files/websiteFixtures.php';
    }

    /**
     * Rolls back website creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadWebsiteFixturesRollback(): void
    {
        include __DIR__ . '/../../../_files/websiteFixtures_rollback.php';
    }
}
