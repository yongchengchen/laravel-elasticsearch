<?php
/**
 *
 * @category   Framework support
 * @copyright
 * @license
 * @author      Yongcheng Chen yongcheng.chen@live.com
 */

namespace Yong\ElasticSuit\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yong\ElasticSuit\Processor\EsModelSyncProcessor;

class SyncModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'els:sync {model : Elasticsearch Model}';

    protected $description = "sync mysql model to elasticsearch model";

    public static function register($serviceProvider, &$appContainer) {
        $class = static::class;
        $name = md5($class);
        $appContainer->singleton($name, function ($app) use($class) {
            return $app[$class];
        });
        $serviceProvider->commands($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $model = $this->argument('model');
        with(new EsModelSyncProcessor($model, $output))->sync();
        return 0;
    }
}
