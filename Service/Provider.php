<?php

namespace Yong\ElasticSuit\Service;

use Illuminate\Support\ServiceProvider;
use Yong\ElasticSuit\Elasticsearch\Connection;
use Yong\ElasticSuit\Console\Commands\SyncModel;
use Yong\ElasticSuit\Console\Commands\MappingModel;

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
            return new Connection($config['database'], $config['prefix'], $config);
        });

        if ($this->app->runningInConsole()) {
            SyncModel::register($this, $this->app);
            MappingModel::register($this, $this->app);
        }
    }
}