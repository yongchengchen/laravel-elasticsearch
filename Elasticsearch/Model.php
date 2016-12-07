<?php

namespace Yong\ElasticSuit\Elasticsearch;

use Yong\ElasticSuit\Elasticsearch\Query\Builder;
use Yong\ElasticSuit\Elasticsearch\Query\EloquentBuilder;

class Model extends \Illuminate\Database\Eloquent\Model
{
    protected $connection = 'elasticsearch';
    protected $primaryKey = '_id';

    public function getSchema() {
        $params = [
            'index' => $this->getConnection()->getDatabaseName(),
            'type' => $this->table
        ];

        // Update the index mapping
        $result = $this->getConnection()->connect()->indices()->getMapping($params);

        return $result;
    }
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new EloquentBuilder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        return new Builder($conn, $grammar, $conn->getPostProcessor());
    }
}
