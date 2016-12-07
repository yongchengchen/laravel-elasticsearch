<?php

namespace Yong\ElasticSuit\Service;

use Illuminate\Support\ServiceProvider;
use Yong\ElasticSuit\Elasticsearch\Connection;
use DB;

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
    }

}
