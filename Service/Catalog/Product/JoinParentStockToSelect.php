<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Service\Catalog\Product;

use Klevu\Search\Api\Service\Catalog\Product\JoinParentEntityToSelectInterface;
use Klevu\Search\Api\Service\Catalog\Product\JoinParentStockToSelectInterface;
use Klevu\Search\Repository\MagentoProductSyncRepository;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

class JoinParentStockToSelect implements JoinParentStockToSelectInterface
{
    public const STOCK_TABLE_ALIAS = 'parent_stock_status_index';

    /**
     * @var JoinParentEntityToSelectInterface
     */
    private $joinParentEntityToSelect;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;
    /**
     * @var StockIndexTableNameResolverInterface
     */
    private $stockIndexTableNameResolver;
    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @param JoinParentEntityToSelectInterface $joinParentEntityToSelect
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     * @param DefaultStockProviderInterface $defaultStockProvider
     */
    public function __construct(
        JoinParentEntityToSelectInterface $joinParentEntityToSelect,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        StockIndexTableNameResolverInterface $stockIndexTableNameResolver,
        DefaultStockProviderInterface $defaultStockProvider
    ) {
        $this->joinParentEntityToSelect = $joinParentEntityToSelect;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver;
        $this->defaultStockProvider = $defaultStockProvider;
    }

    /**
     * @param Select $select
     * @param int $storeId
     * @param bool $includeOosProducts
     * @param bool $returnStock
     * @param bool $joinParentEntity
     *
     * @return Select
     * @throws NoSuchEntityException
     */
    public function execute(
        Select $select,
        $storeId,
        $includeOosProducts = true,
        $returnStock = false,
        $joinParentEntity = true
    ): Select {
        $stockId = $this->getStockId((int)$storeId);
        if (null === $stockId) {
            return $select;
        }
        $isJoinRequired = $this->isJoinRequired($select, $includeOosProducts, $returnStock, $stockId);
        if ($isJoinRequired && $joinParentEntity) {
            $select = $this->joinParentEntityToSelect->execute($select);
        }

        return $isJoinRequired
            ? $this->joinParentStock($select, (int)$stockId, $includeOosProducts, $returnStock)
            : $select;
    }

    /**
     * @param int $storeId
     *
     * @return int|null
     * @throws NoSuchEntityException
     */
    private function getStockId(int $storeId): ?int
    {
        $store = $this->storeManager->getStore($storeId);
        $stockResolver = $this->stockByWebsiteIdResolver->execute(
            (int)$store->getWebsiteId()
        );

        return $stockResolver->getStockId();
    }

    /**
     * @param Select $select
     * @param bool $includeOosProducts
     * @param bool $returnStock
     * @param int $stockId
     *
     * @return bool
     */
    private function isJoinRequired(Select $select, bool $includeOosProducts, bool $returnStock, int $stockId): bool
    {
        $isParentStockJoined = $this->isParentStockJoined($select, $stockId);

        return !$isParentStockJoined && (!$includeOosProducts || $returnStock);
    }

    /**
     * @param Select $select
     * @param int $stockId
     * @param bool $includeOosParents
     * @param bool $returnStock
     *
     * @return Select
     */
    private function joinParentStock(Select $select, int $stockId, bool $includeOosParents, bool $returnStock): Select
    {
        $joinType = $includeOosParents
            ? 'joinLeft'
            : 'joinInner';

        if ($stockId === $this->defaultStockProvider->getId()) {
            $isSalableColumnName = 'stock_status';
            $catInvTableName = $this->resourceConnection->getTableName('cataloginventory_stock_status');
            $columns = $returnStock
                ? ['stock_status' => static::STOCK_TABLE_ALIAS . '.' . $isSalableColumnName]
                : [];
            $select->{$joinType}(
                [static::STOCK_TABLE_ALIAS => $catInvTableName],
                sprintf(
                    '%s.entity_id = %s.product_id',
                    MagentoProductSyncRepository::PARENT_CATALOG_PRODUCT_ENTITY_ALIAS,
                    static::STOCK_TABLE_ALIAS
                ),
                $columns
            );
        } else {
            $isSalableColumnName = IndexStructure::IS_SALABLE;
            $stockIndexTableName = $this->stockIndexTableNameResolver->execute($stockId);
            $columns = $returnStock
                ? ['stock_status' => static::STOCK_TABLE_ALIAS . '.' . $isSalableColumnName]
                : [];
            $select->{$joinType}(
                [static::STOCK_TABLE_ALIAS => $stockIndexTableName],
                sprintf(
                    '%s.sku = %s.%s',
                    MagentoProductSyncRepository::PARENT_CATALOG_PRODUCT_ENTITY_ALIAS,
                    static::STOCK_TABLE_ALIAS,
                    IndexStructure::SKU
                ),
                $columns
            );
        }
        if (!$includeOosParents) {
            $select->where(
                static::STOCK_TABLE_ALIAS . '.' . $isSalableColumnName . ' = ?',
                StockStatusInterface::STATUS_IN_STOCK
            );
        }

        return $select;
    }

    /**
     * @param Select $select
     * @param int $stockId
     *
     * @return bool
     */
    private function isParentStockJoined(Select $select, int $stockId): bool
    {
        try {
            $selectFrom = $select->getPart(Select::FROM);
        } catch (\Zend_Db_Select_Exception $e) {
            return false;
        }
        if ($stockId === $this->defaultStockProvider->getId()) {
            $stockTable = $this->resourceConnection->getTableName('cataloginventory_stock_status');
            $joinCondition =  sprintf(
                '%s.entity_id = %s.product_id',
                MagentoProductSyncRepository::PARENT_CATALOG_PRODUCT_ENTITY_ALIAS,
                static::STOCK_TABLE_ALIAS
            );
        } else {
            $stockTable = $this->stockIndexTableNameResolver->execute($stockId);
            $joinCondition = sprintf(
                '%s.sku = %s.%s',
                MagentoProductSyncRepository::PARENT_CATALOG_PRODUCT_ENTITY_ALIAS,
                static::STOCK_TABLE_ALIAS,
                IndexStructure::SKU
            );
        }

        $matches = array_filter($selectFrom, static function (array $from) use ($stockTable, $joinCondition) {
            return isset($from['tableName'], $from['joinType'], $from['joinCondition'])
                && (false !== strpos($from['tableName'], $stockTable))
                && in_array($from['joinType'], [Select::INNER_JOIN, Select::LEFT_JOIN], true)
                && $from['joinCondition'] === $joinCondition;
        });

        return (bool)count($matches);
    }
}
