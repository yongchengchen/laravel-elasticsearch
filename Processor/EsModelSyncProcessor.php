<?php

namespace Yong\ElasticSuit\Processor;

use Symfony\Component\Console\Output\OutputInterface;

class EsModelSyncProcessor {
    protected $esModel;
    protected $output;

    public function __construct($model, OutputInterface $output)
    {
        $this->esModel = $model;
        $this->output = $output;
    }

    public function sync() {
        $esModel = $this->esModel;
        $ormModel = $esModel::getORMModel();
        $this->output->progressStart($ormModel::count());
        $ormModel::chunk(100, function($collection) {
            foreach($collection as $item) {
                $this->sync_item($this->esModel, $item);
                $this->output->progressAdvance();
            }
        });
        $this->output->progressFinish();
    }

    protected function sync_item($esModel, $ormItem) {
        $keyName = $ormItem->getKeyName();
        $esItem = $esModel::where($keyName, '=', $ormItem->getKey())->first();
        if (!$esItem) {
            $esItem = new $esModel();
        }
        $data = $ormItem->toArray();
        $esItem->forceFill($data);
        $esItem->save();
    }
}