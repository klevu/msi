<?php

declare(strict_types=1);

namespace Klevu\Msi\Service\Catalog\Product;

use Klevu\Search\Api\Service\Catalog\Product\GetStockStatusByIdInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class GetStockStatusById implements GetStockStatusByIdInterface
{
    /**
     * @var IsProductSalableInterface
     */
    private $isProductSalable;
    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var StockResolverInterface
     */
    private $stockResolver;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param IsProductSalableInterface $isProductSalable
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        IsProductSalableInterface $isProductSalable,
        GetSkusByProductIdsInterface $getSkusByProductIds,
        StoreManagerInterface $storeManager,
        StockResolverInterface $stockResolver,
        LoggerInterface $logger
    ) {
        $this->isProductSalable = $isProductSalable;
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->storeManager = $storeManager;
        $this->stockResolver = $stockResolver;
        $this->logger = $logger;
    }

    /**
     * @param int[] $productIds
     * @param int|null $websiteId
     *
     * @return array
     */
    public function execute(array $productIds, $websiteId = null): array
    {
        $result = [];
        if (empty($productIds)) {
            return $result;
        }
        try {
            $stockId = $this->getStockId($websiteId);
            $skus = $this->getSkus($productIds);
        } catch (NoSuchEntityException|LocalizedException $exception) {
            $this->logger->error($exception->getMessage());

            return $result;
        }

        foreach ($skus as $sku) {
            $productId = $this->getProductId($sku, $skus);
            if (null === $productId) {
                continue;
            }
            $result[$productId] = (bool)$this->isProductSalable->execute($sku, $stockId);
        }

        return $result;
    }

    /**
     * @param int|null $websiteId
     *
     * @return int
     * @throws NoSuchEntityException|LocalizedException
     */
    private function getStockId(?int $websiteId = null): int
    {
        $website = $this->storeManager->getWebsite($websiteId);
        $stockResolver = $this->stockResolver->execute(
            SalesChannelInterface::TYPE_WEBSITE,
            $website->getCode()
        );

        return $stockResolver->getStockId();
    }

    /**
     * @param int[] $productIds
     *
     * @return string[]
     * @throws NoSuchEntityException
     */
    private function getSkus(array $productIds): array
    {
        return $this->getSkusByProductIds->execute($productIds);
    }

    /**
     * @param string $sku
     * @param string[] $skus
     *
     * @return int|null
     */
    private function getProductId(string $sku, array $skus): ?int
    {
        $productId = array_search($sku, $skus, true);

        return $productId ? (int)$productId: null;
    }
}
