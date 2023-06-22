<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Inventory\Model\ResourceModel\Source as SourceResourceModel;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\Data\StockSearchResultsInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryApi\Api\StockSourceLinksDeleteInterface;
use Magento\InventoryApi\Api\StockSourceLinksSaveInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();


/** @var StockRepositoryInterface $stockRepository */
$stockRepository = $objectManager->create(StockRepositoryInterface::class);
/** @var SourceRepositoryInterface $sourceRepository */
$sourceRepository = $objectManager->create(SourceRepositoryInterface::class);

try {
    // UnLink Source to Stock
    /** @var DataObjectHelper $dataObjectHelper */
    $dataObjectHelper = $objectManager->get(DataObjectHelper::class);
    /** @var StockSourceLinksSaveInterface $stockSourceLinksSave */
    $stockSourceLinksSave = $objectManager->get(StockSourceLinksSaveInterface::class);
    /** @var StockSourceLinkInterfaceFactory $stockSourceLinkFactory */
    $stockSourceLinkFactory = $objectManager->get(StockSourceLinkInterfaceFactory::class);
    $filter = $objectManager->create(Filter::class);
    $filter->setField('name');
    $filter->setValue('klevu_test_stock_1');
    $filter->setConditionType('eq');
    $searchItemBuilderFactory = $objectManager->create(SearchCriteriaBuilderFactory::class);
    $searchItemBuilder = $searchItemBuilderFactory->create();
    $searchItemBuilder->addFilter($filter);
    $searchItemCriteria = $searchItemBuilder->create();
    $stockRepository = $objectManager->create(StockRepositoryInterface::class);
    $stockSearchResults = $stockRepository->getList($searchItemCriteria);
    $stockItems = $stockSearchResults->getItems();
    if ($stockItems) {
        $stock = array_shift($stockItems);

        $source = $sourceRepository->get('klevu_test_source_1');

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

        $stockSourceLinksDelete = $objectManager->create(StockSourceLinksDeleteInterface::class);
        $stockSourceLinksDelete->execute($links);
    }
} catch (\Exception $e) {
    // this is fine
}

// Rollback Source
$sourceCodesToDelete = [
    'klevu_test_source_1'
];
$sourceResourceModel = $objectManager->create(SourceResourceModel::class);

foreach($sourceCodesToDelete as $sourceCode) {
    try {
        $source = $sourceRepository->get($sourceCode);
        $sourceResourceModel->delete($source); // repository doesn't have a delete method
    } catch (NoSuchEntityException $e) {
        // this is fine source does not exist
    }
}

// Rollback Stock
$stockCodesToDelete = [
    'klevu_test_stock_1'
];

/** @var StockRepositoryInterface $stockRepository */
$filter = $objectManager->create(Filter::class);
$filter->setField('name');
$filter->setValue($stockCodesToDelete);
$filter->setConditionType('in');

$searchItemBuilderFactory = $objectManager->create(SearchCriteriaBuilderFactory::class);
$searchItemBuilder = $searchItemBuilderFactory->create();
$searchItemBuilder->addFilter($filter);
$searchItemCriteria = $searchItemBuilder->create();

/** @var StockSearchResultsInterface $stockSearchResults */
$stockSearchResults = $stockRepository->getList($searchItemCriteria);
/** @var StockInterface[] $stockItems */
$stockItems = $stockSearchResults->getItems();

foreach ($stockItems as $stock) {
    try {
        $stockRepository->deleteById($stock->getStockId());
    } catch (\Exception $e) {
        // this is fine, stock has already been removed
    }
}
