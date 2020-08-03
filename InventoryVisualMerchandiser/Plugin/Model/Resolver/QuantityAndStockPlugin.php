<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryVisualMerchandiser\Plugin\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\VisualMerchandiser\Model\Resolver\QuantityAndStock;

/**
 * This plugin adds multi-source stock calculation capabilities to the Visual Merchandiser feature.
 */
class QuantityAndStockPlugin
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
     * @var StockIndexTableNameResolverInterface
     */
    private $stockIndexTableNameResolver;

    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     * @param DefaultStockProviderInterface $defaultStockProvider
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        StockResolverInterface $stockResolver,
        StockIndexTableNameResolverInterface $stockIndexTableNameResolver,
        DefaultStockProviderInterface $defaultStockProvider
    ) {
        $this->storeManager = $storeManager;
        $this->stockResolver = $stockResolver;
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver;
        $this->defaultStockProvider = $defaultStockProvider;
    }

    /**
     * Extend Visual Merchandiser collection with multi-sourcing capabilities.
     *
     * @param QuantityAndStock $subject
     * @param callable $proceed
     * @param Collection $collection
     * @return Collection
     * @throws LocalizedException
     */
    public function aroundJoinStock(QuantityAndStock $subject, callable $proceed, Collection $collection): Collection
    {
        $websiteCode = $this->storeManager->getWebsite()->getCode();
        $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode);
        $stockId = (int)$stock->getStockId();
        if ($stockId === $this->defaultStockProvider->getId()) {
            return $proceed($collection);
        }

        $collection->getSelect()->joinLeft(
            ['inventory_stock' => $this->stockIndexTableNameResolver->execute($stockId)],
            'inventory_stock.sku = e.sku',
            ['stock' => 'quantity']
        );

        return $collection;
    }
}
