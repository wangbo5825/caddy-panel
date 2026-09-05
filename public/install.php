<?php
declare(strict_types=1);

require __DIR__ . '/panel.bootstrap.php';

use CaddyPanel\Auth;
use CaddyPanel\Config;
use CaddyPanel\Db;

$lang = isset($_GET['lang']) && $_GET['lang'] === 'zh' ? 'zh' : 'en';
$T = [
    'en' => [
        'title' => 'caddy-panel installer',
        'app_root' => 'APP_ROOT: {path}. If the path is wrong, edit <code>panel.bootstrap.php</code> in the webroot and refresh (this wizard only checks; it does not provide a web edit entry).',
        'locked' => 'install.php is locked: installation is complete. To reinstall, delete <code>var/installed</code> or use the CLI.',
        'env' => 'Environment check',
        'init' => 'Initial configuration',
        'username' => 'Admin username',
        'password' => 'Admin password (at least 8 characters)',
        'confirm' => 'Confirm password',
        'admin_api' => 'Admin API address (Caddy)',
        'auto_login' => 'Local auto login for 127.0.0.1 / ::1',
        'finish' => 'Finish installation',
        'locked_hint' => 'install.php is locked: installation is complete.',
        'fail_hint' => 'Environment check failed — fix the issues above first. Package acquisition modes (server download / upload) are not yet available in this build.',
        'mismatch' => 'Passwords do not match',
        'short' => 'Password must be at least 8 characters',
        'api_scheme' => 'Admin API address must start with http(s):// or unix://',
        'write_fail' => 'Could not write config.php — check APP_ROOT permissions',
        'init_fail' => 'Initialization failed: {err}',
        'switch_zh' => '中文',
        'checks' => [
            ['PHP 8.1+', 'Current {v}'],
            ['pdo_sqlite', 'Metadata, audit and log aggregation'],
            ['openssl', 'Password hashing and certificate parsing'],
            ['curl', 'Caddy Admin API client'],
            ['ZipArchive', 'Package extraction (future acquisition modes)'],
            ['APP_ROOT resolvable', 'Path: {path}'],
            ['APP_ROOT writable', 'PHP must be able to create var/ etc.'],
            ['Webroot isolation', 'APP_ROOT must not be inside the site document root'],
            ['Application code present', 'Missing src/php/autoload.php: place the app/ contents at APP_ROOT first'],
        ],
    ],
    'zh' => [
        'title' => 'caddy-panel 安装向导',
        'app_root' => 'APP_ROOT：{path}。若路径错误，请编辑 webroot 下 <code>panel.bootstrap.php</code> 后刷新（本向导只检查，不提供 Web 修改入口）。',
        'locked' => 'install.php 已锁定：系统已完成安装。如需重新安装，请删除 <code>var/installed</code> 或使用 CLI。',
        'env' => '环境检查',
        'init' => '初始化配置',
        'username' => '管理员用户名',
        'password' => '管理员密码（至少 8 位）',
        'confirm' => '确认密码',
        'admin_api' => 'Admin API 地址（Caddy）',
        'auto_login' => '本地访问（127.0.0.1 / ::1）免认证自动登录',
        'finish' => '完成安装',
        'locked_hint' => 'install.php 已锁定：系统已完成安装。',
        'fail_hint' => '环境检查未全部通过，请先修复上述问题；安装包获取模式（服务端下载 / 页面上传）将在后续版本开放。',
        'mismatch' => '两次输入的密码不一致',
        'short' => '密码至少 8 位',
        'api_scheme' => 'Admin API 地址需以 http(s):// 或 unix:// 开头',
        'write_fail' => '无法写入 config.php，请检查 APP_ROOT 权限',
        'init_fail' => '初始化失败：{err}',
        'switch_zh' => 'English',
        'checks' => [
            ['PHP 版本 ≥ 8.1', '当前 {v}'],
            ['pdo_sqlite', '元数据、审计、日志聚合'],
            ['openssl', '密码哈希与证书解析'],
            ['curl', 'Caddy Admin API 客户端'],
            ['ZipArchive', '安装包解压（后续包获取模式）'],
            ['APP_ROOT 可解析', '路径：{path}'],
            ['APP_ROOT 可写', 'PHP 进程需可创建 var/ 等目录'],
            ['webroot 隔离', 'APP_ROOT 不能位于站点根目录之内'],
            ['应用代码存在', 'src/php/autoload.php 缺失：请先把 app/ 内容放置到 APP_ROOT'],
        ],
    ],
];
$M = $T[$lang];

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function tpl(string $s, array $p): string
{
    foreach ($p as $k => $v) {
        $s = str_replace('{' . $k . '}', (string) $v, $s);
    }
    return $s;
}

