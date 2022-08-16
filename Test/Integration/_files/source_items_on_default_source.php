<?php

declare(strict_types=1);

use Magento\Framework\Api\DataObjectHelper;
use Magento\Indexer\Model\IndexerFactory;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);
/** @var SourceItemInterfaceFactory $sourceItemFactory */
$sourceItemFactory = $objectManager->get(SourceItemInterfaceFactory::class);
/** @var  SourceItemsSaveInterface $sourceItemsSave */
$sourceItemsSave = $objectManager->get(SourceItemsSaveInterface::class);
/** @var DefaultSourceProviderInterface $defaultSourceProvider */
$defaultSourceProvider = $objectManager->get(DefaultSourceProviderInterface::class);

/**
 * klevu_simple_1 - Default-source - 5 qty (in stock)
 * klevu_simple_2 - Default-source - 6 qty (out of stock)
 * klevu_simple_3 - Default-source - 0 qty (out of stock)
 * klevu_simple_4 - Default-source - 0 qty (in stock)
 */
$sourcesItemsData = [
    [
        SourceItemInterface::SOURCE_CODE => $defaultSourceProvider->getCode(),
        SourceItemInterface::SKU => 'klevu_simple_1',
        SourceItemInterface::QUANTITY => 5,
        SourceItemInterface::STATUS => SourceItemInterface::STATUS_IN_STOCK,
    ],
    [
        SourceItemInterface::SOURCE_CODE => $defaultSourceProvider->getCode(),
        SourceItemInterface::SKU => 'klevu_simple_2',
        SourceItemInterface::QUANTITY => 6,
        SourceItemInterface::STATUS => SourceItemInterface::STATUS_OUT_OF_STOCK,
    ],
    [
        SourceItemInterface::SOURCE_CODE => $defaultSourceProvider->getCode(),
        SourceItemInterface::SKU => 'klevu_simple_3',
        SourceItemInterface::QUANTITY => 0,
        SourceItemInterface::STATUS => SourceItemInterface::STATUS_OUT_OF_STOCK,
    ],
    [
        SourceItemInterface::SOURCE_CODE => $defaultSourceProvider->getCode(),
        SourceItemInterface::SKU => 'klevu_simple_4',
        SourceItemInterface::QUANTITY => 0,
        SourceItemInterface::STATUS => SourceItemInterface::STATUS_IN_STOCK,
    ],
];

$sourceItems = [];
foreach ($sourcesItemsData as $sourceItemData) {
    /** @var SourceItemInterface $source */
    $sourceItem = $sourceItemFactory->create();
    $dataObjectHelper->populateWithArray($sourceItem, $sourceItemData, SourceItemInterface::class);
    $sourceItems[] = $sourceItem;
}
$sourceItemsSave->execute($sourceItems);

$indexerFactory = $objectManager->get(IndexerFactory::class);
$indexes = [
    'catalog_product_attribute',
    'catalog_product_price',
    'inventory',
    'cataloginventory_stock',
];
foreach ($indexes as $index) {
    $indexer = $indexerFactory->create();
    try {
        $indexer->load($index);
        $indexer->reindexAll();
    } catch (\InvalidArgumentException $e) {
        // Support for older versions of Magento which may not have all indexers
        continue;
    }
}
