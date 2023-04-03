<?php

namespace Yong\ElasticSuit\Elasticsearch\Query;

use RuntimeException;
use Inkstation\Base\Providers\Facades\Registry;

class Builder extends \Illuminate\Database\Query\Builder
{
    public $keyname;
    public $keyValue;

    public $withHighLight = false;

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
     * Retrieves the "count" result of the query.
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


    public function aggs($columns = false) {
        if ($columns) {
            $function = 'aggs';
            if (! is_array($columns)) {
                $columns = [$columns];
            }
            $this->aggregate = compact('function', 'columns');
        }
        return $this;
    }

    /**
     * Add a "where mulit match" clause to the query.
     *
     * @param  array  $column
     * @param  string  $value
     * @param  array  $value
     * @param  string  $boolean
     * @param  string  $operator  ('and', number for percentage)
     * @return $this
     */
    public function whereMultiMatch(array $columns, $value, $excludes = null, $operator='and', $boolean = 'and') 
    {
        $type = 'MultiMatch';
        $op_param = $operator;
        if (strpos($operator, '%')>0) {
            $operator = 'minimum_should_match';
        } else {
            $operator = 'operator';
        }
        $this->wheres[] = compact('type', 'columns', 'excludes', 'operator', 'op_param', 'value', 'boolean');
        $this->addBinding($value, 'where');
        return $this;
    }

    public function whereBooleanSub(array $columns, $operator='and', $boolean = 'and')
    {
        $subs = [];
        $op = $operator;
        $b = $boolean;
        foreach($columns as $cond) {
            [$type, $columns, $value, $operator, $boolean] = $cond;
            $op_param = $operator;
            $subs[] = compact('type', 'columns', 'operator', 'op_param', 'value', 'boolean');
        }
        $type = 'BooleanSub';
        $operator = $op;
        $boolean = $b;
        $this->wheres[] = compact('type', 'subs', 'operator', 'boolean');
        return $this;
    }

    public function whereWildcard($column, $value, $operator='and', $boolean = 'and') 
    {
        $type = 'wildcard';
        $op_param = $operator;
        $this->wheres[] = compact('type', 'column', 'operator', 'op_param', 'value', 'boolean');
        $this->addBinding($value, 'where');
        return $this;
    }

    public function whereBoolean($column, $flag = true, $boolean = 'and') 
    {
        $type = 'Boolean';
        $this->wheres[] = compact('type', 'column', 'flag', 'boolean');
        return $this;
    }

    public function whereMinScore($value)
    {
        $type = 'MinScore';
        $boolean = 'and';
        $this->wheres[] = compact('type', 'value', 'boolean');
        return $this;
    }

    public function whereMultiOr(array $columns, $not = false)
    {
        // $type = $not ? 'NotNull' : 'Null';
        $type = "MultiOr";

        $this->wheres[] = compact('type', 'columns');
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
        $type = $not ? 'Null' : 'NotNull';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereInLike($column, $values, $boolean = 'and', $not = false)
    {
        $type = 'InLike';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        foreach ($values as $value) {
            if (! $value instanceof Expression) {
                $this->addBinding($value, 'where');
            }
        }

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        return collect($this->onceWithColumns($columns, function () {
            return $this->processor->processSelect($this, $this->runSelect());
        }));
    }

    public function toSql()
    {
        $data = parent::toSql();
        $min_score = $data["body"]["query"]["min_score"] ?? 0;
        unset($data["body"]["query"]["min_score"]);
        if ($min_score) {
            $data["body"]["min_score"] = $min_score;
        }
        if ($this->withHighLight) {
            $data["body"]["highlight"] = [
                "fields" => [
                        "*" => [ "pre_tags" => ["<mark>"], "post_tags" => ["</mark>"] 
                    ]
                ]
            ];
        }
      
        Registry::put('_elastic_wheres_', $data);
        return $data;
    }

    public function rawGet($columns = ['*'])
    {
        return collect($this->onceWithColumns($columns, function () {
            return $this->runSelect();
        }));
    }

    public function mget($builders = [], $columns = ['*'])
    {
        $queries = [];
        foreach($builders as $builder) {
            $item = $builder->toSql();
            $queries[] = ['index' => $item['index']];
            $queries[] = $item['body'];
        }
        return $this->processor->processMSelect($this, $this->runMSelect($queries));
    }

    protected function runMSelect($queries)
    {
        return $this->connection->mselect(
            $queries, $this->getBindings(), ! $this->useWritePdo
        );
    }

    public function getElsResponse() {
        return $this->processor->getResponse();
    }
}

