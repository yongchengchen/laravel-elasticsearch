## Laravel ElasticSuit

[![Total Downloads](https://poser.pugx.org/yong/elasticsuit/d/total.svg)](https://packagist.org/packages/yong/elasticsuit)
[![Latest Stable Version](https://poser.pugx.org/yong/elasticsuit/v/stable.svg)](https://packagist.org/packages/yong/elasticsuit)
[![Latest Unstable Version](https://poser.pugx.org/yong/elasticsuit/v/unstable.svg)](https://packagist.org/packages/yong/elasticsuit)
[![License](https://poser.pugx.org/yong/elasticsuit/license.svg)](https://packagist.org/packages/yong/elasticsuit)

This is a package to integrate Elasticsearch to Laravel5

It makes you do Elasticsearch just using Eloquent's API.

## Installation

1. Require this package with composer:

```shell
composer require yong/elasticsuit dev-master
```

2. Add service provider to config/app.php

```php
Yong\ElasticSuit\Service\Provider;
```

3. Add elasticsearch node configuration to the "connections" node of config/database.php

```php
        'elasticsearch' => [
            'hosts'=>['127.0.0.1:9200'],
            'ismultihandle'=>0,
            'database'=> 'db*',
            'prefix' => '',
            'settings'=> ['number_of_shards'=>2,'number_of_replicas'=>0]
        ],
```

## Usage

1. Define a model for a elasticsearch type

```php

class TestModel extends \Yong\ElasticSuit\Elasticsearch\Model {
    protected $connection = 'elasticsearch';
    protected $table = 'testmodel';

    //relations
    public function Childmodel () {
        return $this->hasOne(OtherModel::class, '_id');
    }
}
```

2. Create a new document

```php

$testmodel = new TestModel();
$testmodel->first_name = 'firstname';
$testmodel->last_name = 'lastname';
$testmodel->age = 20;
$testmodel->save();
```

3. Search a collection

```php
$collection = TestModel::where('first_name', 'like', 'firstname')
    ->whereIn('_id', [1,2,3,4,5])
    ->whereNotIn('_id', [5,6,7,8,9])
    ->where('_id', '=', 1)
    ->where('age', '>', 18)
    ->orWhere('last_name', 'like', 'lastname')
    ->whereNull('nick_name')
    ->whereNotNull('age')
    ->whereMultMatch(['last_name', 'description'], 'search words', '60%')
    ->skip(10)
    ->forPage(1, 20)
    ->take(10)
    ->limit(10)
    ->select(['first_name', 'last_name', 'age'])
    ->get();

* also support sum(), avg(), min(), max(), stats(), count()
* but not for all fields, only numeric fields can use aggregate

```

4. Relations
    It also support relations, but remember so far just support using default _id as primary key.

```php
    //get relations
    TestModel::with('childmodel')->where('first_name', 'like', 'firstname')->get();

```

## License

And of course:

MIT: http://rem.mit-license.org
