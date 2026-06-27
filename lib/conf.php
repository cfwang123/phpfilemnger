<?php
// TOML 配置文件解析 - 使用 leonelquinteros/php-toml 库
require_once __DIR__ . '/../vendor/autoload.php';

function conf_load($path) {
	if (!file_exists($path)) return false;
	try {
		$obj = Toml::parseFile($path);
		return json_decode(json_encode($obj), true);
	} catch (Exception $e) {
		return false;
	}
}