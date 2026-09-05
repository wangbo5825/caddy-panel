# caddy-panel v0.8.0

A Caddy web management interface built with standard PHP 8.1+ and Vue 3.
Manage sites as `caddy.d` fragments, validate and hot-reload through the Caddy
Admin API, and recover from broken configurations without the web UI.

## Highlights

- Site management as independent `caddy.d/*.caddy` fragments, imported by the
  generated Caddyfile (single-file concat mode available as a fallback)
- Save flow: `/adapt` validation → `/load` hot reload → automatic rollback on
  failure; full snapshots plus a `last-good` copy before every apply
- Auth: local auto-login for 127.0.0.1/::1 and a single admin account with
  password, setup token, rate limiting and CSRF protection
- Bilingual UI (English default, Chinese available), CLI and installer
- Admin API over TCP (default `http://127.0.0.1:2019`) or Unix socket
  (`unix:///run/caddy-admin.sock`)
- Dashboard, sites, Caddyfile editor, certificates, logs, settings
- Emergency recovery CLI: `snapshot`, `restore --list/--latest/--id/--last-good`,
  `doctor [--fix]` — works while FrankenPHP/Caddy is stopped
- Boot-time self check examples for systemd (`ExecStartPre`) and Docker
  entrypoint

## Installation

- [docs/INSTALL.md](docs/INSTALL.md) — standalone PHP and FrankenPHP
- [docs/RECOVERY.md](docs/RECOVERY.md) — emergency configuration recovery

## Quick start (development)

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

First visit opens `install.php` to initialize the admin account and config.
Frontend sources live in `web/` (`npm install && npm run build`); built assets
are included under `public/assets/`.

---

# caddy-panel v0.8.0（中文）

基于标准 PHP 8.1+ 与 Vue 3 的 Caddy Web 管理界面。站点以 `caddy.d` 片段管理，
通过 Caddy Admin API 校验与热加载；配置损坏时无需 Web 界面即可恢复。

## 主要特性

- 站点作为独立 `caddy.d/*.caddy` 片段，由生成的主 Caddyfile import（另提供
  单文件拼接兜底模式）
- 保存流程：`/adapt` 预校验 → `/load` 热加载 → 失败自动回滚；每次应用前保留
  全量快照与 `last-good` 上次可用配置
- 认证：127.0.0.1/::1 本地免认证 + 单管理员密码、setup token、登录限速与 CSRF
- 中英双语（英语为缺省）：界面、CLI、安装向导与 API 消息
- Admin API 支持 TCP（默认 `http://127.0.0.1:2019`）与 Unix socket
  （`unix:///run/caddy-admin.sock`）
- 仪表盘、站点管理、Caddyfile 编辑、证书、日志、设置
- 紧急恢复 CLI：`snapshot`、`restore --list/--latest/--id/--last-good`、
  `doctor [--fix]`——FrankenPHP/Caddy 停止时同样可用
- systemd `ExecStartPre` 与 Docker entrypoint 启动前自检示例

## 安装与恢复

- [docs/INSTALL.zh-CN.md](docs/INSTALL.zh-CN.md) —— 独立 PHP 与 FrankenPHP 安装
- [docs/RECOVERY.zh-CN.md](docs/RECOVERY.zh-CN.md) —— 紧急配置恢复

## 快速开始（开发）

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

首次访问进入 `install.php` 完成初始化。前端源码在 `web/`
（`npm install && npm run build`），构建产物已包含于 `public/assets/`。
