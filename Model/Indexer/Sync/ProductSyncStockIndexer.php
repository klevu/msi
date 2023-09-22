<?php

declare(strict_types=1);

namespace Klevu\Msi\Model\Indexer\Sync;

use Klevu\Msi\Api\Service\Catalog\Product\GetProductIdsBySourceItemIdsInterface;
use Klevu\Search\Model\Product\MagentoProductActionsInterface;
use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Framework\Mview\View\StateInterface;
use Psr\Log\LoggerInterface;

class ProductSyncStockIndexer implements ActionInterface, MviewActionInterface
{
    const RECORD_TYPE_PRODUCTS = 'products';
    const INDEXER_ID = 'klevu_product_sync_stock_msi';

    /**
     * @var MagentoProductActionsInterface
     */
    private $magentoProductActions;
    /**
     * @var ChangelogInterface
     */
    private $changelog;
    /**
     * @var StateInterface
     */
    private $state;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var GetProductIdsBySourceItemIdsInterface
     */
    private $productIdsBySourceItemIds;

    /**
     * @param MagentoProductActionsInterface $magentoProductActions
     * @param ChangelogInterface $changelog
     * @param StateInterface $state
     * @param LoggerInterface $logger
     * @param GetProductIdsBySourceItemIdsInterface $productIdsBySourceItemIds
     */
    public function __construct(
        MagentoProductActionsInterface $magentoProductActions,
        ChangelogInterface $changelog,
        StateInterface $state,
        LoggerInterface $logger,
        GetProductIdsBySourceItemIdsInterface $productIdsBySourceItemIds
    ) {
        $this->magentoProductActions = $magentoProductActions;
        $this->changelog = $changelog;
        $this->state = $state;
        $this->logger = $logger;
        $this->productIdsBySourceItemIds = $productIdsBySourceItemIds;
    }

    /**
     * @return void
     */
    public function executeFull(): void
    {
        $ids = $this->getIdsToUpdate();
        $this->executeAction($ids);
    }

    /**
     * @param int[] $ids
     *
     * @return void
     */
    public function executeList(array $ids): void
    {
        $this->executeAction($ids);
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function executeRow($id): void
    {
        $this->executeAction([$id]);
    }

    /**
     * @param int[] $ids
     *
     * @return void
     */
    public function execute($ids): void
    {
        $this->executeAction($ids);
    }

    /**
     * @param int[] $sourceItemIds
     *
     * @return void
     */
    private function executeAction(array $sourceItemIds): void
    {
        $ids = array_filter(
            $this->productIdsBySourceItemIds->execute($sourceItemIds),
            static function ($id) {
                return is_numeric($id) && (int)$id == $id; // intentionally used weak comparison
            }
        );
        if (!$ids) {
            return;
        }

        $this->magentoProductActions->markRecordIntoQueue(
            $ids,
            static::RECORD_TYPE_PRODUCTS,
            null
        );
    }

    /**
     * @return string[]
     */
    private function getIdsToUpdate(): array
    {
        $state = $this->state->loadByView(self::INDEXER_ID);
        $fromVersionId = (int)$state->getVersionId();
        $this->changelog->setViewId(static::INDEXER_ID);
        $toVersionId = (int)$this->changelog->getVersion();

        try {
            $state->setVersionId($toVersionId);
            $state->save();
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Exception thrown in %s: %s',
                    __METHOD__,
                    $exception->getMessage()
                )
            );

            return [];
        }

        return $this->changelog->getList($fromVersionId, $toVersionId);
    }
}
