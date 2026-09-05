# caddy-panel

A Caddy web management interface built with standard PHP and Vue 3 (design:
[docs/caddy-webui-plan.md](docs/caddy-webui-plan.md), Chinese version:
[README.zh-CN.md](README.zh-CN.md)).

Installation (standalone PHP and FrankenPHP): [docs/INSTALL.md](docs/INSTALL.md).
Emergency recovery: [docs/RECOVERY.md](docs/RECOVERY.md).

The interface, CLI, installer and API messages are bilingual with **English as
the default language** (Chinese is available). The UI has a language switcher
in the top bar; the CLI and installer use English unless `--lang=zh`,
`CADDY_PANEL_LANG=zh` or `?lang=zh` is given.

## Quick start (development)

Requirements: PHP 8.1+ with `pdo_sqlite`, `openssl`, `curl`, `zip`.

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

The first visit opens `install.php`, which checks the environment, validates
APP_ROOT, and creates `config.php`, `var/` and the admin account.

For production, follow the webroot + APP_ROOT layout (plan §9): put the
`public/` contents in the document root, place `src/ etc/ var/ bin/` at
`APP_ROOT`, and set that path in `public/panel.bootstrap.php`.

## CLI (optional, requires server shell)

```bash
php bin/panel help
php bin/panel passwd
php bin/panel setup
```

## Frontend

```bash
cd web && npm install && npm run build
```

Build output goes to `public/`.

## Emergency recovery

If FrankenPHP/Caddy cannot start because of a bad configuration, the web UI is
unreachable too. The panel keeps full snapshots and a last-known-good copy;
recover without the web server via:

```bash
php bin/panel doctor --fix
php bin/panel restore --list
php bin/panel restore --last-good
```

See [docs/RECOVERY.md](docs/RECOVERY.md).

## Development without Caddy

`dev/mock-admin.php` simulates the Caddy Admin API (`/adapt`, `/load`,
`/config/`) so the panel flow can be tested locally:

```bash
php -S 127.0.0.1:2019 dev/mock-admin.php
```

Stop the mock before connecting to a real Caddy on the same port.
