<?php

namespace brikdigital\sunrise\jobs;

use brikdigital\sunrise\Sunrise;
use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\ElementCollection;
use craft\helpers\Queue;
use craft\queue\BaseJob;

/**
 * Sync Products queue job
 */
class SyncProductsJob extends BaseJob
{
    public int $offset = 0;

    private int $limit = 20;

    protected function defaultDescription(): ?string
    {
        $total = $this->offset + $this->limit;
        return "Synchronising products ($this->offset-$total)";
    }

    public function execute($queue): void
    {
        /**
         * Check for products
         */
        $plugin = Sunrise::getInstance();
        $api = $plugin->api;
        $products = $api->get('product', [
            'limit' => $this->limit,
            'offset' => $this->offset,
        ]);

        $count = count($products);
        if ($count <= 0) {
            return;
        }

        $total = $this->offset + $count;
        Sunrise::info("STARTING PRODUCTS SYNC ($this->offset-$total)");

        /**
         * Process products
         */
        $productGroupService = $plugin->productGroup;
        $productService = $plugin->product;
        $productTypeId = $productService->getProductType()->id;

        foreach ($products as $i => $sunriseProduct) {
            $this->setProgress(
                $queue,
                $i / $count,
                Craft::t('app', '{step, number} of {total, number}', [
                    'step' => $i + 1,
                    'total' => $count,
                ])
            );

            $product = Product::find()
                ->sunriseForeignId($sunriseProduct['product_id'])
                ->typeId($productTypeId)
                ->one();

            if (!$product) {
                $product = new Product([
                    'typeId' => $productTypeId,
                    'sunriseForeignId' => $sunriseProduct['product_id'],
                ]);
            }

            $productDetails = $api->get('productdetails/search', [
                'product_id' => $product->sunriseForeignId,
            ])[0] ?? [];

            // Product groups
            $productGroups = new ElementCollection();
            foreach ($productDetails['categories'] as $category) {
                $productGroup = $category['type_id']
                    ? $productGroupService->getProductGroupByForeignId(
                        $category['type_id'],
                        $category['cat_id'] ?: null
                    )
                    : null;

                if ($productGroup) {
                    $productGroups->push($productGroup);
                }
            }

            // Variants
            $productSkus = $api->get('productsku', [
                'productId' => $product->sunriseForeignId,
            ])[0]['ProductSkuList'] ?? [];

            // If no SKU's, main product can be the only variant sold
            if (empty($productSkus) && !empty((int)$sunriseProduct['price_prod'])) {
                $productSkus[] = [
                    'sku_id' => $product->sunriseForeignId,
                    'assigned_attribute_options' => $sunriseProduct['product_title'],
                    'sku_price_excl_vat' => $sunriseProduct['price_prod'],
                    'visible_in_webshop' => $sunriseProduct['status_pro'] === 'ACT',
                    'sku_status' => $sunriseProduct['status_pro'] === 'ACT',
                ];
            }

            $variants = [];
            foreach ($productSkus as $sku) {
                $skuId = $sku['sku_id'];

                $variant = $product->id ? $productService->getVariant($product, $skuId) : null;
                if (!$variant) {
                    $variant = new Variant();
                    $variant->setSku($skuId);
                }

                $variant->title = $sku['assigned_attribute_options'];
                $variant->price = $sku['sku_price_excl_vat'];

                // Values are booleans in form of string
                $variant->enabled = !empty(array_filter(filter_var([
                    $sku['visible_in_webshop'],
                    $sku['sku_status']
                ], FILTER_VALIDATE_BOOL, FILTER_REQUIRE_ARRAY)));

                $variants[] = $variant;
            }

            $product->title = $sunriseProduct['product_title'];
            $product->sunriseProductGroups = $productGroups;
            $product->setVariants($variants);

            Craft::$app->getElements()->saveElement($product);
        }

        Sunrise::info("FINISHED PRODUCTS SYNC ($this->offset-$total)");

        Queue::push(new SyncProductsJob([
            'offset' => $total,
        ]));
    }
}
