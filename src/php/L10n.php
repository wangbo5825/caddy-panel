<?php
declare(strict_types=1);

namespace CaddyPanel;

final class L10n
{
    private const MESSAGES = [
        'en' => [
            'not_authenticated' => 'Not signed in or session expired',
            'csrf_failed' => 'CSRF validation failed',
            'rate_limited' => 'Too many failed attempts — try again in 15 minutes',
            'bad_credentials' => 'Invalid username or password',
            'account_exists' => 'An admin account already exists; re-initialization is not allowed',
            'setup_token_invalid' => 'Setup token is invalid',
            'username_invalid' => 'Username may only contain letters, digits, _ . - (2-32 characters)',
            'password_short' => 'Password must be at least 8 characters',
            'account_not_found' => 'Account not found',
            'password_wrong' => 'Current password is incorrect',
            'site_not_found' => 'Site not found',
            'site_name_required' => 'Site name is required',
            'site_name_taken' => 'A site with this name already exists',
            'site_name_invalid' => 'Site name may only contain letters, digits and . _ -, and must start with a letter or digit',
            'site_domain_required' => 'Site {name} requires at least one domain',
            'caddyfile_empty' => 'Caddyfile must not be empty',
            'adapt_failed' => 'Caddyfile validation failed: {detail}',
            'load_failed' => 'Caddy failed to load the config (changes rolled back): {detail}',
            'admin_api_invalid' => 'Admin API address must start with http(s):// or unix://',
            'style_invalid' => 'Unsupported site config mode',
            'snapshot_not_found' => 'Snapshot not found: {id}',
            'snapshot_invalid' => 'Snapshot is incomplete or invalid: {id}',
            'config_not_writable' => 'config.php is not writable — check permissions',
            'api_not_found' => 'API endpoint not found',
            'server_error' => 'Server error: {detail}',
        ],
        'zh' => [
            'not_authenticated' => '未登录或会话已过期',
            'csrf_failed' => 'CSRF 校验失败',
            'rate_limited' => '失败次数过多，请 15 分钟后再试',
            'bad_credentials' => '用户名或密码错误',
            'account_exists' => '管理员已存在，禁止重复初始化',
            'setup_token_invalid' => 'setup token 无效',
            'username_invalid' => '用户名仅允许字母、数字、_.-，长度 2-32',
            'password_short' => '密码至少 8 位',
            'account_not_found' => '账号不存在',
            'password_wrong' => '当前密码错误',
            'site_not_found' => '站点不存在',
            'site_name_required' => '站点名称不能为空',
            'site_name_taken' => '同名站点已存在',
            'site_name_invalid' => '站点名称仅允许字母数字及 ._- ，以字母数字开头',
            'site_domain_required' => '站点 {name} 至少需要一个域名',
            'caddyfile_empty' => 'Caddyfile 不能为空',
            'adapt_failed' => 'Caddyfile 校验失败：{detail}',
            'load_failed' => 'Caddy 加载失败（已自动回滚）：{detail}',
            'admin_api_invalid' => 'Admin API 地址需以 http(s):// 或 unix:// 开头',
            'style_invalid' => '不支持的站点配置模式',
            'snapshot_not_found' => '快照不存在：{id}',
            'snapshot_invalid' => '快照不完整或无效：{id}',
            'config_not_writable' => 'config.php 不可写，请检查权限',
            'api_not_found' => '接口不存在',
            'server_error' => '服务器错误：{detail}',
        ],
    ];

    public static function lang(): string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (preg_match('/(^|,)\s*(zh)(-[A-Za-z]+)?(\s*;|,|$)/', $header)) {
            return 'zh';
        }
        return 'en';
    }

    public static function text(string $code, array $params = [], ?string $lang = null): string
    {
        $lang = $lang ?? self::lang();
        $msg = self::MESSAGES[$lang][$code] ?? self::MESSAGES['en'][$code] ?? $code;
        foreach ($params as $k => $v) {
            $msg = str_replace('{' . $k . '}', (string) $v, $msg);
        }
        return $msg;
    }
}
