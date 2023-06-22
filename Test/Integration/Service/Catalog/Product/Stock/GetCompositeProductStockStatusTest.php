<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Msi\Test\Integration\Catalog\Product\Stock;

use Klevu\Msi\Service\Catalog\Product\Stock\GetCompositeProductStockStatus;
use Klevu\Search\Api\Service\Catalog\Product\Stock\GetCompositeProductStockStatusInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GetCompositeProductStockStatusTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public function testImplements_GetCompositeProductStockStatusInterface(): void
    {
        $this->assertInstanceOf(
            GetCompositeProductStockStatusInterface::class,
            $this->GetCompositeProductStockStatusService()
        );
    }

    public function testPreference_ForGetStockIdForWebsiteInterface(): void
    {
        $this->assertInstanceOf(
            GetCompositeProductStockStatus::class,
            $this->objectManager->create(GetCompositeProductStockStatusInterface::class)
        );
    }

    public function testExecute_ReturnsFalse_ForIncorrectProductType(): void
    {
        $productFactory = $this->objectManager->create(ProductInterfaceFactory::class);
        $product = $productFactory->create();
        $product->setTypeId(Type::TYPE_SIMPLE);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                sprintf(
                'Method: %s - Error %s',
                'Klevu\Msi\Service\Catalog\Product\Stock\GetCompositeProductStockStatus::execute',
                sprintf(
                    'Incorrect product type must be either configurable or bundle; %s provided',
                    $product->getTypeId()
                )
            )
            );

        $service = $this->GetCompositeProductStockStatusService([
            'logger' => $mockLogger
        ]);
        $status = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);

        $this->assertFalse($status);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testExecute_DefaultScope(): void
    {
        static::loadStockFixturesRollback();

        $store = $this->getStore('klevu_test_store_1');
        $storeId = (int)$store->getId();
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $storeManager->setCurrentStore($store);

        $product = $this->getProduct('klevu_configurable_synctest_instock_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertTrue($isInStock, 'Configurable Product In Stock, Children In Stock');

        $product = $this->getProduct('klevu_configurable_synctest_instock_childrenoos', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Configurable Product In Stock, Children Out Of Stock');

        $product = $this->getProduct('klevu_configurable_synctest_oos_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Configurable Product Out Of Stock, Children In Stock');

        $product = $this->getProduct('klevu_bundle_synctest_instock_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertTrue($isInStock, 'Bundle Product In Stock, Children In Stock');

        $product = $this->getProduct('klevu_bundle_synctest_instock_childrenoos', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Bundle Product In Stock, Children Out Of Stock');

        $product = $this->getProduct('klevu_bundle_synctest_oos_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Bundle Product Out Of Stock, Children In Stock');

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadStockFixtures
     * @magentoDataFixture loadProductFixtures
     */
    public function testExecute_NotDefaultScope(): void
    {
        $store = $this->getStore('klevu_test_store_1');
        $storeId = (int)$store->getId();
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $storeManager->setCurrentStore($store);

        $stock = $this->getStock('klevu_test_stock_1');
        $stockId = $stock->getStockId();

        // None Default Scope
        $product = $this->getProduct('klevu_configurable_synctest_instock_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], $stockId);
        $this->assertTrue($isInStock, 'Configurable Product In Stock, Children In Stock');

        $product = $this->getProduct('klevu_configurable_synctest_instock_childrenoos', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], $stockId);
        $this->assertFalse($isInStock, 'Configurable Product In Stock, Children Out Of Stock');

        $product = $this->getProduct('klevu_configurable_synctest_oos_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], $stockId);
        $this->assertFalse($isInStock, 'Configurable Product Out Of Stock, Children In Stock');

        $product = $this->getProduct('klevu_bundle_synctest_instock_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], $stockId);
        $this->assertTrue($isInStock, 'Bundle Product In Stock, Children In Stock');

        $product = $this->getProduct('klevu_bundle_synctest_instock_childrenoos', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], $stockId);
        $this->assertFalse($isInStock, 'Bundle Product In Stock, Children Out Of Stock');

        $product = $this->getProduct('klevu_bundle_synctest_oos_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], $stockId);
        $this->assertFalse($isInStock, 'Bundle Product Out Of Stock, Children In Stock');

        $product = $this->getProduct('klevu_bundle_synctest_instock_childrenoos_notrequired', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], $stockId);
        $this->assertTrue($isInStock, 'Bundle Product In Stock, Children Out Of Stock Not Required');

        // Default Scope
        $product = $this->getProduct('klevu_configurable_synctest_instock_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Configurable Product In Stock, Children In Stock. Default Stock');

        $product = $this->getProduct('klevu_configurable_synctest_instock_childrenoos', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Configurable Product In Stock, Children Out Of Stock. Default Stock');

        $product = $this->getProduct('klevu_configurable_synctest_oos_childreninstock', $storeId);
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Configurable Product Out Of Stock, Children In Stock. Default Stock');

        $product = $this->getProduct('klevu_bundle_synctest_instock_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Bundle Product In Stock, Children In Stock. Default Stock');

        $product = $this->getProduct('klevu_bundle_synctest_instock_childrenoos', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Bundle Product In Stock, Children Out Of Stock. Default Stock');

        $product = $this->getProduct('klevu_bundle_synctest_oos_childreninstock', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], Stock::DEFAULT_STOCK_ID);
        $this->assertFalse($isInStock, 'Bundle Product Out Of Stock, Children In Stock. Default Stock');

        $product = $this->getProduct('klevu_bundle_synctest_instock_childrenoos_notrequired', $storeId);
        $service = $this->GetCompositeProductStockStatusService();
        $isInStock = $service->execute($product, [], $stockId);
        // children in any scope are not required so ignore their stock. Patent is in stock.
        $this->assertTrue($isInStock, 'Bundle Product In Stock, Children Out Of Stock Not Required. Default Stock');

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
     * @return GetCompositeProductStockStatus
     */
    private function GetCompositeProductStockStatusService(array $arguments = []): GetCompositeProductStockStatus
    {
        return $this->objectManager->create(GetCompositeProductStockStatus::class, $arguments);
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
