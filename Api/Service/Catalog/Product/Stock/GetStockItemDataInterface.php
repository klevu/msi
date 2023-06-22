<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Api\Service\Catalog\Product\Stock;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

interface GetStockItemDataInterface
{
    /**
     * Beware when using this service, the data returned is not consistent between default and none default stock.
     * For none default stock bundles are not returned.
     * For none default stock configurable products represent their children only,
     * Configurable product stock status is not taken into account.
     * See \Klevu\Msi\Test\Integration\Catalog\Product\Stock\GetStockItemDataTest
     *
     * @param array<int,string> $productIds
     * @param int $stockId
     *
     * @return string[][]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(array $productIds, int $stockId): array;
}
