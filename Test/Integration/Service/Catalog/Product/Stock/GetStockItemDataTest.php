<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Msi\Test\Integration\Catalog\Product\Stock;

use Klevu\Msi\Api\Service\Catalog\Product\Stock\GetStockItemDataInterface;
use Klevu\Msi\Service\Catalog\Product\Stock\GetStockItemData;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface as MsiGetStockItemDataInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class GetStockItemDataTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public function testImplements_GetStockItemDataServiceInterface(): void
    {
        $this->assertInstanceOf(
            GetStockItemDataInterface::class,
            $this->instantiateGetStockItemDataService()
        );
    }

    public function testPreference_ForGetStockItemDataService(): void
    {
        $this->assertInstanceOf(
            GetStockItemData::class,
            $this->objectManager->create(GetStockItemDataInterface::class)
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testExecute_ReturnsStockData_ForDefaultStock(): void
    {
        static::loadStockFixturesRollback();

        $store = $this->getStore('klevu_test_store_1');
        $storeId = (int)$store->getId();
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $storeManager->setCurrentStore($store);

        $productConfigurableInStock = $this->getProduct('klevu_configurable_synctest_instock_childreninstock', $storeId);
        $productConfigurableOos = $this->getProduct('klevu_configurable_synctest_oos_childreninstock', $storeId);
        $productConfigurableInStockChildOos = $this->getProduct('klevu_configurable_synctest_instock_childrenoos', $storeId);
        $productBundleInStock = $this->getProduct('klevu_bundle_synctest_instock_childreninstock', $storeId);
        $productBundleOos = $this->getProduct('klevu_bundle_synctest_oos_childreninstock', $storeId);
        $productBundleInStockChildOos = $this->getProduct('klevu_bundle_synctest_instock_childrenoos', $storeId);

        $productIds = [
            $productConfigurableInStock->getId(),
            $productConfigurableOos->getId(),
            $productConfigurableInStockChildOos->getId(),
            $productBundleInStock->getId(),
            $productBundleOos->getId(),
            $productBundleInStockChildOos->getId(),
        ];
        $service = $this->instantiateGetStockItemDataService();
        $stockData = $service->execute($productIds, Stock::DEFAULT_STOCK_ID);

        $this->assertArrayHasKey($productConfigurableInStock->getId(), $stockData);
        $productStockConfigurableIs = $stockData[$productConfigurableInStock->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productStockConfigurableIs);
        $this->assertSame('1', $productStockConfigurableIs[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productStockConfigurableIs);
        $this->assertSame('0.0000', $productStockConfigurableIs[MsiGetStockItemDataInterface::QUANTITY]);

        $this->assertArrayHasKey($productConfigurableOos->getId(), $stockData);
        $productStockConfigurableOos = $stockData[$productConfigurableOos->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productStockConfigurableOos);
        $this->assertSame('0', $productStockConfigurableOos[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productStockConfigurableOos);
        $this->assertSame('0.0000', $productStockConfigurableOos[MsiGetStockItemDataInterface::QUANTITY]);

        $this->assertArrayHasKey($productConfigurableInStockChildOos->getId(), $stockData);
        $productConfigurableInStockChildOos = $stockData[$productConfigurableInStockChildOos->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productConfigurableInStockChildOos);
        $this->assertSame('0', $productConfigurableInStockChildOos[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productConfigurableInStockChildOos);
        $this->assertSame('0.0000', $productConfigurableInStockChildOos[MsiGetStockItemDataInterface::QUANTITY]);

        $this->assertArrayHasKey($productBundleInStock->getId(), $stockData);
        $productStockBundleIn = $stockData[$productBundleInStock->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productStockBundleIn);
        $this->assertSame('1', $productStockBundleIn[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productStockBundleIn);
        $this->assertSame('0.0000', $productStockBundleIn[MsiGetStockItemDataInterface::QUANTITY]);

        $this->assertArrayHasKey($productBundleOos->getId(), $stockData);
        $productStockBundleOss = $stockData[$productBundleOos->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productStockBundleOss);
        $this->assertSame('0', $productStockBundleOss[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productStockBundleOss);
        $this->assertSame('0.0000', $productStockBundleOss[MsiGetStockItemDataInterface::QUANTITY]);

        $this->assertArrayHasKey($productBundleInStockChildOos->getId(), $stockData);
        $productBundleInStockChildOos = $stockData[$productBundleInStockChildOos->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productBundleInStockChildOos);
        $this->assertSame('0', $productBundleInStockChildOos[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productBundleInStockChildOos);
        $this->assertSame('0.0000', $productBundleInStockChildOos[MsiGetStockItemDataInterface::QUANTITY]);

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testExecute_ReturnsStockData_ForNotDefaultStock(): void
    {
        static::loadStockFixturesRollback();

        $store = $this->getStore('klevu_test_store_1');
        $storeId = (int)$store->getId();
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $storeManager->setCurrentStore($store);

        $stock = $this->getStock('klevu_test_stock_1');
        $stockId = $stock->getStockId();

        $productConfigurableInStock = $this->getProduct('klevu_configurable_synctest_instock_childreninstock', $storeId);
        $productConfigurableOos = $this->getProduct('klevu_configurable_synctest_oos_childreninstock', $storeId);
        $productConfigurableInStockChildOos = $this->getProduct('klevu_configurable_synctest_instock_childrenoos', $storeId);
        $productBundleInStock = $this->getProduct('klevu_bundle_synctest_instock_childreninstock', $storeId);
        $productBundleOos = $this->getProduct('klevu_bundle_synctest_oos_childreninstock', $storeId);
        $productBundleInStockChildOos = $this->getProduct('klevu_bundle_synctest_instock_childrenoos', $storeId);

        $productIds = [
            $productConfigurableInStock->getId(),
            $productConfigurableOos->getId(),
            $productConfigurableInStockChildOos->getId(),
            $productBundleInStock->getId(),
            $productBundleOos->getId(),
            $productBundleInStockChildOos->getId()
        ];
        $service = $this->instantiateGetStockItemDataService();
        $stockData = $service->execute($productIds, $stockId);

        $this->assertArrayHasKey($productConfigurableInStock->getId(), $stockData);
        $productStockConfigurableIs = $stockData[$productConfigurableInStock->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productStockConfigurableIs);
        $this->assertSame('1', $productStockConfigurableIs[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productStockConfigurableIs);
        $this->assertSame('200.0000', $productStockConfigurableIs[MsiGetStockItemDataInterface::QUANTITY]);

        $this->assertArrayHasKey($productConfigurableOos->getId(), $stockData);
        $productStockConfigurableOos = $stockData[$productConfigurableOos->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productStockConfigurableOos);
        // configurable products in inventory_stock_x do not take into parent stock status and return in stock
        $this->assertSame('1', $productStockConfigurableOos[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productStockConfigurableOos);
        $this->assertSame('200.0000', $productStockConfigurableOos[MsiGetStockItemDataInterface::QUANTITY]);

        $this->assertArrayHasKey($productConfigurableInStockChildOos->getId(), $stockData);
        $productConfigurableInStockChildOos = $stockData[$productConfigurableInStockChildOos->getId()];
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::IS_SALABLE, $productConfigurableInStockChildOos);
        $this->assertSame('0', $productConfigurableInStockChildOos[MsiGetStockItemDataInterface::IS_SALABLE]);
        $this->assertArrayhasKey(MsiGetStockItemDataInterface::QUANTITY, $productConfigurableInStockChildOos);
        $this->assertSame('0.0000', $productConfigurableInStockChildOos[MsiGetStockItemDataInterface::QUANTITY]);

        // bundle products are not available in inventory_stock_x
        $this->assertArrayNotHasKey($productBundleInStock->getId(), $stockData);
        $this->assertArrayNotHasKey($productBundleOos->getId(), $stockData);
        $this->assertArrayNotHasKey($productBundleInStockChildOos->getId(), $stockData);

        static::loadWebsiteFixturesRollback();
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
     * @return GetStockItemData
     */
    private function instantiateGetStockItemDataService(array $arguments = []): GetStockItemData
    {
        return $this->objectManager->create(GetStockItemData::class, $arguments);
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
     * @param string $stockCode
     *
     * @return StockInterface
     */
    private function getStock(string $stockCode): StockInterface
    {
        $filter = $this->objectManager->create(Filter::class);
        $filter->setField('name');
        $filter->setValue($stockCode);
        $filter->setConditionType('eq');

        $searchItemBuilderFactory = $this->objectManager->create(SearchCriteriaBuilderFactory::class);
        $searchItemBuilder = $searchItemBuilderFactory->create();
        $searchItemBuilder->addFilter($filter);
        $searchItemCriteria = $searchItemBuilder->create();

        $stockRepository = $this->objectManager->create(StockRepositoryInterface::class);
        $stockSearchResults = $stockRepository->getList($searchItemCriteria);
        $stockItems = $stockSearchResults->getItems();

        return array_shift($stockItems);
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
