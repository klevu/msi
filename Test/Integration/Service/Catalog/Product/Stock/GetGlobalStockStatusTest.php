<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Msi\Test\Integration\Catalog\Product\Stock;

use Klevu\Msi\Api\Service\Catalog\Product\Stock\GetGlobalStockStatusInterface;
use Klevu\Msi\Service\Catalog\Product\Stock\GetGlobalStockStatus;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class GetGlobalStockStatusTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public function testImplements_GetGlobalStockStatusInterface(): void
    {
        $this->assertInstanceOf(
            GetGlobalStockStatusInterface::class,
            $this->instantiateGetGlobalStockStatus()
        );
    }

    public function testPreference_ForGetGlobalStockStatusInterface(): void
    {
        $this->assertInstanceOf(
            GetGlobalStockStatus::class,
            $this->objectManager->create(GetGlobalStockStatusInterface::class)
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testExecute_ReturnsStockItem_forConfigurableProduct_DefaultStock(): void
    {
        $store = $this->getStore('klevu_test_store_1');
        $product1 = $this->getProduct('klevu_configurable_synctest_instock_childreninstock', (int)$store->getId());
        $product2 = $this->getProduct('klevu_configurable_synctest_oos_childreninstock', (int)$store->getId());
        $product3 = $this->getProduct('klevu_configurable_synctest_instock_childrenoos', (int)$store->getId());

        $service = $this->instantiateGetGlobalStockStatus();
        $stockItems = $service->execute([
            (int)$product1->getId(),
            (int)$product2->getId(),
            (int)$product3->getId(),
        ]);

        $stockItem1 = $this->filterStockItems($stockItems, $product1);
        $this->assertTrue(
            $stockItem1->getIsInStock(),
            'In Stock: klevu_configurable_synctest_instock_childreninstock'
        );

        $stockItem2 = $this->filterStockItems($stockItems, $product2);
        $this->assertFalse(
            $stockItem2->getIsInStock(),
            'Out Of Stock: klevu_configurable_synctest_oos_childreninstock'
        );

        $stockItem3 = $this->filterStockItems($stockItems, $product3);
        $this->assertTrue(
            $stockItem3->getIsInStock(),
            'In Stock: klevu_configurable_synctest_instock_childrenoos'
        );

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testExecute_ReturnsStockItem_forBundleProduct_DefaultStock(): void
    {
        $store = $this->getStore('klevu_test_store_1');
        $product1 = $this->getProduct('klevu_bundle_synctest_instock_childreninstock', (int)$store->getId());
        $product2 = $this->getProduct('klevu_bundle_synctest_instock_childrenoos', (int)$store->getId());
        $product3 = $this->getProduct('klevu_bundle_synctest_oos_childreninstock', (int)$store->getId());
        $product4 = $this->getProduct('klevu_bundle_synctest_instock_childrenoos_notrequired', (int)$store->getId());

        $service = $this->instantiateGetGlobalStockStatus();
        $stockItems = $service->execute([
            (int)$product1->getId(),
            (int)$product2->getId(),
            (int)$product3->getId(),
            (int)$product4->getId(),
        ]);

        $stockItem1 = $this->filterStockItems($stockItems, $product1);
        $this->assertTrue(
            $stockItem1->getIsInStock(),
            'In Stock: klevu_bundle_synctest_instock_childreninstock'
        );

        $stockItem2 = $this->filterStockItems($stockItems, $product2);
        $this->assertTrue(
            $stockItem2->getIsInStock(),
            'In Stock: klevu_bundle_synctest_instock_childrenoos'
        );

        $stockItem3 = $this->filterStockItems($stockItems, $product3);
        $this->assertFalse(
            $stockItem3->getIsInStock(),
            'Out Of Stock: klevu_bundle_synctest_oos_childreninstock'
        );

        $stockItem4 = $this->filterStockItems($stockItems, $product4);
        $this->assertTrue(
            $stockItem4->getIsInStock(),
            'In Stock: klevu_bundle_synctest_instock_childrenoos_notrequired'
        );

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testExecute_ReturnsStockItem_forConfigurableProduct_NotDefaultStock(): void
    {
        $store = $this->getStore('klevu_test_store_1');
        $product1 = $this->getProduct('klevu_configurable_synctest_instock_childreninstock', (int)$store->getId());
        $product2 = $this->getProduct('klevu_configurable_synctest_oos_childreninstock', (int)$store->getId());
        $product3 = $this->getProduct('klevu_configurable_synctest_instock_childrenoos', (int)$store->getId());

        $service = $this->instantiateGetGlobalStockStatus();
        $stockItems = $service->execute([
            (int)$product1->getId(),
            (int)$product2->getId(),
            (int)$product3->getId(),
        ]);

        $stockItem1 = $this->filterStockItems($stockItems, $product1);
        $this->assertTrue(
            $stockItem1->getIsInStock(),
            'In Stock: klevu_configurable_synctest_instock_childreninstock'
        );

        $stockItem2 = $this->filterStockItems($stockItems, $product2);
        $this->assertFalse(
            $stockItem2->getIsInStock(),
            'Out Of Stock: klevu_configurable_synctest_oos_childreninstock'
        );

        $stockItem3 = $this->filterStockItems($stockItems, $product3);
        $this->assertTrue(
            $stockItem3->getIsInStock(),
            'In Stock: klevu_configurable_synctest_instock_childrenoos'
        );

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testExecute_ReturnsStockItem_forBundleProduct_NotDefaultStock(): void
    {
        $store = $this->getStore('klevu_test_store_1');
        $product1 = $this->getProduct('klevu_bundle_synctest_instock_childreninstock', (int)$store->getId());
        $product2 = $this->getProduct('klevu_bundle_synctest_instock_childrenoos', (int)$store->getId());
        $product3 = $this->getProduct('klevu_bundle_synctest_oos_childreninstock', (int)$store->getId());
        $product4 = $this->getProduct('klevu_bundle_synctest_instock_childrenoos_notrequired', (int)$store->getId());

        $service = $this->instantiateGetGlobalStockStatus();
        $stockItems = $service->execute([
            (int)$product1->getId(),
            (int)$product2->getId(),
            (int)$product3->getId(),
            (int)$product4->getId(),
        ]);

        $stockItem1 = $this->filterStockItems($stockItems, $product1);
        $this->assertTrue(
            $stockItem1->getIsInStock(),
            'In Stock: klevu_bundle_synctest_instock_childreninstock'
        );

        $stockItem2 = $this->filterStockItems($stockItems, $product2);
        $this->assertTrue(
            $stockItem2->getIsInStock(),
            'In Stock: klevu_bundle_synctest_instock_childrenoos'
        );

        $stockItem3 = $this->filterStockItems($stockItems, $product3);
        $this->assertFalse(
            $stockItem3->getIsInStock(),
            'Out Of Stock: klevu_bundle_synctest_oos_childreninstock'
        );

        $stockItem4 = $this->filterStockItems($stockItems, $product4);
        $this->assertTrue(
            $stockItem4->getIsInStock(),
            'In Stock: klevu_bundle_synctest_instock_childrenoos_notrequired'
        );

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @param StockItemInterface[] $stockItems
     * @param ProductInterface $product
     *
     * @return StockItemInterface
     */
    private function filterStockItems(array $stockItems, ProductInterface $product): StockItemInterface
    {
        $stockItems = array_filter($stockItems, static function (StockItemInterface $stockItem) use ($product) {
            return (int)$product->getId() === (int)$stockItem->getProductId();
        });

        return array_shift($stockItems);
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @param mixed[] $arguments
     *
     * @return GetGlobalStockStatus
     */
    private function instantiateGetGlobalStockStatus(array $arguments = []): GetGlobalStockStatus
    {
        return $this->objectManager->create(GetGlobalStockStatus::class, $arguments);
    }

    /**
     * @param string $sku
     * @param int|null $storeId
     *
     * @return ProductInterface
     */
    private function getProduct(string $sku, ?int $storeId): ProductInterface
    {
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);

        try {
            return $productRepository->get($sku, false, $storeId, true);
        } catch (NoSuchEntityException $e) {
            $this->fail(sprintf('SKU: %s could not be loaded for store %s', $sku, $storeId));
        }
    }

    /**
     * @param string $storeCode
     *
     * @return StoreInterface
     */
    private function getStore(string $storeCode): StoreInterface
    {
        $storeRepository = $this->objectManager->get(StoreRepositoryInterface::class);

        try {
            return $storeRepository->get($storeCode);
        } catch (NoSuchEntityException $e) {
            $this->fail(sprintf('Store: %s could not be loaded.', $storeCode));
        }
    }

    /**
     * Loads product creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadProductFixtures(): void
    {
        include __DIR__ . '/_files/productFixtures.php';
    }

    /**
     * Rolls back product creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadProductFixturesRollback(): void
    {
        include __DIR__ . '/_files/productFixtures_rollback.php';
    }

    /**
     * Loads website stock creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadStockFixtures(): void
    {
        include __DIR__ . '/../../../../_files/stockFixtures.php';
    }

    /**
     * Rolls back website stock creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadStockFixturesRollback(): void
    {
        include __DIR__ . '/../../../../_files/stockFixtures_rollback.php';
    }

    /**
     * Loads store and website creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadWebsiteFixtures(): void
    {
        include __DIR__ . '/../../../../_files/websiteFixtures.php';
    }

    /**
     * Rolls back store and website creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadWebsiteFixturesRollback(): void
    {
        include __DIR__ . '/../../../../_files/websiteFixtures_rollback.php';
    }
}
