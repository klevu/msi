<?php

declare(strict_types=1);

namespace Klevu\Msi\Test\Unit\Catalog\Product;

use Klevu\Msi\Service\Catalog\Product\GetStockStatusById;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GetStockStatusByIdTest extends TestCase
{
    /**
     * @var IsProductSalableInterface|MockObject
     */
    private $mockIsProductSalable;
    /**
     * @var GetSkusByProductIdsInterface|MockObject
     */
    private $mockGetSkusByProductIds;
    /**
     * @var StoreManagerInterface|MockObject
     */
    private $mockStoreManager;
    /**
     * @var StockResolverInterface|MockObject
     */
    private $mockStockResolver;
    /**
     * @var MockObject|LoggerInterface
     */
    private $mockLogger;

    public function testEmptyArrayWhenNoProductIdsAreProvided(): void
    {
        $productIds = [];
        $scopeId = 1;

        $this->mockStoreManager->expects($this->never())->method('getWebsite');

        $getStockStatusById = $this->instantiateGetStockStatusById();
        $stockStatus = $getStockStatusById->execute($productIds, $scopeId);

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(0, $stockStatus);
    }

    public function testEmptyArrayIsReturnedWhenStoreDoesNotExist(): void
    {
        $productIds = [1, 2, 3];
        $scopeId = 1;

        $exception = new LocalizedException(
            __("The website with id %1 that was requested wasn't found. Verify the website and try again.", $scopeId)
        );
        $this->mockStoreManager->expects($this->once())
            ->method('getWebsite')
            ->willThrowException($exception);

        $getStockStatusById = $this->instantiateGetStockStatusById();
        $stockStatus = $getStockStatusById->execute($productIds, $scopeId);

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(0, $stockStatus);
    }

    public function testEmptyArrayIsReturnedWhenStockResolverDoesNotExist(): void
    {
        $productIds = [1, 2, 3];
        $scopeId = 2;
        $websiteCode = 'website_code';

        $exception = new NoSuchEntityException(__('Stock with id "%value" does not exist.', ['value' => $websiteCode]));
        $this->mockStockResolver->expects($this->once())->method('execute')->willThrowException($exception);

        $mockWebsite = $this->getMockBuilder(WebsiteInterface::class)->disableOriginalConstructor()->getMock();
        $mockWebsite->expects($this->once())->method('getCode')->willReturn($websiteCode);

        $this->mockStoreManager->expects($this->once())
            ->method('getWebsite')
            ->willReturn($mockWebsite);

        $getStockStatusById = $this->instantiateGetStockStatusById();
        $stockStatus = $getStockStatusById->execute($productIds, $scopeId);

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(0, $stockStatus);
    }

    public function testEmptyArrayIsReturnedWhenProductIdsDoNotExist(): void
    {
        $productIds = [1, 2, 3];
        $scopeId = 2;
        $websiteCode = 'website_code';
        $stockId = 5;

        $notFoundedIds = [2];
        $exception = new NoSuchEntityException(
            __('Following products with requested ids were not found: %1', implode(', ', $notFoundedIds))
        );
        $this->mockGetSkusByProductIds->expects($this->once())
            ->method('execute')
            ->willThrowException($exception);

        $mockStockInterface = $this->getMockBuilder(StockInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStockInterface->expects($this->once())->method('getStockId')->willReturn($stockId);
        $this->mockStockResolver->expects($this->once())->method('execute')->willReturn($mockStockInterface);

        $mockWebsite = $this->getMockBuilder(WebsiteInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockWebsite->expects($this->once())->method('getCode')->willReturn($websiteCode);

        $this->mockStoreManager->expects($this->once())
            ->method('getWebsite')
            ->willReturn($mockWebsite);

        $getStockStatusById = $this->instantiateGetStockStatusById();
        $stockStatus = $getStockStatusById->execute($productIds, $scopeId);

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(0, $stockStatus);
    }

    public function testReturnsArrayWhenProductsAreSaleable(): void
    {
        $productIds = [1, 2, 3];
        $scopeId = 2;
        $websiteCode = 'website_code';
        $stockId = 5;
        $isSaleableProducts = [
            'sku_1' => true,
            'sku_2' => false,
            'sku_3' => true
        ];

        $this->mockIsProductSalable->expects($this->exactly(3))
            ->method('execute')
            ->willReturnCallback(function ($sku, $stockId) use ($isSaleableProducts) {
                return $isSaleableProducts[$sku];
            });

        $this->mockGetSkusByProductIds->expects($this->once())
            ->method('execute')
            ->willReturn([1 => 'sku_1', 2 => 'sku_2', 3 => 'sku_3']);

        $mockStockInterface = $this->getMockBuilder(StockInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStockInterface->expects($this->once())->method('getStockId')->willReturn($stockId);
        $this->mockStockResolver->expects($this->once())->method('execute')->willReturn($mockStockInterface);

        $mockWebsite = $this->getMockBuilder(WebsiteInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockWebsite->expects($this->once())->method('getCode')->willReturn($websiteCode);

        $this->mockStoreManager->expects($this->once())
            ->method('getWebsite')
            ->willReturn($mockWebsite);

        $getStockStatusById = $this->instantiateGetStockStatusById();
        $stockStatus = $getStockStatusById->execute($productIds, $scopeId);

        if (method_exists($this, 'assertIsArray')) {
            $this->assertIsArray($stockStatus);
        } else {
            $this->assertTrue(is_array($stockStatus), 'Is Array');
        }
        $this->assertCount(3, $stockStatus);

        $this->assertArrayHasKey(1, $stockStatus);
        $this->assertTrue($stockStatus[1]);

        $this->assertArrayHasKey(2, $stockStatus);
        $this->assertFalse($stockStatus[2]);

        $this->assertArrayHasKey(3, $stockStatus);
        $this->assertTrue($stockStatus[3]);
    }

    protected function setUp(): void
    {
        $this->mockIsProductSalable = $this->getMockBuilder(IsProductSalableInterface::class)->getMock();
        $this->mockGetSkusByProductIds = $this->getMockBuilder(GetSkusByProductIdsInterface::class)->getMock();
        $this->mockStoreManager = $this->getMockBuilder(StoreManagerInterface::class)->getMock();
        $this->mockStockResolver = $this->getMockBuilder(StockResolverInterface::class)->getMock();
        $this->mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();
    }

    private function instantiateGetStockStatusById(): GetStockStatusById
    {
        return new GetStockStatusById(
            $this->mockIsProductSalable,
            $this->mockGetSkusByProductIds,
            $this->mockStoreManager,
            $this->mockStockResolver,
            $this->mockLogger
        );
    }
}
