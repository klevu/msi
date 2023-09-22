<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Api\Service\Catalog\Product;

interface GetProductIdsBySourceItemIdsInterface
{
    /**
     * @param int[] $sourceItemIds
     *
     * @return int[]
     */
    public function execute(array $sourceItemIds): array;
}
