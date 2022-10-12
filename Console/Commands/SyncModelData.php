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
use Yong\ElasticSuit\Processor\ORM2ELSSyncModelDataProcessor;
use Yong\ElasticSuit\Elasticsearch\InterfaceComplexIndexer;

class SyncModelData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'els:sync-data {model : Elasticsearch Model} {index : Index name} {--modelId=} {--fromId=} {--toId=}';

    protected $description = "sync mysql model data to elasticsearch index";

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
        if (!((new $model) instanceof InterfaceComplexIndexer)) {
            $output->writeln("<error>model must be instance of InterfaceComplexIndexer</error>");
            return;
        }

        $indexName = $this->argument('index');
        if ($indexName == 'default') {
            $indexName = '';
        }

        with(new ORM2ELSSyncModelDataProcessor($model, $indexName, $output))
            ->sync($this->option('modelId'), $this->option('fromId'), $this->option('toId'));
        return 0;
    }
}
