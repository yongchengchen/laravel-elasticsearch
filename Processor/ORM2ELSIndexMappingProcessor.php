<?php

namespace Yong\ElasticSuit\Processor;

use Symfony\Component\Console\Output\OutputInterface;

use Illuminate\Support\Facades\Schema;
use Doctrine\DBAL\Types\Type;
use Yong\ElasticSuit\Doctrine\DBAL\Types\EnumType;

class ORM2ELSIndexMappingProcessor {
    protected $elsModel;
    protected $output;

    public function __construct($model, $indexName, OutputInterface $output)
    {
        if (!Type::hasType('enum')) {
            Type::addType('enum', EnumType::class);
        }
        $model::$elsIndexName = $indexName;
        $this->elsModel = $model;
        $this->output = $output;
    }

    public function createIndex()
    {
        $elsModel = $this->elsModel;
        $elsItem = new $elsModel;

        $columns = $elsItem->getColumns();
        $tableName = $elsItem->getTable();
        $elsBuilder = Schema::connection($elsItem->getConnectionName());
        list ($success, $rets, $msg) = $elsBuilder->buildByColumns($tableName, is_array($columns) ? collect($columns) : $columns);
        if ($success) {
            $this->output->writeln(sprintf("<info>%s index mapping success.</info>", $msg));
        } else {
            $this->output->writeln("<error>Create index mapping failed.</error>");
        }
        $this->output->writeln(json_encode($rets));
    }
}