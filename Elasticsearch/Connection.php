<?php

namespace Yong\ElasticSuit\Elasticsearch;


use Closure;
use Config;
use Illuminate\Support\Arr;
use Elasticsearch\ClientBuilder;
use Illuminate\Database\Connection as BaseConnection;

use Yong\ElasticSuit\Elasticsearch\Query\Grammar\Grammar;
use Yong\ElasticSuit\Elasticsearch\Query\Processors\Processor;


/**
 * Relational DB -> Databases -> Tables -> Rows -> Columns
 * Elasticsearch -> Indices   -> Types  -> Documents -> Fields
 */
class Connection extends BaseConnection
{
    /**
     * The active Elasticsearch Client
     * @var Elasticsearch\Client
     */
    protected $elasticsearch_client;

    /**
     * insert id
     *
     * @var        string
     */
    private $_lastInsertId = '';


    public function __construct($database = '', $tablePrefix = '', array $config = []) {
        $this->elasticsearch_client = null;
        parent::__construct(null, $database, $tablePrefix, $config);
    }

    private function notSupport($keyword) {
        throw new RuntimeException(sprintf(' "%s" is not supported by Elasticsearch Connection', $keyword));
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\SQLiteGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new Grammar);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\SQLiteGrammar
     */
    protected function getDefaultSchemaGrammar()
    {

        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\SQLiteProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new Processor;
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \Doctrine\DBAL\Driver\PDOSqlite\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return elasticsearch_client
     */
    protected function connect()
    {
        if (is_null($this->elasticsearch_client)) {
            $singleHandler = ClientBuilder::singleHandler();
            $this->elasticsearch_client  = ClientBuilder::create()
                ->setHosts($this->getConfig('hosts'))
                ->setHandler($singleHandler)
                ->build();
        }
        return $this->elasticsearch_client;
    }


    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @return \Yong\ElasticSuit\Elasticsearch\Query\Builder
     */
    public function table($table) {
        return $this->query()->from($table);
    }

    /**
     * Get a new raw query expression.
     *
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Expression
     */
    public function raw($value)
    {
        $this->notSupport('raw');
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param  array  $query
     * @param  array   $bindings
     * @return mixed
     */
    public function selectOne($query, $bindings = []) {
        $query['size'] = 1;
        return $this->select($query, $bindings);
    }

    /**
     * Run a select statement against the database.
     *
     * @param  array  $query
     * @param  array   $bindings
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true) {
        return $this->connect()->search($query);
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $des   //use as description
     * @param  array   $data
     * @return bool
     */
    public function insert($des, $data=[]) {
        $result = $this->connect()->index($data);
        if (isset($result['result']) && in_array($result['result'], ['updated', 'created'])) {
            $this->_lastInsertId = $result['_id'];
            return true;
        }
        $this->_lastInsertId = '';
        return false;
    }

    public function lastInsertId($sequence) {
        return $this->_lastInsertId;
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $des   //use as description
     * @param  array   $data
     * @return int
     */
    public function update($des, $data = []) {
        // $data = [
        //     'index'=> $this->indicesName,
        //     'type'=> $type,
        //     'body'=> ['doc'=>$data],
        //     'id' => $docId
        // ]
        return $this->connect()->update($data);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $des
     * @param  array   $data
     * @return int
     */
    public function delete($des, $data = []) {
        // $data = [
        //     'index'=> $this->indicesName,
        //     'type'=> $type,
        //     'id' => $docId
        // ];
        return $this->connect()->delete($data);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = []) {
        $this->notSupport('statement');
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = []) {
        $this->notSupport('affectingStatement');
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query){
        $this->notSupport('unprepared');
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings) {
        $this->notSupport('prepareBindings');
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1){
        $this->notSupport('transaction');
    }

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction() {
        $this->notSupport('beginTransaction');
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit() {
        $this->notSupport('commit');
    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack() {
        $this->notSupport('rollBack');
    }

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel() {
        $this->notSupport('transactionLevel');
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  \Closure  $callback
     * @return array
     */
    public function pretend(Closure $callback) {
        $this->notSupport('pretend');
    }
}
