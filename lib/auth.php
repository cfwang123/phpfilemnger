<?php
// 用户认证

function auth_init() {
	if (session_status() === PHP_SESSION_NONE) {
		// 延长 session 生命周期为 7 天，避免频繁重新登录
		$lifetime = 86400 * 7; // 7 天
		ini_set('session.gc_maxlifetime', $lifetime);
		session_set_cookie_params($lifetime, '/', '', false, true);
		session_start();
		// 定期刷新 session 的存活时间
		if (isset($_SESSION['_CREATED'])) {
			if (time() - $_SESSION['_CREATED'] > 86400) {
				$_SESSION['_CREATED'] = time();
			}
		} else {
			$_SESSION['_CREATED'] = time();
		}
	}
	require_once __DIR__ . '/conf.php';
}

function auth_login($name, $pass) {
	auth_init();
	$cfg = conf_load(__DIR__ . '/config.php');
	if (!$cfg || !isset($cfg['users'])) return false;
	foreach ($cfg['users'] as $u) {
		if ($u['name'] === $name && $u['pass'] === $pass) {
			$_SESSION['user'] = array('name' => $u['name'], 'level' => $u['level']);
			return true;
		}
	}
	return false;
}

function _auth_token_dir() {
	$dir = __DIR__ . '/_tokens';
	if (!is_dir($dir)) @mkdir($dir, 0700, true);
	return $dir;
}

function _auth_token_save($token, $user, $expire) {
	$f = _auth_token_dir() . '/' . $token . '.json';
	file_put_contents($f, json_encode(array('user' => $user, 'expire' => $expire)), LOCK_EX);
}

function _auth_token_load($token) {
	$f = _auth_token_dir() . '/' . $token . '.json';
	if (!file_exists($f)) return null;
	$data = json_decode(file_get_contents($f), true);
	if (!$data) return null;
	if (strtotime($data['expire']) < time()) {
		@unlink($f);
		return null;
	}
	return $data;
}

function _auth_token_delete($token) {
	$f = _auth_token_dir() . '/' . $token . '.json';
	@unlink($f);
}

function _auth_token_delete_by_user($user) {
	$dir = _auth_token_dir();
	foreach (glob($dir . '/*.json') as $f) {
		$data = json_decode(file_get_contents($f), true);
		if ($data && $data['user'] === $user) @unlink($f);
	}
}

function auth_login_keep($name, $pass) {
	if (!auth_login($name, $pass)) return false;
	// 生成 token，存入文件，设置 cookie（2 个月）
	$token = function_exists('random_bytes') ? bin2hex(random_bytes(32)) : md5(mt_rand() . microtime() . uniqid('', true)) . md5(mt_rand() . microtime() . uniqid('', true));
	$expire = date('Y-m-d H:i:s', time() + 5184000); // 60天
	_auth_token_delete_by_user($name);
	_auth_token_save($token, $name, $expire);
	setcookie('fm_token', $token, time() + 5184000, '/', '', false, true);
	return true;
}

function auth_cookie_login() {
	if (session_status() === PHP_SESSION_NONE) session_start();
	if (isset($_SESSION['user'])) return; // 已有 session
	if (!isset($_COOKIE['fm_token'])) return;
	$data = _auth_token_load($_COOKIE['fm_token']);
	if (!$data) return;
	$_SESSION['user'] = array('name' => $data['user'], 'level' => '');
	// 加载配置获取 level
	$cfg = conf_load(__DIR__ . '/config.php');
	if ($cfg && isset($cfg['users'])) {
		foreach ($cfg['users'] as $u) {
			if ($u['name'] === $data['user']) {
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
		header('Location: index.php');
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
	$_SESSION = array();
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	setcookie('fm_token', '', time() - 42000, '/', '', false, true);
	@session_destroy();
	if (isset($_COOKIE['fm_token'])) _auth_token_delete($_COOKIE['fm_token']);
}