$appRoot = (string) APP_ROOT;
$appRootReal = realpath($appRoot);
$installed = is_file($appRootReal . '/var/installed');
$webRootReal = realpath(__DIR__);

$checks = [
    [PHP_VERSION_ID >= 80100, tpl($M['checks'][0][0], ['v' => PHP_VERSION]), tpl($M['checks'][0][1], ['v' => PHP_VERSION])],
    [extension_loaded('pdo_sqlite'), $M['checks'][1][0], $M['checks'][1][1]],
    [extension_loaded('openssl'), $M['checks'][2][0], $M['checks'][2][1]],
    [extension_loaded('curl'), $M['checks'][3][0], $M['checks'][3][1]],
    [class_exists('ZipArchive'), $M['checks'][4][0], $M['checks'][4][1]],
    [$appRootReal !== false, $M['checks'][5][0], $appRootReal !== false ? $appRootReal : $appRoot],
    [is_writable($appRootReal ?: $appRoot), $M['checks'][6][0], $M['checks'][6][1]],
    [
        $appRootReal === false || $webRootReal === false || strpos($appRootReal . DIRECTORY_SEPARATOR, $webRootReal . DIRECTORY_SEPARATOR) !== 0,
        $M['checks'][7][0],
        $M['checks'][7][1],
    ],
    [is_file($appRootReal . '/src/php/autoload.php'), $M['checks'][8][0], $M['checks'][8][1]],
];
$allPass = true;
foreach ($checks as $c) {
    if (!$c[0]) {
        $allPass = false;
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allPass) {
    $username = trim((string) ($_POST['username'] ?? 'admin'));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    $adminApi = trim((string) ($_POST['admin_api'] ?? 'http://127.0.0.1:2019'));
    $autoLogin = isset($_POST['local_auto_login']);

    if ($password !== $confirm) {
        $errors[] = $M['mismatch'];
    }
    if (strlen($password) < 8) {
        $errors[] = $M['short'];
    }
    if ($adminApi !== '' && !preg_match('#^(https?|unix)://#i', $adminApi)) {
        $errors[] = $M['api_scheme'];
    }

    if (!$errors) {
        require $appRootReal . '/src/php/autoload.php';
        $config = Config::load($appRootReal);
        $config->set('auth.allow_local_auto_login', $autoLogin);
        $config->set('auth.setup_token', '');
        $config->set('caddy.admin_api', $adminApi);
        if (!$config->save()) {
            $errors[] = $M['write_fail'];
        } else {
            Db::get();
            try {
                Auth::setup('', $username, $password, $config);
                file_put_contents($appRootReal . '/var/installed', date('c'));
                header('Location: /', true, 302);
                exit;
            } catch (\Throwable $ex) {
                $errors[] = tpl($M['init_fail'], ['err' => $ex->getMessage()]);
            }
        }
    }
}
?>
<!doctype html>
<html lang="<?= $lang === 'zh' ? 'zh-CN' : 'en' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($M['title']) ?></title>
<style>
:root{ --bg:#eef1f6; --card:#fff; --border:#dde3ec; --text:#1b2734; --muted:#5b6b7c; --primary:#1d63ed; --green:#16803c; --red:#c43535; --amber:#9a6207; }
@media (prefers-color-scheme: dark){ :root{ --bg:#0d1117; --card:#161c24; --border:#2b3542; --text:#e4eaf2; --muted:#94a3b4; --primary:#5c93ff; --green:#3fbe6b; --red:#ff6d6d; --amber:#e9b44c; } }
*{ box-sizing:border-box; }
body{ margin:0; font-family:system-ui,-apple-system,"Segoe UI","Microsoft YaHei",sans-serif; background:var(--bg); color:var(--text); line-height:1.55; }
.wrap{ max-width:760px; margin:40px auto; padding:0 18px; }
.lang{ text-align:right; margin-bottom:10px; }
.lang a{ color:var(--muted); text-decoration:none; font-size:13px; border:1px solid var(--border); border-radius:999px; padding:4px 12px; background:var(--card); }
.card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:22px; margin-bottom:16px; }
h1{ font-size:20px; margin:0 0 4px; }
h2{ font-size:15px; margin:0 0 12px; }
.muted{ color:var(--muted); font-size:13px; }
.check{ display:flex; gap:10px; padding:7px 0; border-bottom:1px solid var(--border); font-size:14px; }
.check:last-child{ border-bottom:0; }
.ok{ color:var(--green); }
.bad{ color:var(--red); }
label{ display:block; font-size:13px; color:var(--muted); margin:10px 0 4px; }
input{ width:100%; padding:9px 11px; border:1px solid var(--border); border-radius:9px; background:var(--card); color:var(--text); font:inherit; }
input[type=checkbox]{ width:auto; }
.btn{ margin-top:16px; background:var(--primary); color:#fff; border:0; border-radius:9px; padding:10px 18px; font:inherit; font-weight:600; cursor:pointer; }
.err{ background:rgba(255,109,109,.12); border:1px solid rgba(255,109,109,.45); color:var(--red); border-radius:9px; padding:10px 13px; margin-top:12px; font-size:14px; }
code{ background:var(--card); border:1px solid var(--border); border-radius:5px; padding:1px 5px; font-size:12px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="lang"><a href="?lang=<?= $lang === 'zh' ? 'en' : 'zh' ?>"><?= e($M['switch_zh']) ?></a></div>
  <div class="card">
    <h1><?= e($M['title']) ?></h1>
    <p class="muted"><?= tpl($M['app_root'], ['path' => e($appRootReal ?: $appRoot)]) ?></p>
    <?php if ($installed): ?>
      <p class="bad" style="font-weight:600"><?= $M['locked'] ?></p>
    <?php else: ?>
      <h2><?= e($M['env']) ?></h2>
      <?php foreach ($checks as $c): ?>
        <div class="check"><span class="<?= $c[0] ? 'ok' : 'bad' ?>"><?= $c[0] ? '✓' : '✗' ?></span>
          <span><strong><?= e($c[1]) ?></strong><br><span class="muted"><?= e($c[2]) ?></span></span></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if (!$installed && $allPass): ?>
  <div class="card">
    <h2><?= e($M['init']) ?></h2>
    <form method="post">
      <label><?= e($M['username']) ?></label>
      <input name="username" value="<?= e($_POST['username'] ?? 'admin') ?>" required>
      <label><?= e($M['password']) ?></label>
      <input name="password" type="password" required>
      <label><?= e($M['confirm']) ?></label>
      <input name="confirm" type="password" required>
      <label><?= e($M['admin_api']) ?></label>
      <input name="admin_api" value="<?= e($_POST['admin_api'] ?? 'http://127.0.0.1:2019') ?>" required>
      <label><input type="checkbox" name="local_auto_login" checked> <?= e($M['auto_login']) ?></label>
      <button class="btn" type="submit"><?= e($M['finish']) ?></button>
    </form>
    <?php foreach ($errors as $err): ?><div class="err"><?= e($err) ?></div><?php endforeach; ?>
  </div>
  <?php elseif (!$installed): ?>
  <div class="card"><p style="color:var(--amber)"><?= e($M['fail_hint']) ?></p></div>
  <?php endif; ?>
</div>
</body>
</html>
