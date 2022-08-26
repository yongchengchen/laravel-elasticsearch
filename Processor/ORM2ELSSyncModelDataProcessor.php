<?php

namespace Yong\ElasticSuit\Processor;

use Symfony\Component\Console\Output\OutputInterface;
use Yong\ElasticSuit\Elasticsearch\InterfaceComplexIndexer;

class ORM2ELSSyncModelDataProcessor {
    protected $elasticModel;
    protected $output;
    protected $emptyElasticInstance;

    public function __construct($model, $indexName, OutputInterface $output)
    {
        $model::$elsIndexName = $indexName;
        $this->elasticModel = $model;
        $this->output = $output;
    }

    public function sync($modelIdsString = null) {
        $elasticModel = $this->elasticModel;
        $ormModel = $elasticModel::getORMModel();
        $this->emptyElasticInstance = new $elasticModel;
        $keyName = $this->emptyElasticInstance->get2ndKeyName();

        $relations = $elasticModel::ormRelations();
        $builder = $ormModel::query();
        if ($relations = $elasticModel::ormRelations()) {
            $builder->with($relations);
        }

        $amount = 1;
        if ($modelIdsString) {
            $modelIds = explode(',', $modelIdsString);
            $amount = count($modelIds);
            $builder->whereKey($modelIds);
        } else {
            $amount = $ormModel::count();
        }
        $this->output->progressStart($amount);
        $elasticModel::extendIndexingQueryChunk($builder, 200, function($collection) use ($keyName) {
            $keyValues = [];
            foreach($collection as $item) {
                $keyValues[] = $this->sync_item($this->elasticModel, $item, $keyName);
                $this->output->progressAdvance();
            }
            return $keyValues;
        });
        $this->output->progressFinish();
    }

    protected function sync_item($elasticModel, $ormItem, $keyName) {
        $keyName = empty($keyName) ? $ormItem->getKeyName() : $keyName;

        $ormData = $this->emptyElasticInstance->grabDataFromOrm($ormItem);

        if (empty($ormData)) {
            $this->output->writeln("<error>{$ormItem->getKey()} not been indexed.</error>");
            return;
        }

        if (empty($ormData[$keyName])) {
            $this->output->writeln("<error>ORM Data doesn't have key {$keyName}, will not been indexed.</error>");
            return;
        }

        $keyValue = $ormData[$keyName];
        $elsItem = $elasticModel::where($keyName, '=', $keyValue)->first();
        if (!$elsItem) {
            $elsItem = new $elasticModel();
        }
        $elsItem->forceFill($ormData);
        $elsItem->save();

        return $keyValue;
    }
}