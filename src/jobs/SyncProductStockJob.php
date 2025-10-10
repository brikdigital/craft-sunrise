<?php

namespace brikdigital\sunrise\jobs;

use brikdigital\sunrise\services\ProductService;
use brikdigital\sunrise\services\SunriseService;
use brikdigital\sunrise\Sunrise;
use Craft;
use craft\base\Batchable;
use craft\db\QueryBatcher;
use craft\queue\BaseBatchedJob;

class SyncProductStockJob extends BaseBatchedJob
{
    public int $batchSize = 20;
    public SunriseService $api;
    public ProductService $service;

    public function __construct($config = [])
    {
        $plugin = Sunrise::getInstance();
        $this->api = $plugin->api;
        $this->service = $plugin->product;

        parent::__construct($config);
    }

    protected function defaultDescription(): ?string
    {
        return 'Synchronising product stock';
    }

    protected function loadData(): Batchable
    {
        return new QueryBatcher($this->service->getProductQuery());
    }

    protected function processItem(mixed $item): void
    {
        $sunriseId = $item->sunriseForeignId;
        if (!$sunriseId) {
            return;
        }

        $warehouseProducts = $this->api->getAll('warehouse_products/search', [
            'product_id' => $sunriseId,
        ]);

        $stocks = [];
        foreach ($warehouseProducts as $warehouseProduct) {
            $sku = $warehouseProduct['product_extension'] ?? null;
            if (!$sku) {
                continue;
            }

            $stocks[$sku] = ($stocks[$sku] ?? 0) + (int)$warehouseProduct['available_stock'];
        }

        foreach ($stocks as $sku => $stock) {
            $variant = $this->service->getVariant($item, $sku);
            if (!$variant) {
                continue;
            }

            $variant->hasUnlimitedStock = false;
            $variant->stock = $stock;

            Craft::$app->getElements()->saveElement($variant);
        }
    }
}
