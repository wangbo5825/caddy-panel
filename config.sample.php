<?php
/**
 * caddy-panel 配置样例。复制为 APP_ROOT/config.php 后按需修改。
 * 安装向导（install.php）会自动生成 config.php。
 */
return [
    'auth' => [
        // 127.0.0.1 / ::1 访问时自动进入管理员
        'allow_local_auto_login' => true,
        // 远程首次初始化所需的一次性 token（也可放置 var/setup.token 文件）
        'setup_token' => '',
    ],
    'caddy' => [
        // Admin API 地址（Caddyfile 全局 admin 指令优先于 CADDY_ADMIN 环境变量）
        'admin_api' => 'http://127.0.0.1:2019',
        'caddyfile' => APP_ROOT . '/etc/Caddyfile',
        'fragments_dir' => APP_ROOT . '/etc/caddy.d',
        'global_file' => APP_ROOT . '/etc/global.caddy',
        // Caddy 数据目录（证书/存储），留空则按默认位置探测
        'data_dir' => '',
        // Caddy 结构化访问日志文件，留空则日志页提示未配置
        'log_file' => '',
    ],
    'panel' => [
        'name' => 'caddy-panel',
        // 独立部署（php -S）监听地址
        'listen' => '127.0.0.1:8080',
    ],
];
