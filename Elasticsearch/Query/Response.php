<?php
namespace Yong\ElasticSuit\Elasticsearch\Query;

use Illuminate\Support\Arr;

class Response
{
    private $succeed;
    private $took;
    private $timed_out;
    private $total;
    private $hits;
    private $rsp;

    public function __construct(array $rsp)
    {
        $this->rsp = $rsp;
        $this->took = Arr::get($rsp, 'took');
        $this->timed_out = Arr::get($rsp, 'timed_out');
        $hits = Arr::get($rsp, 'hits');
        $this->total = Arr::get($hits, 'total', 0);
        if ($this->total > 0) {
            $this->hits = [];
            $subhits =  Arr::get($hits, 'hits', []);
            foreach($subhits as $record) {
                $id = Arr::get($record,'_id', null);
                $_source = Arr::get($record, '_source', []);
                if (!is_null($id)) {
                    $_source['_id'] = $id;
                }
                $this->hits[] = $_source;
            }
        }
    }

    /**
     * @return mixed
     */
    public function getTook()
    {
        return $this->took;
    }

    /**
     * @return mixed
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @return mixed
     */
    public function getHits()
    {
        return $this->hits;
    }

    public function getAggregations() {
        return Arr::get($this->rsp, 'aggregations', ['aggregate'=>['value'=>'0']]);
    }
}