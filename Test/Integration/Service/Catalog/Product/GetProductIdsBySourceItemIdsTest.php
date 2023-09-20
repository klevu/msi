<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Test\Integration\Catalog\Product;

use Klevu\Msi\Api\Service\Catalog\Product\GetProductIdsBySourceItemIdsInterface;
use Klevu\Msi\Service\Catalog\Product\GetProductIdsBySourceItemIds;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Inventory\Model\SourceItem;
use Magento\Inventory\Model\SourceItemRepository;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class GetProductIdsBySourceItemIdsTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public function testImplements_GetStockIdForWebsiteInterface(): void
    {
        $this->assertInstanceOf(
            GetProductIdsBySourceItemIdsInterface::class,
            $this->instantiateGetProductIdsBySourceItemIds()
        );
    }

    public function testPreference_ForGetStockIdForWebsiteInterface(): void
    {
        $this->assertInstanceOf(
            GetProductIdsBySourceItemIds::class,
            $this->objectManager->get(GetProductIdsBySourceItemIdsInterface::class)
        );
    }

    public function testExecute_ReturnsEmptyArray_WhenNoSourceItemIds(): void
    {
        $service = $this->instantiateGetProductIdsBySourceItemIds();
        $this->assertCount(0, $service->execute([]));
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadProductFixtures
     * @magentoDataFixture loadInventoryFixtures
     */
    public function testExecute_ReturnsProductId_ForSourceItemId(): void
    {
        $product1 = $this->getProduct('klevu_simple_1');
        $product2 = $this->getProduct('klevu_simple_2');
        $product3 = $this->getProduct('klevu_simple_3');
        $product4 = $this->getProduct('klevu_simple_4');

        $searchCriteria = $this->objectManager->get(SearchCriteriaInterface::class);
        $sourceItemRepository = $this->objectManager->get(SourceItemRepository::class);
        $sourceItems = $sourceItemRepository->getList($searchCriteria);
        $sourceItemIds = array_map(static function (SourceItem $sourceItem): int {
            return (int)$sourceItem->getId();
        }, $sourceItems->getItems());

        $service = $this->instantiateGetProductIdsBySourceItemIds();
        $productIds = $service->execute($sourceItemIds);

        $this->assertContains($product1->getId(), $productIds);
        $this->assertContains($product2->getId(), $productIds);
        $this->assertContains($product3->getId(), $productIds);
        $this->assertContains($product4->getId(), $productIds);

        static::loadInventoryFixturesRollback();
        static::loadProductFixturesRollback();
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
     * @return GetProductIdsBySourceItemIds
     */
    private function instantiateGetProductIdsBySourceItemIds(array $arguments = []): GetProductIdsBySourceItemIds
    {
        return $this->objectManager->create(GetProductIdsBySourceItemIds::class, $arguments);
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
