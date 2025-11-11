<?php
require 'vendor/autoload.php';

use Redis\RedisCache\RedisCache;

$redis = new RedisCache([
    'timeout'       => 2,
    'persistent'    => true,
    'autoReconnect' => true,
    'json'          => true,
]);

// Basic
// echo $redis->ping(); // => PONG
// $redis->set('foo', ['name' => 'Alice', 'age' => 30]);
// print_r($redis->get('foo')); // => ['name' => 'Alice', 'age' => 30]

// // Pipeline
// $redis->pipelineStart();
// $redis->set('a', 1);
// $redis->set('b', 2);
// $redis->get('a');
// $redis->get('b');
// $responses = $redis->pipelineExecute();
// print_r($responses);

// // Transaction
// $redis->multi();
// $redis->set('x', '100');
// $redis->set('y', '200');
// $redis->exec();


$redis->set('foo', 'bar');
echo $redis->get('foo');            // => bar
$redis->lpush('numbers', 1);
$redis->lpush('numbers', 2);
print_r($redis->lrange('numbers', 0, -1)); // => [2, 1]
$redis->hset('user:1', 'name', 'Alice');
echo $redis->hget('user:1', 'name'); // => Alice