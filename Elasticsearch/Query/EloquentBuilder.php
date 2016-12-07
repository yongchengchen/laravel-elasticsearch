<?php
namespace Yong\ElasticSuit\Elasticsearch\Query;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Model;

class EloquentBuilder extends EloquentBuilder
{
    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'insert', 'insertGetId', 'getBindings', 'toSql',
        'exists', 'count', 'min', 'max', 'avg', 'sum', 'getConnection',
        'stats'
    ];

    /**
     * Create a new Eloquent query builder instance.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return void
     */
    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->query->from($model->getTable(), $model->getKeyName());
 
        return $this;
    }
}
