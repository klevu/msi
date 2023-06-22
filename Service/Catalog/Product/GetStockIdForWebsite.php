<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Msi\Service\Catalog\Product;

use Klevu\Search\Api\Service\Catalog\Product\GetStockIdForWebsiteInterface;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class GetStockIdForWebsite implements GetStockIdForWebsiteInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var StockResolverInterface
     */
    private $stockResolver;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        StockResolverInterface $stockResolver,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->stockResolver = $stockResolver;
        $this->logger = $logger;
    }

    /**
     * @param int|null $websiteId
     *
     * @return int
     */
    public function execute($websiteId = null): int
    {
        $return = Stock::DEFAULT_STOCK_ID;
        try {
            $website = $this->storeManager->getWebsite($websiteId);
            $stockStatus = $this->stockResolver->execute(
                SalesChannelInterface::TYPE_WEBSITE,
                $website->getCode()
            );

            $return = (int)$stockStatus->getStockId();
        } catch (LocalizedException $exception) {
            $this->logger->error(
                sprintf(
                    'Method: %s - Error: %s',
                    __METHOD__,
                    $exception->getMessage()
                )
            );
        }

        return $return;
    }
}
