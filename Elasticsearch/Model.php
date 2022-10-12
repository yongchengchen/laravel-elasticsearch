<?php

namespace Yong\ElasticSuit\Elasticsearch;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;
use Yong\ElasticSuit\Elasticsearch\Query\Builder;
use Yong\ElasticSuit\Elasticsearch\Query\EloquentBuilder;
use Doctrine\DBAL\DBALException;
use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends BaseModel
{
    protected $connection = 'elasticsearch';
    protected $primaryKey = '_id';

    protected static $ORMModel;

    public static $elsIndexName;

    protected $_encryptColumns;

    protected $_performAction = false;

    public function _encryptData($plaintext)
    {
        if ($plaintext = trim($plaintext)) {
            $key = 'password';
            $cipher = "AES-128-CBC";
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $iv = '1234567890123456';
            $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv);
            $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
            return base64_encode($hmac.$ciphertext_raw);
        }
        return '';
    }

    public function _decryptData($ciphertext)
    {
        if (empty($ciphertext)) {
            return '';
        }
        $key = 'password';
        $cipher = "AES-128-CBC";

        $c = base64_decode($ciphertext);
        $ivlen = 0;
        $iv = '1234567890123456';
        $hmac = substr($c, $ivlen, $sha2len=32);
        $ciphertext_raw = substr($c, $ivlen+$sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
        if (hash_equals($hmac, $calcmac))// timing attack safe comparison
        {
            return $original_plaintext;
        }
        return $ciphertext;
    }

    public function attrFromX($key, $default = null) {
        if ($this->x) {
            return Arr::get($this->x, $key, $default);
        }
        return $default;
    }

    public static function extendIndexingQueryChunk($query, $chunkSize, \Closure $callback) {
        return $query->chunk($chunkSize, $callback);
    }
    
    public static function getORMModel() {
        return static::$ORMModel;
    }

    public function get2ndKeyName() {
        return null;
    }

    public static function ormRelations() {
        return null;
    }

    public function orm2ElsData($ormItem) {
        return $ormItem->toArray();
    }

    public function getTable() {
        if (static::$elsIndexName) {
            return static::$elsIndexName;
        }
        if ($this->table) {
            return $this->table;
        }
        if ($ormClass = static::$ORMModel) {
            return with(new $ormClass)->getTable();
        }
        return parent::getTable();
    }
    
    public function getColumns() {
        $columns = [];
        if ($ormClass = static::$ORMModel) {
            $ormItem = new $ormClass;
            $tableName = $ormItem->getTable();
            $schemaBuilder = Schema::connection($ormItem->getConnectionName());
            $columnNames = $schemaBuilder->getColumnListing($tableName);
            $columns = [];
            foreach($columnNames as $name) {
                try {
                    $type = $schemaBuilder->getColumnType($tableName, $name);
                } catch (DBALException $e) {
                    $type = 'string';
                }
                $columns[] = compact('name', 'type');
            }
        }
        return collect($columns);
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    public function getSchema() {
        $params = [
            'index' => $this->getTable(),
            'type' => '_doc'
        ];

        // Update the index mapping
        $result = $this->getConnection()->elsAdapter()->indices()->getMapping($params);

        return $result;
    }
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new EloquentBuilder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        return new Builder($conn, $grammar, $conn->getPostProcessor());
    }

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        parent::setKeysForSaveQuery($query);
        $baseQuery = $query->getQuery();
        $baseQuery->keyValue = $this->getKeyForSaveQuery();
        return $query;
    }

    public function getAttribute($key)
    {
        if (Str::contains($key, '.')) {
            return Arr::get($this->attributes, $key);
        }
        return parent::getAttribute($key);
    }

    protected function newHasOne(BaseBuilder $query, BaseModel $parent, $foreignKey, $localKey)
    {
        return new Relations\HasOne($query, $parent, $foreignKey, $localKey);
    }

    protected function newHasMany(BaseBuilder $query, BaseModel $parent, $foreignKey, $localKey)
    {
        return new Relations\HasMany($query, $parent, $foreignKey, $localKey);
    }

    public function newFromBuilder($attributes = [], $connection = null)
    {
        $realAttributes = $attributes['_source'] ?? [];
        foreach($this->_encryptColumns ?? [] as $k) {
            if ($v = $realAttributes[$k]) {
                $realAttributes[$k] = $this->_decryptData($v);
            }
        }
        $realAttributes['_id'] = $attributes['_id'];
        $model = parent::newFromBuilder($realAttributes, $connection);
        $model->setTable($attributes['_index']);
        return $model;
    }

    public function getAttributes()
    {
        $attributes = $this->attributes;
        if ($this->_performAction && !empty($this->_encryptColumns)) {
            foreach($this->_encryptColumns as $k) {
                if ($v = $attributes[$k] ?? false) {
                    $attributes[$k] = $this->_encryptData($v);
                } 
            }
        }

        return $attributes;
    }

    public function getDirty()
    {
        return $this->getAttributes();
    }

    protected function performInsert(BaseBuilder $query)
    {
        $this->_performAction = true;
        $ret = parent::performInsert($query);
        $this->_performAction = false;
        return $ret;
    }

    protected function performUpdate(BaseBuilder $query)
    {
        $this->_performAction = true;
        $ret = parent::performUpdate($query);
        $this->_performAction = false;
        return $ret;
    }
}
