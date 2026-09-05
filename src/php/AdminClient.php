<?php
declare(strict_types=1);

namespace CaddyPanel;

final class AdminClient
{
    private ?string $socketPath;
    private string $httpBase;

    public function __construct(string $base)
    {
        if (preg_match('#^unix://(.+)$#i', $base, $m)) {
            // Caddy admin over unix socket（如 admin unix//run/caddy-admin.sock）
            $this->socketPath = rtrim(trim($m[1]), '/');
            $this->httpBase = 'http://localhost';
        } else {
            $this->socketPath = null;
            $this->httpBase = rtrim($base, '/');
        }
    }

    public function adapt(string $caddyfile): array
    {
        return $this->send('POST', '/adapt', ['Content-Type: text/caddyfile'], $caddyfile);
    }

    public function load(string $caddyfile): array
    {
        return $this->send('POST', '/load', ['Content-Type: text/caddyfile'], $caddyfile);
    }

    public function config(): array
    {
        return $this->send('GET', '/config/');
    }

    public function upstreams(): array
    {
        return $this->send('GET', '/reverse_proxy/upstreams');
    }

    public function send(string $method, string $path, array $headers = [], ?string $body = null): array
    {
        $ch = curl_init($this->httpBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ]);
        if ($this->socketPath !== null) {
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $this->socketPath);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $start = microtime(true);
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $latency = (int) round((microtime(true) - $start) * 1000);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => $err ?: '无法连接 Admin API', 'latency' => $latency];
        }
        $decoded = json_decode($resp, true);
        $data = ($decoded !== null) ? $decoded : $resp;
        $ok = $status >= 200 && $status < 300;
        $error = $ok ? '' : (is_array($data) && isset($data['error']) ? (string) $data['error'] : $resp);
        return ['ok' => $ok, 'status' => $status, 'body' => $data, 'error' => $error, 'latency' => $latency];
    }
}
