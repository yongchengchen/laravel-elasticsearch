<?php

namespace Yong\ElasticSuit\Elasticsearch;

use Illuminate\Support\Facades\Schema;
use Yong\ElasticSuit\Elasticsearch\Query\Builder;
use Yong\ElasticSuit\Elasticsearch\Query\EloquentBuilder;
use Doctrine\DBAL\DBALException;

class Model extends \Illuminate\Database\Eloquent\Model
{
    protected $connection = 'elasticsearch';
    protected $primaryKey = '_id';

    protected static $ORMModel;

    public static $elsIndexName;

    public static function getORMModel() {
        return static::$ORMModel;
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
}
