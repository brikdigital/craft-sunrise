<?php

namespace brikdigital\sunrise\services;

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
}
