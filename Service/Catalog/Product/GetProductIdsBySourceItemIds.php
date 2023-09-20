<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Service\Catalog\Product;

use Klevu\Msi\Api\Service\Catalog\Product\GetProductIdsBySourceItemIdsInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

class GetProductIdsBySourceItemIds implements GetProductIdsBySourceItemIdsInterface
{
    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var string
     */
    private $productTableName;

    /**
     * @param ResourceConnection $resource
     * @param string $productTableName
     */
    public function __construct(
        ResourceConnection $resource,
        string $productTableName
    ) {
        $this->resource = $resource;
        $this->productTableName = $productTableName;
    }

    /**
     * @param int[] $sourceItemIds
     *
     * @return int[]
     */
    public function execute(array $sourceItemIds): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['source_item' => $this->resource->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM)],
            []
        );
        $select->where(
            'source_item.' . SourceItem::ID_FIELD_NAME . ' IN (?)',
            $sourceItemIds
        );
        $select->join(
            ['product' => $this->resource->getTableName($this->productTableName)],
            'source_item.' . SourceItemInterface::SKU . ' = product.sku',
            ['product.entity_id']
        );
        $select->distinct();

        return $connection->fetchCol($select);
    }
}
