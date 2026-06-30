<?php
// 配置加载（从 PHP 数组配置）
function conf_load($path) {
	if (!file_exists($path)) return false;
	$cfg = require $path;
	return is_array($cfg) ? $cfg : false;
}

// 全局配置读取，支持点号访问嵌套键，如 conf_get('system.fs_encoding')
function conf_get($key = null, $default = null) {
	static $cfg = null;
	if ($cfg === null) {
		$file = __DIR__ . '/config.php';
		$cfg = file_exists($file) ? require $file : array();
	}
	if ($key === null) return $cfg;
	$parts = explode('.', $key);
	$v = $cfg;
	foreach ($parts as $k) {
		if (!is_array($v) || !isset($v[$k])) return $default;
		$v = $v[$k];
	}
	return $v;
}
