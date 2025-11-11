# RedisCache

**Lightweight pure PHP Redis client without native extensions**
Works without `redis.dll` or the PHP `Redis` extension. Supports all Redis commands, pipelines, transactions, authentication, database selection, and optional JSON serialization.

---

## 📦 Installation

Use [Composer](https://getcomposer.org/) to install:

```bash
composer require felconca/redis-cache
```

Composer will handle autoloading, so you can start using it immediately.

---

## ⚡ Usage

### Basic Usage

```php
<?php
require 'vendor/autoload.php';

use RedisCache\RedisCache;

// Create a Redis client
$redis = new RedisCache([
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'password' => null,   // optional, leave null if no password
    'database' => 0,      // optional, default DB
    'timeout'  => 2,      // optional timeout in seconds
    'json'     => true,   // optional: automatically serialize/deserialize arrays
]);

// Ping Redis
echo $redis->ping(); // PONG

// Set a key
$redis->set('foo', ['name' => 'Alice', 'age' => 30]);

// Get a key
print_r($redis->get('foo')); // ['name' => 'Alice', 'age' => 30]

// Delete a key
$redis->del('foo');
```

---

### Pipelines

```php
$redis->pipelineStart();
$redis->set('a', 1);
$redis->set('b', 2);
$redis->get('a');
$redis->get('b');
$responses = $redis->pipelineExecute();

print_r($responses); // Responses from all commands
```

---

### Transactions

```php
$redis->multi();
$redis->set('x', 100);
$redis->set('y', 200);
$responses = $redis->exec();

print_r($responses); // Results of transaction
```

---

### Advanced Options

- **AUTH**: Provide `'password' => 'yourpassword'` to connect to protected Redis.
- **SELECT DB**: Provide `'database' => 2` to select a database other than 0.
- **JSON mode**: Set `'json' => true` to automatically serialize arrays and decode them on retrieval.
- **Timeout**: Set `'timeout' => 2` to change connection timeout (seconds).
- **Auto-reconnect**: Enabled by default. Handles connection drops automatically.

---

### Example with All Options

```php
$redis = new RedisCache([
    'host'          => '127.0.0.1',
    'port'          => 6379,
    'password'      => 'myStrongPassword',
    'database'      => 1,
    'timeout'       => 3,
    'json'          => true
]);

$redis->set('user:1', ['name' => 'Bob', 'role' => 'admin']);
print_r($redis->get('user:1'));
```

---

### Closing the Connection

```php
$redis->close();
```

---

### ✅ Features

- No PHP extensions required (`redis.dll` not needed)
- Supports **all Redis commands** dynamically
- Pipelines & Transactions
- Authentication & database selection
- JSON serialization/deserialization
- Configurable timeout and auto-reconnect
- Lightweight and pure PHP
