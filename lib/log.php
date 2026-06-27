<?php
// 操作日志记录与查询

function log_add($act, $path, $detail) {
	$u = auth_user();
	$user = $u ? $u['name'] : '';
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	db_exec("INSERT INTO logs (user, act, path, detail, ip) VALUES (?, ?, ?, ?, ?)",
		array($user, $act, $path, $detail, $ip));
}

function log_query($limit, $offset, $filter = array()) {
	$where = '';
	$params = array();
	if (isset($filter['act'])) {
		$where = ' WHERE act = ?';
		$params[] = $filter['act'];
	}
	if (isset($filter['user'])) {
		$where = ($where === '' ? ' WHERE' : ' AND') . ' user = ?';
		$params[] = $filter['user'];
	}
	// 总数
	$row = db_one("SELECT COUNT(*) AS cnt FROM logs" . $where, $params);
	$total = $row ? (int)$row['cnt'] : 0;
	// 列表
	$items = db_query("SELECT * FROM logs" . $where . " ORDER BY id DESC LIMIT ? OFFSET ?",
		array_merge($params, array((int)$limit, (int)$offset)));
	return array('total' => $total, 'items' => $items);
}

function log_count() {
	$row = db_one("SELECT COUNT(*) AS cnt FROM logs");
	return $row ? (int)$row['cnt'] : 0;
}