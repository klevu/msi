<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Api\Service\Catalog\Product\Stock;

use Magento\CatalogInventory\Api\Data\StockItemInterface;

interface GetGlobalStockStatusInterface
{
    /**
     * @param int[] $productIds
     *
     * @return StockItemInterface[]
     */
    public function execute(array $productIds): array;
}
