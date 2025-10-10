<?php

namespace brikdigital\sunrise\console\controllers;

use brikdigital\sunrise\jobs\SyncProductGroupsJob;
use brikdigital\sunrise\jobs\SyncProductsJob;
use brikdigital\sunrise\jobs\SyncProductStockJob;
use craft\console\Controller;
use craft\helpers\Queue;

class SyncController extends Controller
{
    public function actionProductGroups(): void
    {
        Queue::push(new SyncProductGroupsJob());
    }

    public function actionProducts(): void
    {
        Queue::push(new SyncProductsJob());
    }

    public function actionStock(): void
    {
        Queue::push(new SyncProductStockJob());
    }
}