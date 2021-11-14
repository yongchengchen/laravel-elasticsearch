<?php

namespace Yong\ElasticSuit\Elasticsearch\Schema;

use Closure;
use Illuminate\Support\Collection;
use Yong\ElasticSuit\Elasticsearch\Schema\Blueprint;

class Builder extends \Illuminate\Database\Schema\Builder
{
    protected $ormModel;
    protected $blueprintCallback;

    /**
     * The database connection instance.
     *
     * @var \Yong\ElasticSuit\Elasticsearch\Connection
     */
    protected $connection;

    /**
     * Create a new database Schema manager.
     *
     * @param  \Yong\ElasticSuit\Elasticsearch\Connection $connection
     * @return void
     */
    public function __construct(\Yong\ElasticSuit\Elasticsearch\Connection $connection)
    {
        $this->connection = $connection;
    }

    public function hasTable($table) {
        return $this->connection->elsAdapter()->indices()->exists(['index' => $table]);
    }

    // public function table($table, Closure $callback) {
    // }

    public function fromOrmModel($className, \Closure $callback) {
        $this->ormModel = $className;
        $this->blueprintCallback = $callback;
        return $this;
    }

    protected function prepareFiledMapping($define) {
        $mapping = [];
        $define = is_array($define) ? $define : ['type' => $define];
        $type = $define['type'];
        switch($type) {
            case 'integer':
            case 'float':
            case 'double':
                $mapping['type'] = $type;
                break;
            case 'boolean':
                $mapping['type'] = 'boolean';
                break;
            case 'timestamp':
            case 'datetime':
                $mapping['type'] = 'date';
                $mapping['format'] = 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis';
                break;
            case 'string':
            case 'varchar':
            case 'text':
                $mapping = ['type' => 'text', 'fielddata' => true];
                break;
            case 'keyword':
                $mapping = ['type' => 'keyword'];
                break;
            case 'object':
            case 'json':
                $mapping = ['type' => 'object'];
                break;
            case 'suggest':
                $mapping = ['type' => 'completion'];
                break;
            default:
                $mapping = ['type' => 'text', 'fielddata' => true];
                break;
        }

        if ($define['fielddata'] ?? false) {
            $mapping['fielddata'] = true;
        }
        if ($define['keyword'] ?? false) {
            $mapping['fields']['keyword'] = ['type' => 'keyword'];
        }
        
        // if ($define['suggest'] ?? false) {
        //     $mapping['fields']['suggest'] = ['type' => $define['suggest']];
        // }

        if (($define['index'] ?? false) && $mapping['type'] == 'string' && !is_bool($define['index'])) {
            $mapping['index'] = $define['index'];
            $mapping['fields']['raw'] = ['index' => 'not_analyzed', 'type' => 'string'];
        }
        
        return $mapping;
    }

    /**
     * Create a new command set with a Closure.
     *
     * @param  string  $table
     * @param  \Closure|null  $callback
     * @return \Illuminate\Database\Schema\Blueprint
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        if (isset($this->resolver)) {
            return call_user_func($this->resolver, $table, $callback);
        }

        return new Blueprint($table, $callback);
    }

    /**
     * Execute the blueprint to build / modify the table.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @return void
     */
    protected function build(\Illuminate\Database\Schema\Blueprint $blueprint)
    {
        if ($this->blueprintCallback) {
            $callback = $this->blueprintCallback;
            $callback($blueprint);
        }
        $columns = $blueprint->getColumns();
        return $this->buildByColumns($blueprint->getTable(), $columns);
    }

    /**
     * @params Illuminate\Support\Collection $columns 
     * each column is an array with the following keys:
     * - name (*required)
     * - type (*required)
     * - keyword   (optional)
     * - fielddata (optional)
     * - index     (optional)
     */
    public function buildByColumns($table, Collection $columns) {
        $body = [];
        $doc = [];
        foreach($columns as $column) {
            $configs = is_object($column) ? $column->toArray() : $column;
            $body[$configs['name']] =  $this->prepareFiledMapping($configs);
        }
        $doc['properties'] = $body;
        try {
            $indices = $this->connection->elsAdapter()->indices();
            if ($indices->exists([ 'index' => $table ])) {
                $indices->putMapping([
                    'index' => $table,
                    'body' => $doc
                ]);
                return [true, $doc, 'Update'];
            } else {
                $body = [
                    'settings' => [
                        'index' => [
                            'number_of_replicas' => 0,
                            // "analysis" => [
                            //     "analyzer" => [
                            //       "trigram"=>[
                            //         "type"=> "custom",
                            //         "tokenizer"=> "standard",
                            //         "filter"=> ["lowercase","shingle"]
                            //         ],
                            //       "reverse"=>[
                            //         "type"=> "custom",
                            //         "tokenizer"=> "standard",
                            //         "filter" => ["lowercase","reverse"]
                            //         ]
                            //     ],
                            //     "filter"=> [
                            //       "shingle"=> [
                            //         "type"=> "shingle",
                            //         "min_shingle_size"=> 2,
                            //         "max_shingle_size"=> 3
                            //       ]
                            //     ]
                            // ]
                        ],
                    ],
                    'mappings' => $doc
                ];
                $indices->create([
                    'index' => $table,
                    'body' => $body
                ]);
                return [true, $body, 'Create'];
            }
        } catch (\Elasticsearch\Common\Exceptions\BadRequest400Exception $e) {
            $msg = json_decode($e->getMessage(), true);
            if ($errType = $msg['error']['root_cause'][0]['type'] ?? false) {
                if ($errType !== 'resource_already_exists_exception') {
                    throw $e;
                }

                return [false, $msg, ''];
            }
        } 
    }

    public function drop($table) {
        if ($this->hasTable($table)) {
            $this->connection->elsAdapter()->indices()->deleteMapping(
                [
                    'index' => $table,
                    'type' => '_doc'   
                ]);
        }
    }
}