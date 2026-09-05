# 安装指南

语言：中文。English: [INSTALL.md](INSTALL.md)。

caddy-panel 是 PHP 8.1+ 应用，支持两种部署形态：

1. **独立 PHP**——面板运行在任意 PHP 服务器（PHP-FPM + nginx / Apache /
   `php -S`），通过 TCP 或 Unix socket 访问 Caddy/FrankenPHP 的 Admin API。
2. **FrankenPHP**——面板由它所管理的 FrankenPHP 自身提供服务
   （FRAMPP 风格，caddy.d + import 布局）。

两种形态使用相同的两区域布局：

```text
<站点根目录>            # 只放 public/ 的内容（index.php、install.php、assets）
APP_ROOT/               # src/ etc/ var/ bin/ config.php（webroot 之外）
```

## 要求

- PHP 8.1+（`pdo_sqlite`、`openssl`、`curl`、`zip`）
- 可达的 Caddy Admin API（默认 TCP `127.0.0.1:2019`，或 Unix socket
  如 `unix:///run/caddy-admin.sock`）
- PHP 进程用户可写 `APP_ROOT/var` 与 `APP_ROOT/etc`

## A. 独立 PHP 安装

1. 把 `public/` 内容上传到站点根目录；`src/ etc/ var/ bin/` 放到
   `APP_ROOT`（FTP / 文件管理器即可）。
2. 编辑站点根目录下的 `panel.bootstrap.php`，把 `APP_ROOT` 设为上面的绝对路径。
3. 浏览器访问站点——`install.php` 会做环境检查，并创建 `config.php`、
   `var/`、管理员账号与 `var/installed`。
4. 在设置中选择 Admin API 连接：
   - **TCP（默认）：** `http://127.0.0.1:2019`
   - **Unix socket：** `unix:///run/caddy-admin.sock`（仅同机）
5. PHP 进程用户需可写 `var/` 并可达 Admin API（Unix socket 还需对 socket
   文件有读写权限，例如把 PHP 用户加入 `caddy` 组）。

### 开发运行

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

### 生产示例（nginx + PHP-FPM）

```nginx
server {
    listen 80;
    server_name panel.example.com;
    root /srv/caddy-panel/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## B. FrankenPHP 安装

1. 在 `APP_ROOT`（例如 `/srv/caddy-panel`）准备好同样布局。
2. 先手写一份用于启动安装向导的 `APP_ROOT/etc/Caddyfile`（安装完成后面板
   会接管该文件）：

```caddy
{
    # 默认 TCP admin API（标准）；也可改用 Unix socket：
    # admin unix//run/caddy-admin.sock
    admin 127.0.0.1:2019
}

panel.example.com {
    root * /srv/caddy-panel/public
    php_server
}
```

3. 用该文件启动 FrankenPHP（工作目录 = `APP_ROOT`）：

```bash
cd /srv/caddy-panel
frankenphp run --config etc/Caddyfile --adapter caddyfile
```

4. 打开 `http://panel.example.com`，完成 `install.php` 向导（创建
   `config.php`、`var/`、管理员账号）。
5. 在界面中把面板自身站点添加为 **PHP 站点**（域名
   `panel.example.com`，根目录 `public`），使其成为 `caddy.d` 片段统一管理
   ——生成的 Caddyfile 通过 import 引入 `caddy.d/*.caddy` 并热加载。
6. 注册启动前自检（配置损坏会导致 FrankenPHP 无法启动，必须先检查再启动）：

   - systemd `ExecStartPre=/usr/bin/php /srv/caddy-panel/bin/panel doctor --fix`
     （见 `scripts/caddy-panel.service.example`）
   - 或 Docker entrypoint `scripts/caddy-panel-entrypoint.sh`

### FrankenPHP 注意事项

- PHP 与 Caddy 同进程，PHP 可自动访问 Unix socket Admin API（`getcwd()` 即
  FrankenPHP 工作目录）。
- 本机直接访问时远程 IP 判定正常；若前面还有反向代理，需配置
  `trusted_proxies`，本地免认证才会按预期工作。

## 紧急恢复

配置损坏导致 FrankenPHP 无法启动时，Web 界面同样不可达。面板会在 `var/`
保存全量快照与"上次可用配置"，可在无 Web 服务时恢复：

```bash
php bin/panel doctor --fix          # 损坏时自动回滚到 last-good
php bin/panel restore --list
php bin/panel restore --last-good
```

完整说明：[RECOVERY.zh-CN.md](RECOVERY.zh-CN.md)。
