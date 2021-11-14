<?php

namespace Yong\ElasticSuit\Processor;

use Symfony\Component\Console\Output\OutputInterface;
use Yong\ElasticSuit\Elasticsearch\InterfaceComplexIndexer;

class ORM2ELSSyncModelDataProcessor {
    protected $elsModel;
    protected $output;

    public function __construct($model, $indexName, OutputInterface $output)
    {
        $model::$elsIndexName = $indexName;
        $this->elsModel = $model;
        $this->output = $output;
    }

    public function sync() {
        $elsModel = $this->elsModel;
        $ormModel = $elsModel::getORMModel();
        $this->output->progressStart($ormModel::count());
        $ormModel::with($elsModel::ormRelations())->chunk(1000, function($collection) {
            foreach($collection as $item) {
                $this->sync_item($this->elsModel, $item);
                $this->output->progressAdvance();
            }
        });
        $this->output->progressFinish();
    }

    protected function sync_item($elsModel, $ormItem) {
        $keyName = $ormItem->getKeyName();
        $elsItem = $elsModel::where($keyName, '=', $ormItem->getKey())->first();
        if (!$elsItem) {
            $elsItem = new $elsModel();
        }
        
        if ($elsItem instanceof InterfaceComplexIndexer) {
            $elsItem->forceFill($elsItem->grabDataFromOrm($ormItem));
        } else {
            $elsItem->forceFill($ormItem->toArray());
        }
        $elsItem->save();
    }
}