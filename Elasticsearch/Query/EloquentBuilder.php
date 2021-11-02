<?php
namespace Yong\ElasticSuit\Elasticsearch\Query;

use Illuminate\Container\Container;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Yong\ElasticSuit\Pagination\LengthAwarePaginator;

class EloquentBuilder extends \Illuminate\Database\Eloquent\Builder
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


    /**
     * Paginate the given query.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        list($results, $total, $aggregate) = $this->forPage($page, $perPage)->getFromEls($columns);
        return $this->paginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
            'aggregate' => $aggregate
        ]);
    }

    protected function paginator($items, $total, $perPage, $currentPage, $options)
    {
        return Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items', 'total', 'perPage', 'currentPage', 'options'
        ));
    }

    protected function getFromEls($columns = ['*']) {
        $builder = $this->applyScopes();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        $total = 0;
        $aggregate = null;
        if (count($models = $builder->getModels($columns)) > 0) {
            $total = $builder->getElsResponse()->getTotal();
            $aggregate = $builder->getElsResponse()->getFieldsAggregations();
            $models = $builder->eagerLoadRelations($models);
        }
        return [$builder->getModel()->newCollection($models), $total, $aggregate];
    }

    public function get($columns = ['*']) {
        return $this->paginate(100, $columns);
    }

    protected function getElsResponse() {
        return $this->query->getElsResponse();
    }
}
