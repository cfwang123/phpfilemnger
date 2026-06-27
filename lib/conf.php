<?php
// 配置加载（从 PHP 数组配置）
function conf_load($path) {
	if (!file_exists($path)) return false;
	$cfg = require $path;
	return is_array($cfg) ? $cfg : false;
}
