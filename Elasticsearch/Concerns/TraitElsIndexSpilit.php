<?php

namespace Yong\ElasticSuit\Elasticsearch\Concerns;

use Artisan;
use Illuminate\Database\Eloquent\Model;

trait TraitElsIndexSpilit
{
    /**
     * base on model class and split doc number to determin each index's start/end id.
     * @param Illuminate\Database\Eloquent\Model $modelClass
     * @param string $orderKey
     * @param $splitDocNumber
     * @param $lastStartId  last startId if you have stored paramter for elstic indexes group
     * @param $lastIndex    the index number from 0 which indicate the elstic indexes
     */
    protected function prepareIndexes(Model $model, string $orderKey, $splitDocNumber, $lastStartId = 0, $lastIndex = 0)
    {
        $modelClass = get_class($model);
        $indexPattern = rtrim($model->getTable(), '*');
        $total = $modelClass::count();
        $indexCount = ceil($total / $splitDocNumber);
        $fastIndexSrchs = [];
        $lastId = $lastStartId;
        for ($i = $lastIndex; $i < $indexCount; $i++) {
            $indexName = sprintf('%s-%d', $indexPattern, $i);
            $fastIndexSrchs[$lastId] = $indexName;
            if ($order = $modelClass::where($orderKey, '>', $lastId)->orderBy($orderKey)->offset($splitDocNumber)->limit(1)->first()) {
                $lastId = $order->{$orderKey};
            }
        }
        return $fastIndexSrchs;
    }

    protected function createIndex(string $modelClass, string $indexName)
    {
        Artisan::call('els:create-index', ['model' => $modelClass, 'index' => $indexName]);
        return Artisan::output();
    }
}
