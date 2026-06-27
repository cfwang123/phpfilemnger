<?php
// SQLite 数据库封装 - 参数存储 + 操作日志

function db_init($path = '') {
	if ($path === '') $path = __DIR__ . '/../data.db';
	static $pdo = null;
	if ($pdo !== null) return $pdo;
	$pdo = new PDO('sqlite:' . $path);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	// 建表 - params
	$pdo->exec("CREATE TABLE IF NOT EXISTS params (
		key TEXT PRIMARY KEY,
		value TEXT
	)");
	// 建表 - logs
	$pdo->exec("CREATE TABLE IF NOT EXISTS logs (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		user TEXT,
		act TEXT,
		path TEXT,
		detail TEXT,
		ip TEXT,
		time DATETIME DEFAULT CURRENT_TIMESTAMP
	)");
	// 建表 - auth_tokens（保持登录）
	$pdo->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
		token TEXT PRIMARY KEY,
		user TEXT,
		expire TEXT
	)");
	return $pdo;
}

function db_get($key) {
	$pdo = db_init();
	$st = $pdo->prepare("SELECT value FROM params WHERE key = ?");
	$st->execute(array($key));
	$v = $st->fetchColumn();
	return $v === false ? null : $v;
}

function db_set($key, $value) {
	$pdo = db_init();
	$st = $pdo->prepare("INSERT OR REPLACE INTO params (key, value) VALUES (?, ?)");
	$st->execute(array($key, $value));
}

function db_del($key) {
	$pdo = db_init();
	$st = $pdo->prepare("DELETE FROM params WHERE key = ?");
	$st->execute(array($key));
}

function db_exec($sql, $params = array()) {
	$pdo = db_init();
	$st = $pdo->prepare($sql);
	$st->execute($params);
	return $st->rowCount();
}

function db_query($sql, $params = array()) {
	$pdo = db_init();
	$st = $pdo->prepare($sql);
	$st->execute($params);
	return $st->fetchAll(PDO::FETCH_ASSOC);
}

function db_one($sql, $params = array()) {
	$pdo = db_init();
	$st = $pdo->prepare($sql);
	$st->execute($params);
	return $st->fetch(PDO::FETCH_ASSOC);
}