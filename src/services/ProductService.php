<?php

namespace brikdigital\sunrise\services;

use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\services\ProductTypes;
use yii\base\Component;

class ProductService extends Component
{
    private const PRODUCT_TYPE_HANDLE = 'sunrise';

    public function getProductType(): ?ProductType
    {
        $service = new ProductTypes();
        return $service->getProductTypeByHandle(self::PRODUCT_TYPE_HANDLE);
    }

    public function getProductQuery(): ProductQuery
    {
        return Product::find()
            ->sunriseForeignId(':notempty:')
            ->type(self::PRODUCT_TYPE_HANDLE)
            ->orderBy('id ASC');
    }

    public function getVariant(Product $product, string $sku): ?Variant
    {
        return Variant::find()
            ->product($product)
            ->sku($sku)
            ->status(null)
            ->one();
    }
}
