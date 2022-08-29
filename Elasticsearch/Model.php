<?php

namespace Yong\ElasticSuit\Elasticsearch;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;
use Yong\ElasticSuit\Elasticsearch\Query\Builder;
use Yong\ElasticSuit\Elasticsearch\Query\EloquentBuilder;
use Doctrine\DBAL\DBALException;
use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
    protected $connection = 'elasticsearch';
    protected $primaryKey = '_id';

    protected static $ORMModel;

    public static $elsIndexName;

    public function attrFromX($key, $default = null) {
        if ($this->x) {
            return Arr::get($this->x, $key, $default);
        }
        return $default;
    }

    public static function extendIndexingQueryChunk($query, $chunkSize, \Closure $callback) {
        return $query->chunk($chunkSize, $callback);
    }
    
    public static function getORMModel() {
        return static::$ORMModel;
    }

    public function get2ndKeyName() {
        return null;
    }

    public static function ormRelations() {
        return null;
    }

    public function orm2ElsData($ormItem) {
        return $ormItem->toArray();
    }

    public function getTable() {
        if (static::$elsIndexName) {
            return static::$elsIndexName;
        }
        if ($this->table) {
            return $this->table;
        }
        if ($ormClass = static::$ORMModel) {
            return with(new $ormClass)->getTable();
        }
        return parent::getTable();
    }
    
    public function getColumns() {
        $columns = [];
        if ($ormClass = static::$ORMModel) {
            $ormItem = new $ormClass;
            $tableName = $ormItem->getTable();
            $schemaBuilder = Schema::connection($ormItem->getConnectionName());
            $columnNames = $schemaBuilder->getColumnListing($tableName);
            $columns = [];
            foreach($columnNames as $name) {
                try {
                    $type = $schemaBuilder->getColumnType($tableName, $name);
                } catch (DBALException $e) {
                    $type = 'string';
                }
                $columns[] = compact('name', 'type');
            }
        }
        return collect($columns);
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    public function getSchema() {
        $params = [
            'index' => $this->getTable(),
            'type' => '_doc'
        ];

        // Update the index mapping
        $result = $this->getConnection()->elsAdapter()->indices()->getMapping($params);

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

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery(\Illuminate\Database\Eloquent\Builder $query)
    {
        parent::setKeysForSaveQuery($query);
        $baseQuery = $query->getQuery();
        $baseQuery->keyValue = $this->getKeyForSaveQuery();
        return $query;
    }

    public function getAttribute($key)
    {
        if (Str::contains($key, '.')) {
            return Arr::get($this->attributes, $key);
        }
        return parent::getAttribute($key);
    }

    protected function newHasOne(BaseBuilder $query, BaseModel $parent, $foreignKey, $localKey)
    {
        return new Relations\HasOne($query, $parent, $foreignKey, $localKey);
    }

    protected function newHasMany(BaseBuilder $query, BaseModel $parent, $foreignKey, $localKey)
    {
        return new Relations\HasMany($query, $parent, $foreignKey, $localKey);
    }

    public function newFromBuilder($attributes = [], $connection = null)
    {
        $realAttributes = $attributes['_source'] ?? [];
        $realAttributes['_id'] = $attributes['_id'];
        $model = parent::newFromBuilder($realAttributes, $connection);
        $model->setTable($attributes['_index']);
        return $model;
    }
}
