# 紧急恢复

caddy-panel 通常由它所管理的 Caddy/FrankenPHP 自身提供服务：一旦应用了错误配置导致
Caddy 无法启动，Web 界面也会同时不可达。因此恢复**不能依赖 Web 界面或 Admin API**。
下面所有恢复操作都是纯文件操作，在 FrankenPHP/Caddy **停止**状态下同样可用。

## 快照如何产生

- 每次保存/应用前，面板保存一份**全量快照**（主 `Caddyfile`、`global.caddy`、
  `caddy.d/*.caddy` 全部片段与 manifest），存放在 `var/backups/config/<时间戳>/`
  （保留最近 30 份）。
- 每次 `/load` **成功**后，把同样内容复制到 `var/last-good/` —— 即"上次可用配置"。

## 命令（CLI，无需 Web 服务）

```bash
# 列出快照
php bin/panel restore --list

# 恢复最近一份快照
php bin/panel restore --latest

# 恢复指定快照
php bin/panel restore --id=20260905-143000

# 恢复上一次成功加载过的配置
php bin/panel restore --last-good

# 用本机 caddy/frankenphp 校验恢复后的配置（若找到）
php bin/panel restore --latest --verify

# 检查当前配置完整性（纯结构检查，不依赖 Admin API）
php bin/panel doctor

# 检查；损坏时自动回滚到 last-good
php bin/panel doctor --fix
```

恢复后正常启动 FrankenPHP/Caddy 即可，无需再 `/load`——磁盘上的配置文件就是事实来源。

## 启动前自检（推荐）

### systemd

```ini
ExecStartPre=/usr/bin/php /srv/caddy-panel/bin/panel doctor --fix
```

示例见 [scripts/caddy-panel.service.example](../scripts/caddy-panel.service.example)。

### Docker / entrypoint

使用 [scripts/caddy-panel-entrypoint.sh](../scripts/caddy-panel-entrypoint.sh)：
启动前执行 `doctor --fix`，仅当自动回滚后配置仍然损坏时才拒绝启动。

## 手工恢复（无 CLI）

即使没有 CLI，也可以从 `var/backups/config/<时间戳>/` 或 `var/last-good/` 手工恢复：

```bash
cp var/last-good/Caddyfile  etc/Caddyfile
cp var/last-good/global.caddy etc/global.caddy   # 若存在
rm -f etc/caddy.d/*.caddy
cp var/last-good/caddy.d/*.caddy etc/caddy.d/
```

然后重新启动 FrankenPHP。

## 说明

- `var/last-good` 只在配置被 Admin API `/load` 接受后更新，因此始终代表已知可用状态。
- `doctor` 做纯结构检查，无需网络；`--verify` 的真适配需要 CLI 能找到
  `caddy`/`frankenphp` 可执行文件。
- 恢复只写文件，绝不向运行中的 Caddy 推送配置（此时可能根本没有运行实例）。
