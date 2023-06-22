<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

use Magento\Framework\Api\DataObjectHelper;
use Magento\Inventory\Model\Source as InventorySource;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceInterfaceFactory;
use Magento\InventoryApi\Api\Data\StockExtension;
use Magento\InventoryApi\Api\Data\StockInterfaceFactory;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryApi\Api\StockSourceLinksSaveInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;

require __DIR__ . '/stockFixtures_rollback.php';

$objectManager = Bootstrap::getObjectManager();

// Create Stock and Assign to Website
$storeRepository = $objectManager->get(StoreRepositoryInterface::class);
$store = $storeRepository->get('klevu_test_store_1');
$website = $store->getWebsite();

$salesChannel1 = $objectManager->get(SalesChannelInterface::class);
$salesChannel1->setCode($website->getCode());
$salesChannel1->setType(SalesChannelInterface::TYPE_WEBSITE);

$salesChannels = [
    $salesChannel1
];

$stockExtension = $objectManager->get(StockExtension::class);
$stockExtension->setSalesChannels($salesChannels);

$stockFactory = $objectManager->create(StockInterfaceFactory::class);
$stock = $stockFactory->create();
$stock->setData([
    'name' => 'klevu_test_stock_1'
]);
$stock->setExtensionAttributes($stockExtension);
$stockRepository = $objectManager->create(StockRepositoryInterface::class);
$stockRepository->save($stock);

// Create Source
$sourceFactory = $objectManager->create(SourceInterfaceFactory::class);
$source = $sourceFactory->create();
$source->setData([
    SourceInterface::SOURCE_CODE => 'klevu_test_source_1',
    SourceInterface::NAME => 'Klevu Test Source 1',
    SourceInterface::DESCRIPTION => 'Klevu Test Source 1',
    SourceInterface::LONGITUDE => '0.000000',
    SourceInterface::LATITUDE => '0.000000',
    SourceInterface::COUNTRY_ID => 'UK',
    SourceInterface::POSTCODE => 'SW1A 0AA',
]);
$sourceRepository = $objectManager->create(SourceRepositoryInterface::class);
$sourceRepository->save($source);
/** @var InventorySource $source */
$source = $sourceRepository->get('klevu_test_source_1');

// Link Source to Stock
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);
/** @var StockSourceLinksSaveInterface $stockSourceLinksSave */
$stockSourceLinksSave = $objectManager->get(StockSourceLinksSaveInterface::class);
/** @var StockSourceLinkInterfaceFactory $stockSourceLinkFactory */
$stockSourceLinkFactory = $objectManager->get(StockSourceLinkInterfaceFactory::class);

$linksData = [
    [
        StockSourceLinkInterface::STOCK_ID => $stock->getStockId(),
        StockSourceLinkInterface::SOURCE_CODE => $source->getId(),
        StockSourceLinkInterface::PRIORITY => 1,
    ],
];

$links = [];
foreach ($linksData as $linkData) {
    /** @var StockSourceLinkInterface $link */
    $link = $stockSourceLinkFactory->create();
    $dataObjectHelper->populateWithArray($link, $linkData, StockSourceLinkInterface::class);
    $links[] = $link;
}
$stockSourceLinksSave->execute($links);
