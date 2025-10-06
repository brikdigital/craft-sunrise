<?php

namespace brikdigital\sunrise\console\controllers;

use brikdigital\sunrise\jobs\SyncProductGroups;
use craft\console\Controller;
use craft\helpers\Queue;

class SyncController extends Controller
{
    public function actionProductGroups()
    {
        Queue::push(new SyncProductGroups());
    }
}