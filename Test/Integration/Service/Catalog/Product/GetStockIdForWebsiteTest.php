<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Msi\Test\Integration\Catalog\Product;

use Klevu\Msi\Service\Catalog\Product\GetStockIdForWebsite;
use Klevu\Search\Api\Service\Catalog\Product\GetStockIdForWebsiteInterface;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class GetStockIdForWebsiteTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public function testImplements_GetStockIdForWebsiteInterface(): void
    {
        $this->assertInstanceOf(
            GetStockIdForWebsiteInterface::class,
            $this->instantiateGetStockIdForWebsite()
        );
    }

    public function testPreference_ForGetStockIdForWebsiteInterface(): void
    {
        $this->assertInstanceOf(
            GetStockIdForWebsite::class,
            $this->objectManager->get(GetStockIdForWebsiteInterface::class)
        );
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadStockFixtures
     */
    public function testExecute_ReturnsStockId_ForWebsite(): void
    {
        $website = $this->getWebsite('klevu_test_website_1');
        $stock = $this->getStock('klevu_test_stock_1');
        $expectedStockId = $stock->getStockId();

        $service = $this->instantiateGetStockIdForWebsite();
        $actualStockId = $service->execute((int)$website->getId());

        $this->assertSame($expectedStockId, $actualStockId);

        static::loadWebsiteFixturesRollback();
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadWebsiteFixtures
     * @magentoDataFixture loadStockFixtures
     */
    public function testExecute_ReturnsDefaultStockId_ForMissingWebsite(): void
    {
        $service = $this->instantiateGetStockIdForWebsite();
        $actualStockId = $service->execute();

        $this->assertSame(Stock::DEFAULT_STOCK_ID, $actualStockId);

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
     * @return GetStockIdForWebsite
     */
    private function instantiateGetStockIdForWebsite(array $arguments = []): GetStockIdForWebsite
    {
        return $this->objectManager->create(GetStockIdForWebsite::class, $arguments);
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
     * @param string $websiteCode
     *
     * @return WebsiteInterface
     * @throws NoSuchEntityException
     */
    private function getWebsite(string $websiteCode): WebsiteInterface
    {
        /** @var WebsiteRepositoryInterface $websiteRepository */
        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);

        return $websiteRepository->get($websiteCode);
    }

    /**
     * Loads website stock creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadStockFixtures(): void
    {
        include __DIR__ . '/../../../_files/stockFixtures.php';
    }

    /**
     * Rolls back website stock creation scripts because annotations use a relative path
     *  from integration tests root
     */
    public static function loadStockFixturesRollback(): void
    {
        include __DIR__ . '/../../../_files/stockFixtures_rollback.php';
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
