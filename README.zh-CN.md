# caddy-panel

基于标准 PHP 的 Caddy Web 管理界面（设计见
[docs/caddy-webui-plan.md](docs/caddy-webui-plan.md)，English:
[README.md](README.md)）。

安装（独立 PHP 与 FrankenPHP）：[docs/INSTALL.zh-CN.md](docs/INSTALL.zh-CN.md)。
紧急恢复：[docs/RECOVERY.zh-CN.md](docs/RECOVERY.zh-CN.md)。

界面、CLI、安装向导与 API 消息均为中英双语，**英语为缺省语言**：界面右上角
可切换语言；CLI 与安装向导默认英语，使用 `--lang=zh` /
`CADDY_PANEL_LANG=zh` / `?lang=zh` 切换中文。

## 快速开始（开发）

要求：PHP 8.1+（`pdo_sqlite`、`openssl`、`curl`、`zip`）。

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

首次访问进入 `install.php` 安装向导（环境检查、APP_ROOT 校验，创建
`config.php`、`var/` 与管理员账号）。生产部署请按计划文档 §9 的 webroot +
APP_ROOT 布局：webroot 放 `public/` 内容，`src/ etc/ var/ bin/` 放置到
`APP_ROOT`，并在 `public/panel.bootstrap.php` 中填写路径。

## CLI（可选，需服务器 shell）

```bash
php bin/panel help
php bin/panel passwd
php bin/panel setup
```

## 前端

```bash
cd web && npm install && npm run build
```

构建产物输出到 `public/`。

## 紧急恢复

若配置损坏导致 FrankenPHP/Caddy 无法启动，Web 界面同样不可达。面板会保存全量快照
与"上次可用配置"，可在无 Web 服务时恢复：

```bash
php bin/panel doctor --fix
php bin/panel restore --list
php bin/panel restore --last-good
```

详见 [docs/RECOVERY.zh-CN.md](docs/RECOVERY.zh-CN.md)。

## 无 Caddy 的开发测试

`dev/mock-admin.php` 模拟 Caddy Admin API（`/adapt`、`/load`、`/config/`）：

```bash
php -S 127.0.0.1:2019 dev/mock-admin.php
```

接入同端口真实 Caddy 前请先停掉 mock。
