<?php

namespace App\Services\Mikrotik;

use Illuminate\Support\Facades\Log;

/**
 * RouterOS API Client
 * Handles low-level communication with Mikrotik routers
 */
class RouterOSClient
{
    protected $socket;
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $password;
    protected int $timeout;
    protected bool $connected = false;
    protected bool $debug = false;

    public function __construct(
        string $host,
        string $username,
        string $password,
        int $port = 8728,
        int $timeout = 10
    ) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->debug = config('mikrotik.debug', false);
    }

    /**
     * Connect to RouterOS API
     */
    public function connect(): bool
    {
        try {
            $this->socket = @fsockopen(
                $this->host,
                $this->port,
                $errno,
                $errstr,
                $this->timeout
            );

            if (!$this->socket) {
                throw new \Exception("Connection failed: {$errstr} ({$errno})");
            }

            stream_set_timeout($this->socket, $this->timeout);

            // Login to router
            $this->login();
            $this->connected = true;

            $this->log('Connected to ' . $this->host);

            return true;
        } catch (\Exception $e) {
            $this->log('Connection error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Login to RouterOS (API v2 - post 6.43)
     */
    protected function login(): void
    {
        // Try new login method first (RouterOS 6.43+)
        $response = $this->send([
            '/login',
            '=name=' . $this->username,
            '=password=' . $this->password,
        ]);

        if (isset($response[0]) && $response[0] === '!done') {
            return; // Login successful
        }

        // Check for challenge (old method)
        if (isset($response[0]) && $response[0] === '!done' && isset($response[1])) {
            // Extract challenge and use MD5 login
            preg_match('/=ret=(.+)/', $response[1], $matches);
            if (!empty($matches[1])) {
                $challenge = hex2bin($matches[1]);
                $hash = md5(chr(0) . $this->password . $challenge, true);

                $response = $this->send([
                    '/login',
                    '=name=' . $this->username,
                    '=response=00' . bin2hex($hash),
                ]);
            }
        }

        // Check for errors
        foreach ($response as $line) {
            if (str_starts_with($line, '!trap')) {
                throw new \Exception('Login failed: Invalid credentials');
            }
        }
    }

    /**
     * Disconnect from router
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
            $this->log('Disconnected from ' . $this->host);
        }
    }

    /**
     * Send command to router
     */
    public function send(array $command): array
    {
        if (!$this->socket) {
            throw new \Exception('Not connected to router');
        }

        // Send command
        foreach ($command as $word) {
            $this->writeWord($word);
        }
        $this->writeWord(''); // End of sentence

        // Read response
        return $this->read();
    }

    /**
     * Execute API command and return parsed result
     */
    public function command(string $cmd, array $params = [], ?string $tag = null): array
    {
        $sentence = [$cmd];

        foreach ($params as $key => $value) {
            if (is_numeric($key)) {
                $sentence[] = $value;
            } else {
                $sentence[] = '=' . $key . '=' . $value;
            }
        }

        if ($tag) {
            $sentence[] = '.tag=' . $tag;
        }

        $response = $this->send($sentence);

        // Debug: log raw response
        Log::info('[RouterOS] Command: ' . $cmd, ['raw_response' => $response]);

        return $this->parseResponse($response);
    }

    /**
     * Parse API response into array of items
     */
    protected function parseResponse(array $response): array
    {
        $result = [];
        $current = [];

        foreach ($response as $line) {
            if ($line === '!re') {
                if (!empty($current)) {
                    $result[] = $current;
                }
                $current = [];
            } elseif ($line === '!done') {
                if (!empty($current)) {
                    $result[] = $current;
                }
                break;
            } elseif ($line === '!trap') {
                // Error response
                continue;
            } elseif (str_starts_with($line, '=')) {
                // Parse key=value
                $line = substr($line, 1);
                $pos = strpos($line, '=');
                if ($pos !== false) {
                    $key = substr($line, 0, $pos);
                    $value = substr($line, $pos + 1);
                    $current[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Write a word to socket
     */
    protected function writeWord(string $word): void
    {
        $len = strlen($word);

        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF));
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        } elseif ($len < 0x10000000) {
            $len |= 0xE0000000;
            fwrite($this->socket, chr(($len >> 24) & 0xFF));
            fwrite($this->socket, chr(($len >> 16) & 0xFF));
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0));
            fwrite($this->socket, chr(($len >> 24) & 0xFF));
            fwrite($this->socket, chr(($len >> 16) & 0xFF));
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        }

        fwrite($this->socket, $word);

        $this->log("TX: {$word}", 'debug');
    }

    /**
     * Read response from socket
     */
    protected function read(): array
    {
        $response = [];

        while (true) {
            $word = $this->readWord();

            if ($word === false || $word === '') {
                break;
            }

            $response[] = $word;

            $this->log("RX: {$word}", 'debug');

            // Check for end of response
            if ($word === '!done' || $word === '!fatal') {
                break;
            }
        }

        return $response;
    }

    /**
     * Read a word from socket
     */
    protected function readWord()
    {
        $byte = fread($this->socket, 1);

        if ($byte === false || strlen($byte) === 0) {
            return false;
        }

        $len = ord($byte);

        if ($len < 0x80) {
            // 1 byte length
        } elseif ($len < 0xC0) {
            // 2 byte length
            $len = (($len & 0x3F) << 8) + ord(fread($this->socket, 1));
        } elseif ($len < 0xE0) {
            // 3 byte length
            $len = (($len & 0x1F) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        } elseif ($len < 0xF0) {
            // 4 byte length
            $len = (($len & 0x0F) << 24) + (ord(fread($this->socket, 1)) << 16) +
                (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        } elseif ($len === 0xF0) {
            // 5 byte length
            $len = (ord(fread($this->socket, 1)) << 24) + (ord(fread($this->socket, 1)) << 16) +
                (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        }

        if ($len === 0) {
            return '';
        }

        $word = '';
        while (strlen($word) < $len) {
            $chunk = fread($this->socket, $len - strlen($word));
            if ($chunk === false) {
                break;
            }
            $word .= $chunk;
        }

        return $word;
    }

    /**
     * Log message
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if (!$this->debug && $level === 'debug') {
            return;
        }

        Log::channel(config('mikrotik.log_channel', 'daily'))->$level("[RouterOS] {$message}");
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== null;
    }

    /**
     * Get host
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Destructor - ensure socket is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
