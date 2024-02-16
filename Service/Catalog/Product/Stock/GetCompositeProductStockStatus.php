<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Service\Catalog\Product\Stock;

use Klevu\Msi\Api\Service\Catalog\Product\Stock\GetGlobalStockStatusInterface;
use Klevu\Msi\Api\Service\Catalog\Product\Stock\GetStockItemDataInterface;
use Klevu\Search\Api\Service\Catalog\Product\Stock\GetCompositeProductStockStatusInterface;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface as MsiGetStockItemDataInterface;
use Psr\Log\LoggerInterface;

class GetCompositeProductStockStatus implements GetCompositeProductStockStatusInterface
{
    /**
     * @var GetStockItemDataInterface
     */
    private $getStockItemData;
    /**
     * @var GetGlobalStockStatusInterface
     */
    private $getGlobalStockStatus;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param GetStockItemDataInterface $getStockItemData
     * @param GetGlobalStockStatusInterface $getGlobalStockStatus
     * @param LoggerInterface $logger
     */
    public function __construct(
        GetStockItemDataInterface $getStockItemData,
        GetGlobalStockStatusInterface $getGlobalStockStatus,
        LoggerInterface $logger
    ) {
        $this->getStockItemData = $getStockItemData;
        $this->getGlobalStockStatus = $getGlobalStockStatus;
        $this->logger = $logger;
    }

    /**
     * @param ProductInterface $product
     * @param array $bundleOptions
     * @param int|null $stockId
     *
     * @return bool
     */
    public function execute(ProductInterface $product, array $bundleOptions, $stockId): bool
    {
        try {
            $this->validateProductType($product);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error(
                sprintf(
                    'Method: %s - Error %s',
                    __METHOD__,
                    $exception->getMessage()
                )
            );

            return false;
        }
        if (!$product->getId() || !$this->isProductInStock($product)) {
            return false;
        }
        switch ($product->getTypeId()) {
            case ConfigurableType::TYPE_CODE:
                $return = $this->isConfigurableProductInStock($product, (int)$stockId);
                break;
            case BundleType::TYPE_CODE:
                $return = $this->isBundleProductInStock($product, (int)$stockId);
                break;
            default:
                $return = false;
                break;
        }

        return $return;
    }

    /**
     * @param ProductInterface $product
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateProductType(ProductInterface $product): void
    {
        if (in_array($product->getTypeId(), [ConfigurableType::TYPE_CODE, BundleType::TYPE_CODE], true)) {
            return;
        }
        throw new \InvalidArgumentException(
            sprintf(
                'Incorrect product type must be either configurable or bundle; %s provided',
                $product->getTypeId()
            )
        );
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    private function isProductInStock(ProductInterface $product): bool
    {
        // bundle and configurable products stock status is still global with MSI,
        // check that first before checking entries in `inventory_stock_x` which doesn't take this into account
        $extensionAttributes = $product->getExtensionAttributes();
        /** @var StockItemInterface $stockItem */
        $stockItem = $extensionAttributes
            ? $extensionAttributes->getStockItem()
            : null;

        if (null === $stockItem) {
            $stockItems = $this->getGlobalStockStatus->execute([(int)$product->getId()]);
            $stockItem = array_shift($stockItems);
        }

        // assume true if stock item can not be checked
        return !$stockItem || $stockItem->getIsInStock();
    }

    /**
     * @param ProductInterface $product
     * @param int $stockId
     *
     * @return bool
     */
    private function isConfigurableProductInStock(ProductInterface $product, int $stockId): bool
    {
        /**
         * Prior to Magento 2.4.6 (MSI 1.2.6)
         * Configurable products have a single entry in `inventory_stock_x`,
         * which takes into account only their children
         *
         * 1.2.6 release:
         * https://experienceleague.adobe.com/docs/commerce-admin/inventory/release-notes.html?lang=en#v1.2.6
         *
         * @TODO optimise this for Magento 2.4.6 and above
         */
        $return = false;
        try {
            $stockItemsData = $this->getStockItemData->execute([$product->getId()], $stockId);
            $stockItemData = array_shift($stockItemsData);

            $return = !empty($stockItemData[MsiGetStockItemDataInterface::IS_SALABLE]);
        } catch (LocalizedException $exception) {
            $this->logger->error(
                sprintf(
                    'Method: %s - Error %s',
                    __METHOD__,
                    $exception->getMessage()
                )
            );
        }

        return $return;
    }

    /**
     * @param ProductInterface $product
     * @param int $stockId
     *
     * @return bool
     */
    private function isBundleProductInStock(ProductInterface $product, int $stockId): bool
    {
        /**
         * Prior to Magento 2.4.6 (MSI 1.2.6)
         * Bundle products are not present in `inventory_stock_x` like configurable.
         * However, their children are present.
         *
         * 1.2.6 release:
         * https://experienceleague.adobe.com/docs/commerce-admin/inventory/release-notes.html?lang=en#v1.2.6
         *
         * @TODO optimise this for Magento 2.4.6 and above
         */
        $return = false; // bundle product must have at least one child.
        try {
            /** @var BundleType $typeInstance */
            $typeInstance = $product->getTypeInstance();
            $childrenByGroup = $typeInstance->getChildrenIds($product->getId(), true);
            foreach ($childrenByGroup as $groupId => $productIds) {
                if (0 === $groupId) {
                    // $groupId 0 contains all none required sections/groups merged together.
                    // don't check stock for products in none required sections/groups
                    $return = true;
                    continue;
                }
                $stockItemsData = $this->getStockItemData->execute($productIds, $stockId);
                $return = $this->hasStockDataForAllItems($productIds, $stockItemsData)
                    && $this->hasInStockItems($stockItemsData);

                if (!$return) {
                    break;
                }
            }
        } catch (LocalizedException $exception) {
            $this->logger->error(
                sprintf(
                    'Method: %s - Error %s',
                    __METHOD__,
                    $exception->getMessage()
                )
            );
        }

        return $return;
    }

    /**
     * @param string[] $productIds
     * @param string[][] $stockItemsData
     *
     * @return bool
     */
    private function hasStockDataForAllItems(array $productIds, array $stockItemsData): bool
    {
        $productCount = count($productIds);

        return $productCount && ($productCount === count($stockItemsData));
    }

    /**
     * @param string[][] $stockItemsData
     *
     * @return bool
     */
    private function hasInStockItems(array $stockItemsData): bool
    {
        $inStockItems = array_filter($stockItemsData, static function (array $stockItemData): bool {
            return !empty($stockItemData[MsiGetStockItemDataInterface::IS_SALABLE]);
        });

        return (bool)count($inStockItems);
    }
}
