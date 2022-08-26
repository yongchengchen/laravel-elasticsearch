<?php

namespace Yong\ElasticSuit\Elasticsearch\Schema;

class Blueprint extends \Illuminate\Database\Schema\Blueprint {
    /**
     * Create a new string column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function keyword($column)
    {
        return $this->addColumn('keyword', $column);
    }

    public function suggest($column, $suggestType = 'completion')
    {
        return $this->addColumn('suggest', $column, compact('suggestType'));
    }

    public function object($column, $properties = [])
    {
        return $this->addColumn('object', $column, compact('properties'));
    }

    public function array($column, $properties = [])
    {
        return $this->addColumn('array', $column, compact('properties'));
    }
}