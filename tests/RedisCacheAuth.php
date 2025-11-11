<?php
require 'vendor/autoload.php';

use YourName\RedisCache\RedisCache;

$redis = new RedisCache([
    'host'       => '127.0.0.1',
    'port'       => 6379,
    'password'   => 'myStrongPassword123',
    'database'   => 2,
    'timeout'    => 2,
    'json'       => true,
]);

echo $redis->ping(); // PONG

$redis->set('user:1', ['name' => 'Alice', 'age' => 30]);
print_r($redis->get('user:1')); // ['name' => 'Alice', 'age' => 30]

$redis->multi();
$redis->set('x', 100);
$redis->set('y', 200);
print_r($redis->exec());

$redis->close();
