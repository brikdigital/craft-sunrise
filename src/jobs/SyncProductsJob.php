<?php

namespace brikdigital\sunrise\jobs;

use brikdigital\sunrise\services\AttributeService;
use brikdigital\sunrise\services\ProductGroupService;
use brikdigital\sunrise\services\ProductService;
use brikdigital\sunrise\services\SunriseService;
use brikdigital\sunrise\Sunrise;
use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\elements\ElementCollection;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use Exception;

/**
 * Sync Products queue job
 */
class SyncProductsJob extends BaseJob
{
    public int $offset = 0;
    public array $processedProductIds = [];

    private SunriseService $api;
    private ProductService $productService;
    private ProductGroupService $productGroupService;
    private AttributeService $attributeService;
    private int $productTypeId;
    private int $limit = 20;

    public function __construct($config = [])
    {
        $plugin = Sunrise::getInstance();

        $this->api = $plugin->api;
        $this->productService = $plugin->product;
        $this->productGroupService = $plugin->productGroup;
        $this->attributeService = $plugin->attribute;
        $this->productTypeId = $this->productService->getProductType()->id;

        parent::__construct($config);
    }

    protected function defaultDescription(): ?string
    {
        $total = $this->offset + $this->limit;
        return "Synchronising products ($this->offset-$total)";
    }

    public function execute($queue): void
    {
        // Get products
        $products = $this->api->get('product', [
            'limit' => $this->limit,
            'offset' => $this->offset,
        ]);

        $count = count($products);

        // No more products to process
        if ($count <= 0) {
            $this->deleteUnprocessedProducts($this->processedProductIds);
            return;
        }

        $total = $this->offset + $count;
        Sunrise::info("STARTING PRODUCTS SYNC ($this->offset-$total)");

        // Process products
        foreach ($products as $i => $sunriseProduct) {
            $this->setProgress(
                $queue,
                $i / $count,
                Craft::t('app', '{step, number} of {total, number}', [
                    'step' => $i + 1,
                    'total' => $count,
                ])
            );

            // Create or update product
            $product = $this->createOrUpdateProduct($sunriseProduct);
            $this->processedProductIds[] = $product->id;
        }

        Sunrise::info("FINISHED PRODUCTS SYNC ($this->offset-$total)");

        Queue::push(new SyncProductsJob([
            'offset' => $total,
            'processedProductIds' => $this->processedProductIds,
        ]));
    }

    private function createOrUpdateProduct(array $sunriseProduct): Product
    {
        $product = Product::find()
            ->sunriseForeignId($sunriseProduct['product_id'])
            ->typeId($this->productTypeId)
            ->status(null)
            ->one()
            ?? new Product([
                'typeId' => $this->productTypeId,
                'sunriseForeignId' => $sunriseProduct['product_id'],
            ]);

        $productDetails = $this->api->get('productdetails/search', [
            'product_id' => $product->sunriseForeignId,
        ])[0] ?? [];

        // Product groups
        $productGroups = new ElementCollection();
        foreach ($productDetails['categories'] as $category) {
            $productGroup = $category['type_id']
                ? $this->productGroupService->getProductGroupByForeignId(
                    $category['type_id'],
                    $category['cat_id'] ?: null
                )
                : null;

            if ($productGroup) {
                $productGroups->push($productGroup);
            }
        }

        // Variants
        $variants = $this->createOrUpdateVariants($product, $productDetails);

        // Set product properties
        $product->promotable = true;
        $product->title = $sunriseProduct['product_title'];
        $product->sunriseProductGroups = $productGroups;
        $product->enabled = $this->stringToBoolean($productDetails['is_active_in_webshop']);
        $product->setVariants($variants);

        if (Craft::$app->getElements()->saveElement($product)) {
            Sunrise::info('SAVED PRODUCT', [
                'product' => "$product->id: $product->title",
                'variants' => array_map(fn($variant) => [$variant->id, $variant->title], $product->getVariants(true)),
            ]);
        } else {
            Sunrise::error('ERROR SAVING PRODUCT', [
                'product' => "$product->id: $product->title",
                'error' => $product->getErrors(),
            ]);
        }

        return $product;
    }

    private function createOrUpdateVariants(Product $product, array $productDetails)
    {
        $productSkus = $this->api->get('productsku', [
            'productId' => $product->sunriseForeignId,
        ])[0]['ProductSkuList'] ?? [];

        // If no SKU's, main product can be the only variant sold
        if (empty($productSkus) && !empty((int)$productDetails['price_prod'])) {
            $productSkus[] = [
                'sku_id' => $product->sunriseForeignId,
                'assigned_attribute_options' => $productDetails['product_title'],
                'sku_price_excl_vat' => $productDetails['price_prod'],
                'visible_in_webshop' => $productDetails['status_pro'] === 'ACT',
                'sku_status' => $productDetails['status_pro'] === 'ACT',
                'min_qty' => $productDetails['min_order'],
                'sku_promo_price' => $productDetails['prod_promo_price'],
            ];
        }

        $variants = [];
        foreach ($productSkus as $sku) {
            $skuId = $sku['sku_id'];

            $variant = $product->id ? $this->productService->getVariant($product, $skuId) : null;
            if (!$variant) {
                $variant = new Variant();
                $variant->setSku($skuId);
            }

            $variant->title = $sku['assigned_attribute_options'];
            $variant->price = $sku['sku_price_excl_vat'] ?: null;
//            $variant->minQty = $sku['min_qty'] ?: null;
            $variant->productQtyBoxSize = $sku['prod_package_unit'] ?: null;

            // Tiered pricing
            $tierPrices = [];
            if ($sku['min_qty'] && $sku['sku_promo_price']) {
                $tierPrices[] = [
                    'qty' => (int)$sku['min_qty'],
                    'price' => (float)$sku['sku_promo_price'],
                ];
            }

            $variant->productTierprice = $tierPrices;

            // Values are booleans in form of string
            $variant->enabled = $this->stringToBoolean([
                $sku['visible_in_webshop'],
                $sku['sku_status']
            ]);

            // Attributes
            $attributes = [];
            foreach ($sku['assigned_attributes'] ?? [] as $assignedAttribute) {
                if (!empty($assignedAttribute['attribute_id'] && !empty($assignedAttribute['option_id']))) {
                    $attribute = $this->attributeService->getAttributeByForeignId($assignedAttribute['attribute_id']);
                    $option = $this->attributeService->getOptionByForeignId($attribute->id, $assignedAttribute['option_id']);
                    if ($option) {
                        $attributes[] = $option;
                    }
                }
            }
            $variant->sunriseAttributes = collect($attributes);

            $variants[] = $variant;
        }

        // No need for variant delete logic, because product variants always get completely overwritten
        return $variants;
    }

    private function deleteUnprocessedProducts(array $processedIds)
    {
        $deletedProducts = Product::find()
            ->typeId($this->productTypeId)
            ->id(array_merge(['not'], $processedIds))
            ->all();
        foreach ($deletedProducts as $product) {
            try {
                Craft::$app->getElements()->deleteElement($product, true);
                Sunrise::info('DELETED PRODUCT', [
                    'product' => "$product->id: $product->title"
                ]);
            } catch (Exception $e) {
                Sunrise::error('ERROR DELETING PRODUCT', [
                    'product' => "$product->id: $product->title",
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function stringToBoolean($value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        return !empty(array_filter(filter_var($value, FILTER_VALIDATE_BOOL, FILTER_REQUIRE_ARRAY)));
    }
}
