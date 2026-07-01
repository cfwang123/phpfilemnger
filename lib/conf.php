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

// 读取 params 键值对参数（配置在 config.php 的 'params' 节）
function param_get($key, $default = null) {
	$params = conf_get('params', array());
	if (!is_array($params) || !isset($params[$key])) return $default;
	return $params[$key];
}

// ===== 语言支持 =====
// 检测当前语言: GET > session > cookie > 浏览器语言 > 默认 en
function lang_current() {
	static $lang = null;
	if ($lang !== null) return $lang;
	if (isset($_GET['lang']) && in_array($_GET['lang'], array('en', 'zh'), true)) {
		$lang = $_GET['lang'];
		if (!isset($_SESSION)) session_start();
		$_SESSION['lang'] = $lang;
		setcookie('lang', $lang, time() + 86400 * 60, '/');
		return $lang;
	}
	if (isset($_SESSION['lang'])) return $_SESSION['lang'];
	if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], array('en', 'zh'), true)) return $_COOKIE['lang'];
	// 从浏览器 Accept-Language 头检测
	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		foreach ($langs as $entry) {
			$entry = trim($entry);
			$tag = explode(';', $entry)[0];
			$tag = strtolower(trim($tag));
			if (strpos($tag, 'zh') === 0) { $lang = 'zh'; break; }
			if (strpos($tag, 'en') === 0) { $lang = 'en'; break; }
		}
		if ($lang !== null) return $lang;
	}
	$lang = 'en';
	return $lang;
}

// 加载语言包（缓存）
function lang_pack() {
	static $pack = null;
	if ($pack !== null) return $pack;
	$lang = lang_current();
	$file = __DIR__ . '/lang_' . $lang . '.php';
	if (!file_exists($file)) $file = __DIR__ . '/lang_en.php';
	$pack = require $file;
	return $pack;
}

// 翻译: lang('key', array('name'=>'foo'))
function lang($key, $params = array()) {
	$pack = lang_pack();
	$s = isset($pack[$key]) ? $pack[$key] : $key;
	if ($params) foreach ($params as $k => $v) $s = str_replace('{' . $k . '}', $v, $s);
	return $s;
}
