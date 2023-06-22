<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Service\Catalog\Product\Stock;

use Klevu\Msi\Api\Service\Catalog\Product\Stock\GetGlobalStockStatusInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;

class GetGlobalStockStatus implements GetGlobalStockStatusInterface
{
    /**
     * @var StockItemRepositoryInterface
     */
    private $stockItemRepository;
    /**
     * @var StockItemCriteriaInterfaceFactory
     */
    private $stockItemCriteriaFactory;

    /**
     * @param StockItemRepositoryInterface $stockItemRepository
     * @param StockItemCriteriaInterfaceFactory $stockItemCriteriaFactory
     */
    public function __construct(
        StockItemRepositoryInterface $stockItemRepository,
        StockItemCriteriaInterfaceFactory $stockItemCriteriaFactory
    ) {
        $this->stockItemRepository = $stockItemRepository;
        $this->stockItemCriteriaFactory = $stockItemCriteriaFactory;
    }

    /**
     * @param int[] $productIds
     *
     * @return StockItemInterface[]
     */
    public function execute(array $productIds): array
    {
        /** @var StockItemCriteriaInterface $stockItemCriteria */
        $stockItemCriteria = $this->stockItemCriteriaFactory->create();
        $stockItemCriteria->setProductsFilter($productIds);
        $stockList = $this->stockItemRepository->getList($stockItemCriteria);

        return $stockList->getItems();
    }
}
