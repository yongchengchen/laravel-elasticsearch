<?php

namespace Yong\ElasticSuit\Elasticsearch;

use Illuminate\Database\Eloquent\Model as ORMModel;

interface InterfaceComplexIndexer
{
    /**
     * @param Illuminate\Database\Eloquent\Model $model
     */
    public function grabDataFromOrm(ORMModel $ormItem);
}