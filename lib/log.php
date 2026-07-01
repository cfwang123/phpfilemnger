<?php
// 操作日志记录与查询（文件存储，JSON Lines 格式）

function _log_dir() {
	$dir = __DIR__ . '/_logs';
	if (!is_dir($dir)) @mkdir($dir, 0700, true);
	return $dir;
}

function _log_file() {
	return _log_dir() . '/' . date('Y-m') . '.log';
}

function log_add($act, $path, $detail) {
	$u = auth_user();
	$user = $u ? $u['name'] : '';
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	$entry = array(
		'time' => date('Y-m-d H:i:s'),
		'user' => $user,
		'act' => $act,
		'path' => $path,
		'detail' => $detail,
		'ip' => $ip,
	);
	$line = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
	file_put_contents(_log_file(), $line, FILE_APPEND | LOCK_EX);
}

function _log_read_all() {
	$dir = _log_dir();
	$files = glob($dir . '/*.log');
	if (!$files) return array();
	rsort($files);
	$all = array();
	foreach ($files as $f) {
		$lines = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!$lines) continue;
		foreach ($lines as $line) {
			$item = json_decode($line, true);
			if ($item) $all[] = $item;
		}
	}
	return $all;
}

function log_query($limit, $offset, $filter = array()) {
	$all = _log_read_all();
	$items = array();
	foreach ($all as $item) {
		if (isset($filter['act']) && $item['act'] !== $filter['act']) continue;
		if (isset($filter['user']) && $item['user'] !== $filter['user']) continue;
		$items[] = $item;
	}
	$total = count($items);
	$items = array_slice($items, (int)$offset, (int)$limit);
	return array('total' => $total, 'items' => $items);
}

function log_count() {
	return count(_log_read_all());
}
