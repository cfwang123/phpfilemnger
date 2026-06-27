<?php
// 用户认证

function auth_init() {
	if (session_status() === PHP_SESSION_NONE) session_start();
	require_once __DIR__ . '/conf.php';
}

function auth_login($name, $pass) {
	auth_init();
	$cfg = conf_load(__DIR__ . '/../config.toml');
	if (!$cfg || !isset($cfg['users'])) return false;
	foreach ($cfg['users'] as $u) {
		if ($u['name'] === $name && $u['pass'] === $pass) {
			$_SESSION['user'] = array('name' => $u['name'], 'level' => $u['level']);
			return true;
		}
	}
	return false;
}

function auth_login_keep($name, $pass) {
	if (!auth_login($name, $pass)) return false;
	// 生成 token，存入 DB，设置 cookie（2 个月）
	$token = function_exists('random_bytes') ? bin2hex(random_bytes(32)) : md5(mt_rand() . microtime() . uniqid('', true)) . md5(mt_rand() . microtime() . uniqid('', true));
	$expire = date('Y-m-d H:i:s', time() + 5184000); // 60天
	require_once __DIR__ . '/db.php';
	db_exec("DELETE FROM auth_tokens WHERE user = ?", array($name));
	db_exec("INSERT INTO auth_tokens (token, user, expire) VALUES (?, ?, ?)", array($token, $name, $expire));
	setcookie('fm_token', $token, time() + 5184000, '/', '', false, true);
	return true;
}

function auth_cookie_login() {
	if (session_status() === PHP_SESSION_NONE) session_start();
	if (isset($_SESSION['user'])) return; // 已有 session
	if (!isset($_COOKIE['fm_token'])) return;
	require_once __DIR__ . '/db.php';
	$row = db_one("SELECT user, expire FROM auth_tokens WHERE token = ?", array($_COOKIE['fm_token']));
	if (!$row || strtotime($row['expire']) < time()) return;
	$_SESSION['user'] = array('name' => $row['user'], 'level' => '');
	// 加载配置获取 level
	$cfg = conf_load(__DIR__ . '/../config.toml');
	if ($cfg && isset($cfg['users'])) {
		foreach ($cfg['users'] as $u) {
			if ($u['name'] === $row['user']) {
				$_SESSION['user']['level'] = $u['level'];
				break;
			}
		}
	}
}

function auth_check() {
	auth_init();
	auth_cookie_login();
	if (!isset($_SESSION['user'])) {
		header('Location: login.php');
		exit;
	}
}

function auth_user() {
	auth_init();
	auth_cookie_login();
	return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function auth_logout() {
	auth_init();
	unset($_SESSION['user']);
	setcookie('fm_token', '', time() - 3600, '/', '', false, true);
	require_once __DIR__ . '/db.php';
	db_exec("DELETE FROM auth_tokens WHERE token = ?", array(isset($_COOKIE['fm_token']) ? $_COOKIE['fm_token'] : ''));
}