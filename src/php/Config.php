<?php
declare(strict_types=1);

namespace CaddyPanel;

final class Config
{
    private const DEFAULTS = [
        'auth' => [
            'allow_local_auto_login' => true,
            'setup_token' => '',
        ],
        'caddy' => [
            'admin_api' => 'http://127.0.0.1:2019',
            'caddyfile' => '',
            'fragments_dir' => '',
            'global_file' => '',
            'data_dir' => '',
            'log_file' => '',
        ],
        'panel' => [
            'name' => 'caddy-panel',
            'listen' => '127.0.0.1:8080',
        ],
    ];

    public function __construct(
        private string $appRoot,
        private array $data
    ) {
    }

    public static function load(string $appRoot): self
    {
        $path = $appRoot . '/config.php';
        $data = is_file($path) ? (array) include $path : [];
        $merged = self::mergeDefaults($data);
        return new self($appRoot, $merged);
    }

    public static function mergeDefaults(array $data): array
    {
        $merged = self::DEFAULTS;
        foreach ($data as $k => $v) {
            if (is_array($v) && isset($merged[$k]) && is_array($merged[$k])) {
                $merged[$k] = array_merge($merged[$k], $v);
            } else {
                $merged[$k] = $v;
            }
        }
        return $merged;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $cur = $this->data;
        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) {
                return $default;
            }
            $cur = $cur[$p];
        }
        return $cur;
    }

    public function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $ref = &$this->data;
        foreach ($parts as $p) {
            if (!isset($ref[$p]) || !is_array($ref[$p])) {
                $ref[$p] = [];
            }
            $ref = &$ref[$p];
        }
        $ref = $value;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function appRoot(): string
    {
        return $this->appRoot;
    }

    public function caddyfilePath(): string
    {
        return $this->get('caddy.caddyfile') ?: $this->appRoot . '/etc/Caddyfile';
    }

    public function fragmentsDir(): string
    {
        return $this->get('caddy.fragments_dir') ?: $this->appRoot . '/etc/caddy.d';
    }

    public function globalFile(): string
    {
        return $this->get('caddy.global_file') ?: $this->appRoot . '/etc/global.caddy';
    }

    public function save(): bool
    {
        $path = $this->appRoot . '/config.php';
        $body = "<?php\n/**\n * caddy-panel 配置（由设置页 / 安装向导生成）。\n */\nreturn " . var_export($this->data, true) . ";\n";
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $body) === false) {
            return false;
        }
        if (is_file($path)) {
            @unlink($path);
        }
        if (!@rename($tmp, $path)) {
            return @copy($tmp, $path) && @unlink($tmp);
        }
        return true;
    }
}
