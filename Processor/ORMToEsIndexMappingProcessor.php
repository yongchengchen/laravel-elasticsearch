<?php

namespace Yong\ElasticSuit\Processor;

use Symfony\Component\Console\Output\OutputInterface;

use Illuminate\Support\Facades\Schema;

class ORMToEsIndexMappingProcessor {
    protected $esModel;
    protected $output;

    public function __construct($model, OutputInterface $output)
    {
        $this->esModel = $model;
        $this->output = $output;
    }

    public function createIndex()
    {
        $esModel = $this->esModel;
        $ormModel = $esModel::getORMModel();

        $ormItem = new $ormModel;
        $keyName = $ormItem->getKeyName();
        $tableName = $ormItem->getTable();
        $schemaBuilder = Schema::connection($ormItem->getConnectionName());
        $columnNames = $schemaBuilder->getColumnListing($tableName);
        $columns = [];
        foreach($columnNames as $name) {
            $type = $schemaBuilder->getColumnType($tableName, $name);
            $columns[] = compact('name', 'type');
        }

        $elsBuilder = Schema::connection('elasticsearch');
        $elsBuilder->buildByColumns($tableName, $columns);
    }
}