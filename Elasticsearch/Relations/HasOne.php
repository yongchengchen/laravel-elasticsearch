<?php
namespace Yong\ElasticSuit\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Relations\HasOne as BaseHasOne;

class HasOne extends BaseHasOne
{
    public function getForeignKeyName()
    {
        $segments = explode('.', $this->getQualifiedForeignKeyName(), 2);

        return end($segments);
    }
}
