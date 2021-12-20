<?php
namespace Yong\ElasticSuit\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Relations\HasMany as BaseHasMany;

class HasMany extends BaseHasMany
{
    public function getForeignKeyName()
    {
        $segments = explode('.', $this->getQualifiedForeignKeyName(), 2);

        return end($segments);
    }
}
