<?php

namespace Redis\RedisCache;

/**
 * Pure PHP Redis client — no redis.dll or phpredis extension required.
 * Features:
 *  - Dynamic commands (__call)
 *  - Connection pooling
 *  - Auto-reconnect
 *  - AUTH + SELECT support
 *  - Pipelining
 *  - Transactions
 *  - JSON serialization
 *  - Configurable timeout
 */
class RedisCache
{
    private $host;
    private $port;
    private $timeout;
    private $persistent;
    private $autoReconnect;
    private $jsonMode;
    private $password;
    private $database;

    /** @var resource|null */
    private static $pool = [];

    /** @var resource|null */
    private $socket;

    /** @var bool */
    private $inPipeline = false;

    /** @var string[] */
    private $pipelineCommands = [];

    public function __construct(array $options = [])
    {
        $this->host          = $options['host']          ?? '127.0.0.1';
        $this->port          = $options['port']          ?? 6379;
        $this->timeout       = $options['timeout']       ?? 1.0;
        $this->persistent    = $options['persistent']    ?? true;
        $this->autoReconnect = $options['autoReconnect'] ?? true;
        $this->jsonMode      = $options['json']          ?? false;
        $this->password      = $options['password']      ?? null;
        $this->database      = $options['database']      ?? 0;
    }

    /** Connect or reuse connection */
    private function connect()
    {
        $key = "{$this->host}:{$this->port}";

        if (isset(self::$pool[$key]) && is_resource(self::$pool[$key])) {
            $this->socket = self::$pool[$key];
            return;
        }

        $func = $this->persistent ? 'pfsockopen' : 'fsockopen';
        $this->socket = @$func($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new \Exception("Redis connection failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, (int)$this->timeout);
        self::$pool[$key] = $this->socket;

        // Authenticate and select DB (if configured)
        if ($this->password) {
            $resp = $this->sendCommandRaw(['AUTH', $this->password]);
            if (stripos($resp, 'OK') === false) {
                throw new \Exception("Redis authentication failed");
            }
        }

        if ($this->database !== 0) {
            $resp = $this->sendCommandRaw(['SELECT', (string)$this->database]);
            if (stripos($resp, 'OK') === false) {
                throw new \Exception("Redis database selection failed");
            }
        }
    }

    private function sendCommand(array $command)
    {
        $this->connect();

        $cmd = '*' . count($command) . "\r\n";
        foreach ($command as $arg) {
            $cmd .= '$' . strlen((string)$arg) . "\r\n" . $arg . "\r\n";
        }

        $bytes = @fwrite($this->socket, $cmd);
        if ($bytes === false) {
            if ($this->autoReconnect) {
                $this->close();
                $this->connect();
                $bytes = fwrite($this->socket, $cmd);
            } else {
                throw new \Exception("Failed to send command to Redis");
            }
        }

        if ($this->inPipeline) {
            $this->pipelineCommands[] = $command;
            return true; // defer response
        }

        return $this->readResponse();
    }

    /** Internal send (used for AUTH/SELECT before full init) */
    private function sendCommandRaw(array $command)
    {
        $cmd = '*' . count($command) . "\r\n";
        foreach ($command as $arg) {
            $cmd .= '$' . strlen((string)$arg) . "\r\n" . $arg . "\r\n";
        }

        fwrite($this->socket, $cmd);
        return $this->readResponse();
    }

    private function readResponse()
    {
        $line = fgets($this->socket);
        if ($line === false) {
            if ($this->autoReconnect) {
                $this->close();
                return null;
            }
            throw new \Exception("Redis: Failed to read response");
        }

        $type = $line[0];
        $payload = substr($line, 1, -2);

        switch ($type) {
            case '+':
                return $payload;
            case '-':
                throw new \Exception("Redis error: $payload");
            case ':':
                return (int)$payload;
            case '$':
                $len = (int)$payload;
                if ($len === -1) return null;
                $data = fread($this->socket, $len + 2);
                return substr($data, 0, -2);
            case '*':
                $count = (int)$payload;
                $items = [];
                for ($i = 0; $i < $count; $i++) {
                    $items[] = $this->readResponse();
                }
                return $items;
            default:
                throw new \Exception("Redis: Unknown response type '$type'");
        }
    }

    /** Magic call for all Redis commands */
    public function __call($method, $arguments)
    {
        $command = strtoupper($method);

        // JSON serialize for SET/HSET if enabled
        if ($this->jsonMode && in_array($command, ['SET', 'HSET'])) {
            if (isset($arguments[1]) && is_array($arguments[1])) {
                $arguments[1] = json_encode($arguments[1]);
            }
        }

        $args = array_map('strval', $arguments);
        array_unshift($args, $command);

        $result = $this->sendCommand($args);

        // JSON decode for GET/HGET if enabled
        if ($this->jsonMode && in_array($command, ['GET', 'HGET']) && is_string($result)) {
            $json = json_decode($result, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return $result;
    }

    /** Pipelining */
    public function pipelineStart()
    {
        $this->inPipeline = true;
        $this->pipelineCommands = [];
    }

    public function pipelineExecute()
    {
        $this->inPipeline = false;
        $responses = [];
        foreach ($this->pipelineCommands as $cmd) {
            $responses[] = $this->readResponse();
        }
        $this->pipelineCommands = [];
        return $responses;
    }

    /** Transactions */
    public function multi()
    {
        return $this->sendCommand(['MULTI']);
    }

    public function exec()
    {
        return $this->sendCommand(['EXEC']);
    }

    public function discard()
    {
        return $this->sendCommand(['DISCARD']);
    }

    /** Close socket */
    public function close()
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}
