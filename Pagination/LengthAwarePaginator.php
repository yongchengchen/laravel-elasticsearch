<?php
namespace Yong\ElasticSuit\Pagination;

use \Illuminate\Pagination\LengthAwarePaginator as BaseLengthAwarePaginator;

class LengthAwarePaginator extends BaseLengthAwarePaginator {
    protected $_extData = [];

    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            } else {
                $this->_extData[$key] = $value;
            }
        }
        parent::__construct($items, $total, $perPage, $currentPage);
    }

    public static function fromPaginator(\Illuminate\Pagination\LengthAwarePaginator $paginator, array $extOptions =[]) {
        $extOptions['path'] =  self::resolveCurrentPath();
        $extOptions['pageName'] = $paginator->getPageName();
        return new static($paginator->items(), 
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            $extOptions
        );
    }

    public function setExtraData($key, $value) {
        $this->_extData[$key] = $value;
    }

    public function toArray()
    {
        return array_merge($this->_extData, parent::toArray());
    }
}