<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Msi\Test\Integration\Catalog\Product;

use Klevu\Msi\Service\Catalog\Product\JoinParentStockToSelect;
use Klevu\Search\Api\Service\Catalog\Product\JoinParentStockToSelectInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JoinParentStockToSelectTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testImplements_JoinParentStockToSelectInterface(): void
    {
        $this->assertInstanceOf(
            JoinParentStockToSelectInterface::class,
            $this->instantiateJoinParentStockToSelectService()
        );
    }

    public function testPreference_ForJoinParentStockToSelectInterface(): void
    {
        $this->assertInstanceOf(
            JoinParentStockToSelect::class,
            $this->objectManager->create(JoinParentStockToSelectInterface::class)
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ForExcludeOutOfStock_DefaultStock_ReturnStockFalse(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = false;
        $returnStock = false;
        $stockId = 1;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $originalSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts, $returnStock);

        $updatedSelect = $newSelect->__toString();

        $this->assertNotSame($originalSelect, $updatedSelect);
        $this->commonAssertions($updatedSelect);

        $pattern = $this->isOpenSourceEdition()
            ? '#INNER JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id WHERE \(parent_stock_status_index.stock_status = 1\)#'
            : '#INNER JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id WHERE \(e.created_in <= \d*\) AND \(e.updated_in > \d*\) AND \(parent_stock_status_index.stock_status = 1\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock');

        $pattern = '#SELECT.*`parent_stock_status_index`.`stock_status`.*FROM#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(0, $matches, 'Join Parent Stock Column Not Present');

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ForExcludeOutOfStock_DefaultStock_ReturnStockTrue(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = false;
        $returnStock = true;
        $stockId = 1;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $originalSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts, $returnStock);

        $updatedSelect = $newSelect->__toString();

        $this->assertNotSame($originalSelect, $updatedSelect);
        $this->commonAssertions($updatedSelect);

        $pattern = $this->isOpenSourceEdition()
            ? '#INNER JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id WHERE \(parent_stock_status_index.stock_status = 1\)#'
            : '#INNER JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id WHERE \(e.created_in <= \d*\) AND \(e.updated_in > \d*\) AND \(parent_stock_status_index.stock_status = 1\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock');

        $pattern = '#SELECT.*`parent_stock_status_index`.`stock_status`.*FROM#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock Column ' . $updatedSelect);

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ReturnsSelect_ForExcludeOutOfStock_DefaultStock_ReturnStockTrue_JoinParentEntityFalse(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = false;
        $returnStock = true;
        $joinParent = false;
        $stockId = 1;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $originalSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts, $returnStock, $joinParent);

        $updatedSelect = $newSelect->__toString();

        $this->assertNotSame($originalSelect, $updatedSelect);
        $this->commonAssertions($updatedSelect, 0);

        $pattern = $this->isOpenSourceEdition()
            ? '#INNER JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id WHERE \(parent_stock_status_index.stock_status = 1\)#'
            : '#INNER JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id WHERE \(e.created_in <= \d*\) AND \(e.updated_in > \d*\) AND \(parent_stock_status_index.stock_status = 1\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock');

        $pattern = '#SELECT.*`parent_stock_status_index`.`stock_status`.*FROM#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock Column ' . $updatedSelect);

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ForExcludeOutOfStock_NotDefaultStock_ReturnStockFalse(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = false;
        $stockId = 2;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $originalSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts);

        $updatedSelect = $newSelect->__toString();

        $this->assertNotSame($originalSelect, $updatedSelect);
        $this->commonAssertions($updatedSelect);

        $pattern = $this->isOpenSourceEdition()
            ? '#INNER JOIN `.*inventory_stock_' . $stockId . '` AS `parent_stock_status_index` ON parent.sku = parent_stock_status_index.sku WHERE \(parent_stock_status_index.is_salable = 1\)#'
            : '#INNER JOIN `.*inventory_stock_' . $stockId . '` AS `parent_stock_status_index` ON parent.sku = parent_stock_status_index.sku WHERE \(e.created_in <= \d*\) AND \(e.updated_in > \d*\) AND \(parent_stock_status_index.is_salable = 1\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock');

        $pattern = '#SELECT.*`parent_stock_status_index`.`is_salable` AS `stock_status`.*FROM#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(0, $matches, 'Join Parent Stock Column');

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ForExcludeOutOfStock_NotDefaultStock_ReturnStockTrue(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = false;
        $returnStock = true;
        $stockId = 22;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $originalSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts, $returnStock);

        $updatedSelect = $newSelect->__toString();

        $this->assertNotSame($originalSelect, $updatedSelect);
        $this->commonAssertions($updatedSelect);

        $pattern = $this->isOpenSourceEdition()
            ? '#INNER JOIN `.*inventory_stock_' . $stockId . '` AS `parent_stock_status_index` ON parent.sku = parent_stock_status_index.sku WHERE \(parent_stock_status_index.is_salable = 1\)#'
            : '#INNER JOIN `.*inventory_stock_' . $stockId . '` AS `parent_stock_status_index` ON parent.sku = parent_stock_status_index.sku WHERE \(e.created_in <= \d*\) AND \(e.updated_in > \d*\) AND \(parent_stock_status_index.is_salable = 1\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock');

        $pattern = '#SELECT.*`parent_stock_status_index`.`is_salable` AS `stock_status`.*FROM#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock Column');

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ReturnsSelect_ForExcludeOutOfStock_NotDefaultStockReturnStockTrue_JoinParentEntityFalse(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = false;
        $returnStock = true;
        $joinParent = false;
        $stockId = 22;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $originalSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts, $returnStock, $joinParent);

        $updatedSelect = $newSelect->__toString();

        $this->assertNotSame($originalSelect, $updatedSelect);
        $this->commonAssertions($updatedSelect, 0);

        $pattern = $this->isOpenSourceEdition()
            ? '#INNER JOIN `.*inventory_stock_' . $stockId . '` AS `parent_stock_status_index` ON parent.sku = parent_stock_status_index.sku WHERE \(parent_stock_status_index.is_salable = 1\)#'
            : '#INNER JOIN `.*inventory_stock_' . $stockId . '` AS `parent_stock_status_index` ON parent.sku = parent_stock_status_index.sku WHERE \(e.created_in <= \d*\) AND \(e.updated_in > \d*\) AND \(parent_stock_status_index.is_salable = 1\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock');

        $pattern = '#SELECT.*`parent_stock_status_index`.`is_salable` AS `stock_status`.*FROM#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock Column');

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ForIncludeOutOfStock_DefaultStock_ReturnStockFalse(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = true;
        $returnStock = false;
        $stockId = 1;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $expectedSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts, $returnStock);

        $updatedSelect = $newSelect->__toString();

        $this->assertSame($expectedSelect, $updatedSelect);

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ForIncludeOutOfStock_DefaultStock_ReturnStockTrue(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = true;
        $returnStock = true;
        $stockId = 1;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $expectedSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts, $returnStock);

        $updatedSelect = $newSelect->__toString();

        $this->assertNotSame($expectedSelect, $updatedSelect);
        $this->commonAssertions($updatedSelect);

        $pattern = $this->isOpenSourceEdition()
            ? '#LEFT JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id#'
            : '#LEFT JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id WHERE \(e.created_in <= \d*\) AND \(e.updated_in > \d*\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock');

        $pattern = '#SELECT.*`parent_stock_status_index`.`stock_status`.*FROM#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock Column');

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ForIncludeOutOfStock_DefaultStock_ReturnStockTrue_JoinParentEntityFalse()
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = true;
        $returnStock = true;
        $joinParent = false;
        $stockId = 1;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $expectedSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts, $returnStock, $joinParent);

        $updatedSelect = $newSelect->__toString();

        $this->assertNotSame($expectedSelect, $updatedSelect);
        $this->commonAssertions($updatedSelect, 0);

        $pattern = $this->isOpenSourceEdition()
            ? '#LEFT JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id#'
            : '#LEFT JOIN `.*cataloginventory_stock_status` AS `parent_stock_status_index` ON parent.entity_id = parent_stock_status_index.product_id WHERE \(e.created_in <= \d*\) AND \(e.updated_in > \d*\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock');

        $pattern = '#SELECT.*`parent_stock_status_index`.`stock_status`.*FROM#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount(1, $matches, 'Join Parent Stock Column');

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     */
    public function testExecute_ForIncludeOutOfStock_NotDefaultStock(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $store = $this->getStore('klevu_test_store_1');
        $includeOosProducts = true;
        $stockId = 756;

        $mockStockResolver = $this->getMockStockResolver($stockId, $store);

        $select = $collection->getSelect();
        $originalSelect = $select->__toString();

        $service = $this->instantiateJoinParentStockToSelectService([
            'stockByWebsiteIdResolver' => $mockStockResolver
        ]);
        $newSelect = $service->execute($select, $store->getId(), $includeOosProducts);

        $updatedSelect = $newSelect->__toString();

        $this->assertSame($originalSelect, $updatedSelect);

        static::loadWebsiteFixturesRollback();
    }

    public function testSelect_ParentStockIsNotAddedTwice(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $storeId = 1;
        $select = $collection->getSelect();

        $service = $this->instantiateJoinParentStockToSelectService();
        $select = $service->execute($select, $storeId, true);
        $originalSelect = $select->__toString();

        $newSelect = $service->execute($select, $storeId, true);

        $updatedSelect = $newSelect->__toString();

        $this->assertSame($originalSelect, $updatedSelect);
    }

    /**
     * @param string $updatedSelect
     *
     * @return void
     */
    private function commonAssertions(string $updatedSelect, $count = 1): void
    {
        $pattern = '#INNER JOIN `.*catalog_product_super_link` AS `l` ON e.entity_id = l.product_id#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount($count, $matches, 'Join Super Link');

        $pattern = $this->isOpenSourceEdition()
            ? '#INNER JOIN `.*catalog_product_entity` AS `parent` ON parent.`entity_id` = l.parent_id#'
            : '#INNER JOIN `.*catalog_product_entity` AS `parent` ON parent.`row_id` = l.parent_id AND \(parent.created_in <= \d* AND parent.updated_in > \d*\)#';
        $matches = [];
        preg_match($pattern, $updatedSelect, $matches);
        $this->assertCount($count, $matches, 'Join Parent Entity');
    }

    /**
     * @param mixed[] $arguments
     *
     * @return JoinParentStockToSelect
     */
    private function instantiateJoinParentStockToSelectService(array $arguments = []): JoinParentStockToSelect
    {
        return $this->objectManager->create(
            JoinParentStockToSelect::class,
            $arguments
        );
    }

    /**
     * @return bool
     */
    private function isOpenSourceEdition(): bool
    {
        $productMetadata = $this->objectManager->create(ProductMetadataInterface::class);
        $edition = $productMetadata->getEdition();

        return $edition === ProductMetadata::EDITION_NAME;
    }

    /**
     * @param string $storeCode
     *
     * @return StoreInterface
     * @throws NoSuchEntityException
     */
    private function getStore(string $storeCode): StoreInterface
    {
        $storeRepository = $this->objectManager->create(StoreRepositoryInterface::class);

        return $storeRepository->get($storeCode);
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

    /**
     * @param int $stockId
     * @param StoreInterface $store
     *
     * @return StockByWebsiteIdResolverInterface|(StockByWebsiteIdResolverInterface&MockObject)|MockObject
     */
    private function getMockStockResolver(int $stockId, StoreInterface $store)
    {
        $mockStock = $this->getMockBuilder(StockInterface::class)->getMock();
        $mockStock->expects($this->once())
            ->method('getStockId')
            ->willReturn($stockId);

        $mockStockResolver = $this->getMockBuilder(StockByWebsiteIdResolverInterface::class)->getMock();
        $mockStockResolver->expects($this->once())
            ->method('execute')
            ->with($store->getWebsiteId())
            ->willReturn($mockStock);

        return $mockStockResolver;
    }
}
