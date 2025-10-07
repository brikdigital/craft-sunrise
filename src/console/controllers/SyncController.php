<?php

namespace brikdigital\sunrise\console\controllers;

use brikdigital\sunrise\jobs\SyncProductGroupsJob;
use brikdigital\sunrise\jobs\SyncProductsJob;
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
}