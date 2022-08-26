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
        if (is_null($this->query->limit)) {
            $this->forPage(1, 1024);
        }

        list($results) = $this->getFromEls($columns);
        return $results;
    }

    public function mGet($builders = [], $columns = ['*']) {
        return $this->pureMGet($builders, $columns);
    }

    protected function pureMGet($builders = [], $columns = ['*'], $withAggregations = false) {
        $builder = $this->applyScopes();
        $responses = $this->query->mget($builders);

        $results = [];
        foreach($responses as $i => $response) {
            $models = $builders[$i]->model->hydrate(
                $response->getHits()
            )->all();
            if (count($models)) {
                $models = $builders[$i]->eagerLoadRelations($models);
            }

            if ($withAggregations) {
                $total = $response->getTotal();
                $aggregate = $response->getFieldsAggregations();
                $results[] = compact('models', 'total', 'aggregate');
            } else {
                $results[] = compact('models');
            }
        }
        return $results;
    }

    protected function eagerLoadRelation(array $models, $name, \Closure $constraints)
    {
        // First we will "back up" the existing where conditions on the query so we can
        // add our eager constraints. Then we will merge the wheres that were on the
        // query back to it in order that any where conditions might be specified.
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        $constraints($relation);

        // Once we have the results, we just match those back up to their parent models
        // using the relationship instance. Then we just return the finished arrays
        // of models which have been eagerly hydrated and are readied for return.
        return $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(), $name
        );
    }

    public function quickFacetsPaginate($builders = [], $perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
        $results = $this->pureMGet($builders, $columns, true);
        if (!empty($results)) {
            $mainData = $results[0];
            for($i=1, $l = count($results); $i<$l; $i++) {
                $mainData['aggregate'] = array_merge($mainData['aggregate'], $results[$i]['aggregate']);
            }

            $models = $mainData['models'];
            $total = $mainData['total'];
            $aggregate = $mainData['aggregate'];
        } else {
            $models = [];
            $total = 0;
            $aggregate = [];
        }
        
        return $this->paginator($models, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
            'aggregate' => $aggregate
        ]);
    }

    protected function getElsResponse() {
        return $this->query->getElsResponse();
    }
}
