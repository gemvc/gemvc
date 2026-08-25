<?php

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Loopback PHP built-in server that returns `?code=` as the HTTP status.
 */
final class LocalHttpStatusServer
{
    /** @var resource|false */
    private $process = false;

    private string $routerFile;

    private int $port;

    /** @param resource $process */
    private function __construct(int $port, string $routerFile, $process)
    {
        $this->port = $port;
        $this->routerFile = $routerFile;
        $this->process = $process;
    }

    public static function start(): self
    {
        $routerFile = sys_get_temp_dir() . '/gemvc-http-status-stub-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.php';
        $written = file_put_contents($routerFile, <<<'PHP'
<?php
$code = (int) ($_GET['code'] ?? 200);
if ($code < 100 || $code > 599) {
    $code = 200;
}
http_response_code($code);
header('Content-Type: text/plain; charset=UTF-8');
echo 'status-' . $code;
PHP);
        if ($written === false) {
            throw new \RuntimeException('Could not write local HTTP status stub');
        }

        $port = self::allocatePort();
        $cmd = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $port,
            $routerFile,
        ];
        $log = sys_get_temp_dir() . '/gemvc-http-status-stub-' . getmypid() . '.log';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $log, 'a'],
            2 => ['file', $log, 'a'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($routerFile);
            throw new \RuntimeException('Could not start local HTTP status stub');
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $server = new self($port, $routerFile, $process);
        try {
            $server->waitUntilReady();
        } catch (\Throwable $e) {
            $server->stop();
            throw $e;
        }

        return $server;
    }

    public function urlForStatus(int $code): string
    {
        return 'http://127.0.0.1:' . $this->port . '/?code=' . $code;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            $deadline = microtime(true) + 2.0;
            while (microtime(true) < $deadline) {
                $status = proc_get_status($this->process);
                if ($status === false || $status['running'] !== true) {
                    break;
                }
                usleep(20000);
            }
            $status = proc_get_status($this->process);
            if (is_array($status) && $status['running'] === true) {
                proc_terminate($this->process, 9);
            }
            proc_close($this->process);
            $this->process = false;
        }
        if (is_file($this->routerFile)) {
            @unlink($this->routerFile);
        }
    }

    public function __destruct()
    {
        $this->stop();
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $status = is_resource($this->process) ? proc_get_status($this->process) : false;
            if ($status === false || $status['running'] !== true) {
                throw new \RuntimeException('Local HTTP status stub exited before becoming ready');
            }
            $socket = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            usleep(30000);
        }

        throw new \RuntimeException('Local HTTP status stub did not become ready');
    }

    private static function allocatePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new \RuntimeException('Cannot allocate loopback port: ' . $errstr);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($name) || !str_contains($name, ':')) {
            throw new \RuntimeException('Cannot parse allocated loopback port');
        }
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);
        if ($port < 1) {
            throw new \RuntimeException('Invalid allocated loopback port');
        }

        return $port;
    }
}
