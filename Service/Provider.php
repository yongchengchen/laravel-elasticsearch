<?php

namespace Yong\ElasticSuit\Service;

use Illuminate\Support\ServiceProvider;
use Yong\ElasticSuit\Elasticsearch\Connection;
use Yong\ElasticSuit\Console\Commands\SyncModelData;
use Yong\ElasticSuit\Console\Commands\CreateModelIndex;

class Provider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['db']->extend('elasticsearch', function($config, $name) {
            $config['name'] = $name;
            return new Connection($config['database'], $config['prefix'], $config);
        });

        if ($this->app->runningInConsole()) {
            SyncModelData::register($this, $this->app);
            CreateModelIndex::register($this, $this->app);
        }
    }
}
