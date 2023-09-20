<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Setup\Patch\Data;

use Klevu\Msi\Model\Indexer\Sync\ProductSyncStockIndexer;
use Magento\Config\Model\ResourceModel\Config as ConfigResourceModel;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SetIndexerToUpdateBySchedule implements DataPatchInterface
{
    /**
     * @var IndexerRegistry
     */
    private $indexerRegistry;
    /**
     * @var ConfigResourceModel
     */
    private $configResourceModel;

    /**
     * @param IndexerRegistry $indexerRegistry
     * @param ConfigResourceModel $configResourceModel
     */
    public function __construct(
        IndexerRegistry $indexerRegistry,
        ConfigResourceModel $configResourceModel
    ) {
        $this->indexerRegistry = $indexerRegistry;
        $this->configResourceModel = $configResourceModel;
    }

    /**
     * @return array|string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array|string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return SetIndexerToUpdateBySchedule|void
     */
    public function apply(): void
    {
        // add commit callback to avoid exception: DDL statements are not allowed in transactions
        $this->configResourceModel->addCommitCallback(function () {
            $indexer = $this->indexerRegistry->get(ProductSyncStockIndexer::INDEXER_ID);
            if (!$indexer->isScheduled()) {
                $indexer->setScheduled(true);
            }
        });
    }
}
