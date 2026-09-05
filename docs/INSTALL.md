# Installation guide

Language: English (default). 中文版：[INSTALL.zh-CN.md](INSTALL.zh-CN.md).

caddy-panel is a PHP 8.1+ application that can run in two deployment shapes:

1. **Standalone PHP** — the panel runs under any PHP server (PHP-FPM + nginx /
   Apache / `php -S`) and talks to a Caddy/FrankenPHP Admin API over TCP or a
   Unix socket.
2. **FrankenPHP** — the panel is served by the very FrankenPHP instance it
   manages (FRAMPP-style, caddy.d + import layout).

Both shapes use the same two-zone layout:

```text
<document root>        # only public/ contents (index.php, install.php, assets)
APP_ROOT/              # src/ etc/ var/ bin/ config.php  (outside the web root)
```

## Requirements

- PHP 8.1+ with `pdo_sqlite`, `openssl`, `curl`, `zip`
- A reachable Caddy Admin API (TCP `127.0.0.1:2019` by default, or a Unix
  socket such as `unix:///run/caddy-admin.sock`)
- Writable `APP_ROOT/var` and `APP_ROOT/etc` for the PHP process user

## A. Standalone PHP installation

1. Upload the `public/` contents to your document root, and place
   `src/ etc/ var/ bin/` at `APP_ROOT` (FTP / file manager is enough).
2. Edit `panel.bootstrap.php` in the document root: set `APP_ROOT` to the
   absolute path above.
3. Open the site in a browser — `install.php` checks the environment and
   creates `config.php`, `var/`, the admin account and `var/installed`.
4. Choose the Admin API connection in Settings:
   - **TCP (default):** `http://127.0.0.1:2019`
   - **Unix socket:** `unix:///run/caddy-admin.sock` (same host only)
5. The PHP process user must be able to write `var/` and reach the Admin API
   (Unix sockets additionally require read/write permission on the socket
   file, e.g. add the PHP user to the `caddy` group).

### Development run

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

### Production example (nginx + PHP-FPM)

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

## B. FrankenPHP installation

1. Prepare the same layout under `APP_ROOT`, e.g. `/srv/caddy-panel`.
2. Write an initial `APP_ROOT/etc/Caddyfile` that serves the installer (the
   panel will take over this file after installation):

```caddy
{
    # default TCP admin API (standard); or use a Unix socket:
    # admin unix//run/caddy-admin.sock
    admin 127.0.0.1:2019
}

panel.example.com {
    root * /srv/caddy-panel/public
    php_server
}
```

3. Start FrankenPHP with that file (working directory = `APP_ROOT`):

```bash
cd /srv/caddy-panel
frankenphp run --config etc/Caddyfile --adapter caddyfile
```

4. Open `http://panel.example.com` and complete the `install.php` wizard
   (creates `config.php`, `var/`, the admin account).
5. Add the panel's own site through the UI as a **PHP site** (domain
   `panel.example.com`, root `public`) so it is managed as a `caddy.d`
   fragment — the generated main Caddyfile imports
   `caddy.d/*.caddy`, and `/load` hot-reloads it.
6. Register the boot-time self check (config broken → FrankenPHP would not
   start, so check before starting):

   - systemd `ExecStartPre=/usr/bin/php /srv/caddy-panel/bin/panel doctor --fix`
     (see `scripts/caddy-panel.service.example`)
   - or Docker entrypoint `scripts/caddy-panel-entrypoint.sh`

### Notes for FrankenPHP

- PHP and Caddy share the process, so PHP can reach a Unix-socket Admin API
  automatically (`getcwd()` is the FrankenPHP working directory).
- When the panel is accessed locally, remote-IP detection works directly;
  behind another reverse proxy, configure `trusted_proxies` so local auto
  login behaves as expected.

## Emergency recovery

If a bad configuration prevents FrankenPHP from starting, the web UI is
unreachable too. Full snapshots and a last-known-good copy are kept under
`var/`; recover without the web server:

```bash
php bin/panel doctor --fix          # auto roll back to last-good if broken
php bin/panel restore --list
php bin/panel restore --last-good
```

Full instructions: [RECOVERY.md](RECOVERY.md).
