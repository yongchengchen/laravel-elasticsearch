<?php

namespace Yong\ElasticSuit\Elasticsearch\Query;

use Closure;
use RuntimeException;
use BadMethodCallException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Query\Builder;

class Builder extends Builder
{
    public $keyname;

    /**
     * Set the table which the query is targeting.
     *
     * @param  string  $table
     * @return $this
     */
    public function from($table, $primaryKeyName='_id')
    {
        $this->from = $table;
        $this->keyname = $primaryKeyName;

        return $this;
    }

    private function notSupport($keyword) {
        throw new RuntimeException(sprintf('keyword "%s" is not supported by Elasticsearch', $keyword));
    }

    public function join($table, $one, $operator = null, $two = null, $type = 'inner', $where = false)
    {
        $this->notSupport('join');
    }

    public function joinWhere($table, $one, $operator, $two, $type = 'inner')
    {
        $this->notSupport('joinWhere');
    }

    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        $this->notSupport('leftJoin');
    }

    public function leftJoinWhere($table, $one, $operator, $two)
    {
        $this->notSupport('leftJoinWhere');
    }

    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        $this->notSupport('rightJoin');
    }

    /**
     * Add a "cross join" clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function crossJoin($table, $first = null, $operator = null, $second = null)
    {
        $this->notSupport('crossJoin');
    }


    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function stats($columns = '*')
    {
        if (! is_array($columns)) {
            $columns = [$columns];
        }

        return $this->aggregate(__FUNCTION__, $columns); 
    }


    /**
     * Add a "where mulit match" clause to the query.
     *
     * @param  array  $column
     * @param  string  $value
     * @param  string  $boolean
     * @param  string  $operator  ('and', number for percentage)
     * @return $this
     */
    public function whereMultiMatch(array $columns, $value, $operator='and', $boolean = 'and') 
    {
        $type = 'MultiMatch';
        $op_param = $operator;
        if (strpos($operator, '%')>0) {
            $operator = 'minimum_should_match';
        } else {
            $operator = 'operator';
        }
        $this->wheres[] = compact('type', 'columns', 'operator', 'op_param', 'value', 'boolean');
        $this->addBinding($value, 'where');
        return $this;
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return float|int
     */
    public function aggregate($function, $columns = ['*'])
    {
        $this->aggregate = compact('function', 'columns');

        $previousColumns = $this->columns;

        // We will also back up the select bindings since the select clause will be
        // removed when performing the aggregate function. Once the query is run
        // we will add the bindings back onto this query so they can get used.
        $previousSelectBindings = $this->bindings['select'];

        $this->bindings['select'] = [];

        $results = $this->limit(1)->getAggregate($columns);

        $this->aggregate = null;

        $this->columns = $previousColumns;

        $this->bindings['select'] = $previousSelectBindings;

        if ($function !== 'stats') {
            return $results['aggregate']['value'];
        } else {
            return $results['aggregate'];
        }
    }


    /**
     * Execute the query as a "select" statement for aggregate only
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function getAggregate($columns = ['*'])
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $results = $this->processor->processSelectAggregate($this, $this->runSelect());
        $this->columns = $original;

        return $results;
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function offset($value)
    {
        $this->offset = max(0, $value);
        return $this;
    }


    /**
     * Add a "where null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereNull($column, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotNull' : 'Null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }
}

