<?php
/**
 * caddy-panel 启动器（webroot 内唯一需要用户修改的文件）。
 *
 * APP_ROOT 指向真实应用目录（src/ etc/ var/ bin/ config.php 所在）。
 * 修改此路径属于文件操作，不经由 Web 完成；安装向导只负责检查。
 *
 * 开发环境默认取项目根目录（public 的上一级）。
 */
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
