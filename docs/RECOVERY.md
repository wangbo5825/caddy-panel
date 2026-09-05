# Emergency recovery

caddy-panel is usually served by the very Caddy/FrankenPHP instance it manages.
If a bad configuration is applied and Caddy can no longer start, the web UI is
unreachable too — so recovery must not depend on the web UI or the Admin API.
All recovery operations below are plain file operations and work while
FrankenPHP/Caddy is **stopped**.

## How snapshots are made

- Before every save/apply, the panel stores a **full snapshot** (main
  `Caddyfile`, `global.caddy` and every `caddy.d/*.caddy` fragment, plus a
  manifest) in `var/backups/config/<timestamp>/` (last 30 kept).
- After a **successful** `/load`, the same files are copied to
  `var/last-good/` — the "previous working configuration".

## Commands (CLI, no web server required)

```bash
# list snapshots
php bin/panel restore --list

# restore the most recent snapshot
php bin/panel restore --latest

# restore a specific snapshot
php bin/panel restore --id=20260905-143000

# restore the last configuration known to have loaded successfully
php bin/panel restore --last-good

# verify the restored config locally with the caddy/frankenphp binary (if found)
php bin/panel restore --latest --verify

# check current config integrity (structure only, no Admin API needed)
php bin/panel doctor

# check and automatically roll back to last-good when broken
php bin/panel doctor --fix
```

After a restore, start FrankenPHP/Caddy normally; no `/load` is needed because
the configuration files on disk are the source of truth.

## Boot-time self check (recommended)

### systemd

```ini
ExecStartPre=/usr/bin/php /srv/caddy-panel/bin/panel doctor --fix
```

See [scripts/caddy-panel.service.example](../scripts/caddy-panel.service.example).

### Docker / entrypoint

Use [scripts/caddy-panel-entrypoint.sh](../scripts/caddy-panel-entrypoint.sh):
it runs `doctor --fix` and refuses to start only if the config is still broken
after the automatic rollback.

## Manual recovery (no CLI)

If even the CLI is unavailable, restore by hand from
`var/backups/config/<timestamp>/` or `var/last-good/`:

```bash
cp var/last-good/Caddyfile  etc/Caddyfile
cp var/last-good/global.caddy etc/global.caddy   # if present
rm -f etc/caddy.d/*.caddy
cp var/last-good/caddy.d/*.caddy etc/caddy.d/
```

Then start FrankenPHP again.

## Notes

- `var/last-good` is only updated after a configuration has been accepted by
  the Admin API `/load`, so it always represents a known-working state.
- `doctor` performs structural checks without a network connection; real
  validation with `--verify` requires a `caddy`/`frankenphp` binary reachable
  from the CLI.
- Recovery writes files only; it never pushes configuration to a running
  Caddy (there may be none).
