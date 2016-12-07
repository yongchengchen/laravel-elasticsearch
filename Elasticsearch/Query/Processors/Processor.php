<?php
namespace Yong\ElasticSuit\Elasticsearch\Query\Processors;

use Yong\ElasticSuit\Elasticsearch\Query\Response;

use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $result = $query->getConnection()->insert($sql, $values);
        $id = $query->getConnection()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of a "select" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processSelect(Builder $query, $values)
    {
        $rsp = new Response($values);
        return $rsp->getHits();
    }

    /**
     * Process the results of a "select" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processSelectAggregate(Builder $query, $values)
    {
        $rsp = new Response($values);
        return $rsp->getAggregations();
    }

    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        $mapping = function ($r) {
            $r = (object) $r;

            return $r->name;
        };

        return array_map($mapping, $results);
    }
}
