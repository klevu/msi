<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Service\Catalog\Product\Stock;

use Klevu\Msi\Api\Service\Catalog\Product\Stock\GetStockItemDataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface as MsiGetStockItemDataInterface;

class GetStockItemData implements GetStockItemDataInterface
{
    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;
    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;
    /**
     * @var StockIndexTableNameResolverInterface
     */
    private $stockIndexTableNameResolver;
    /**
     * @var array<int,string>
     */
    private $skus;

    /**
     * @param ResourceConnection $resource
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     */
    public function __construct(
        ResourceConnection $resource,
        DefaultStockProviderInterface $defaultStockProvider,
        GetSkusByProductIdsInterface $getSkusByProductIds,
        StockIndexTableNameResolverInterface $stockIndexTableNameResolver
    ) {
        $this->resource = $resource;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver;
    }

    /**
     * @inheritdoc
     *
     * @param array<int,string> $productIds
     * @param int $stockId
     *
     * @return string[][]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(array $productIds, int $stockId): array
    {
        // rework of \Magento\InventoryIndexer\Model\ResourceModel\GetStockItemData::execute,
        // so we can pass multiple ids for bundle sections/children.
        $connection = $this->resource->getConnection();
        $select = $connection->select();

        if ($this->defaultStockProvider->getId() === $stockId) {
            $stockItemTableName = $this->resource->getTableName('cataloginventory_stock_status');
            $select->from(
                $stockItemTableName,
                [
                    MsiGetStockItemDataInterface::QUANTITY => 'qty',
                    MsiGetStockItemDataInterface::IS_SALABLE => 'stock_status',
                    'product_id' => 'product_id',
                ]
            );
            $select->where('product_id IN (?)', $productIds);
        } else {
            $this->skus = $this->getSkusByProductIds->execute($productIds);
            $stockItemTableName = $this->stockIndexTableNameResolver->execute($stockId);
            $select->from(
                $stockItemTableName,
                [
                    MsiGetStockItemDataInterface::QUANTITY => IndexStructure::QUANTITY,
                    MsiGetStockItemDataInterface::IS_SALABLE => IndexStructure::IS_SALABLE,
                    MsiGetStockItemDataInterface::SKU => 'sku',
                ]
            );
            $select->where(IndexStructure::SKU . ' IN (?)', $this->skus);
        }

        try {
            $stockItems = $connection->fetchAll($select);

            return $this->mapResult($stockItems);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Could not retrieve Stock Item data'), $e);
        }
    }

    /**
     * Set product id as the array key
     *
     * @param string[][] $stockItems
     *
     * @return string[][]
     */
    private function mapResult(array $stockItems): array
    {
        $stockData = [];
        foreach ($stockItems as $stockItem) {
            $productId = null;
            if (isset($stockItem['product_id'])) {
                $productId = $stockItem['product_id'];
                unset($stockItem['product_id']);
            } elseif (isset($stockItem['sku'])) {
                $currentSku = $stockItem['sku'] ?? null;
                $productSkuMap = array_filter($this->skus, static function (string $sku) use ($currentSku) {
                    return $currentSku === $sku;
                });
                [$productId] = array_keys($productSkuMap);
                unset($stockItem['sku']);
            }
            if ($productId) {
                $stockData[$productId] = $stockItem;
            }
        }

        return $stockData;
    }
}
