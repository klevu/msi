<?php

declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Store\Model\Website;

$objectManager = Bootstrap::getObjectManager();

/** @var Website $baseWebsite */
$baseWebsite = $objectManager->create(Website::class);
$baseWebsite->load('base', 'code');

/** @var Website $website1 */
$website1 = $objectManager->create(Website::class);
$website1->load('klevu_test_website_1', 'code');

/** @var Website $website2 */
$website2 = $objectManager->create(Website::class);
$website2->load('klevu_test_website_2', 'code');

$fixtures = [
    [
        'sku' => 'klevu_simple_1',
        'name' => '[Klevu] Simple Product 1',
        'description' => '[Klevu Test Fixtures] Simple product 1',
        'short_description' => '[Klevu Test Fixtures] Simple product 1',
        'attribute_set_id' => 4,
        'website_ids' => array_filter([
            $baseWebsite->getId(),
            $website1->getId(),
            $website2->getId(),
        ]),
        'price' => 10,
        'weight' => 1,
        'tax_class_id' => 2,
        'meta_title' => '[Klevu] Simple Product 1',
        'meta_description' => '[Klevu Test Fixtures] Simple product 1',
        'visibility' => Visibility::VISIBILITY_BOTH,
        'status' => Status::STATUS_ENABLED,
        'url_key' => 'klevu_simple_1_' . crc32((string)rand()),
    ],
    [
        'sku' => 'klevu_simple_2',
        'name' => '[Klevu] Simple Product 2',
        'description' => '[Klevu Test Fixtures] Simple product 2',
        'short_description' => '[Klevu Test Fixtures] Simple product 2',
        'attribute_set_id' => 4,
        'website_ids' => array_filter([
            $baseWebsite->getId(),
            $website1->getId(),
            $website2->getId(),
        ]),
        'price' => 25,
        'weight' => 2,
        'tax_class_id' => 2,
        'meta_title' => '[Klevu] Simple Product 2',
        'meta_description' => '[Klevu Test Fixtures] Simple product 2',
        'visibility' => Visibility::VISIBILITY_BOTH,
        'status' => Status::STATUS_ENABLED,
        'url_key' => 'klevu_simple_2_' . crc32((string)rand()),
    ],
    [
        'sku' => 'klevu_simple_3',
        'name' => '[Klevu] Simple Product 3',
        'description' => '[Klevu Test Fixtures] Simple product 3',
        'short_description' => '[Klevu Test Fixtures] Simple product 3',
        'attribute_set_id' => 4,
        'website_ids' => array_filter([
            $baseWebsite->getId(),
            $website1->getId(),
            $website2->getId(),
        ]),
        'price' => 12,
        'weight' => 1.2,
        'tax_class_id' => 2,
        'meta_title' => '[Klevu] Simple Product 2',
        'meta_description' => '[Klevu Test Fixtures] Simple product 3',
        'visibility' => Visibility::VISIBILITY_BOTH,
        'status' => Status::STATUS_ENABLED,
        'url_key' => 'klevu_simple_3_' . crc32((string)rand()),
    ],
    [
        'sku' => 'klevu_simple_4',
        'name' => '[Klevu] Simple Product 4',
        'description' => '[Klevu Test Fixtures] Simple product 4',
        'short_description' => '[Klevu Test Fixtures] Simple product 4',
        'attribute_set_id' => 4,
        'website_ids' => array_filter([
            $baseWebsite->getId(),
            $website1->getId(),
            $website2->getId(),
        ]),
        'price' => 15,
        'weight' => 2,
        'tax_class_id' => 2,
        'meta_title' => '[Klevu] Simple Product 4',
        'meta_description' => '[Klevu Test Fixtures] Simple product 4',
        'visibility' => Visibility::VISIBILITY_BOTH,
        'status' => Status::STATUS_ENABLED,
        'url_key' => 'klevu_simple_4_' . crc32((string)rand()),
    ]
];

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->create(ProductRepositoryInterface::class);

foreach ($fixtures as $fixture) {
    /** @var $product Product */
    $product = $objectManager->create(Product::class);
    $product->isObjectNew(true);
    $product->addData($fixture);

    $productRepository->save($product);
}

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
