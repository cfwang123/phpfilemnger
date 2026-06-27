<?php
// 登录页面 / 登录验证API

require_once __DIR__ . '/lib/init.php';

// POST: 登录验证
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = isset($_POST['name']) ? trim($_POST['name']) : '';
	$pass = isset($_POST['pass']) ? $_POST['pass'] : '';
	$keep = isset($_POST['keep']) ? intval($_POST['keep']) : 0;
	if ($name === '' || $pass === '') {
		header('Content-Type: application/json');
		echo json_encode(array('ok' => false, 'err' => '用户名和密码不能为空'));
		exit;
	}
	if ($keep && auth_login_keep($name, $pass)) {
		// 记录登录日志
		require_once __DIR__ . '/lib/log.php';
		log_add('login', '', $name);
		header('Content-Type: application/json');
		echo json_encode(array('ok' => true));
	} else if (auth_login($name, $pass)) {
		// 记录登录日志
		require_once __DIR__ . '/lib/log.php';
		log_add('login', '', $name);
		header('Content-Type: application/json');
		echo json_encode(array('ok' => true));
	} else {
		header('Content-Type: application/json');
		echo json_encode(array('ok' => false, 'err' => '用户名或密码错误'));
	}
	exit;
}

// GET: 输出登录页面
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>登录 - Web文件管理系统</title>
	<link rel="stylesheet" href="fontawesome/css/all.min.css">
	<style>
	* {margin:0;padding:0;box-sizing:border-box;}
	body {font-family:"Segoe UI",system-ui,sans-serif;background:#f5f5f7;color:#1f2937;font-size:14px;}
	a {color:#2563eb;text-decoration:none;}
	.edt {padding:7px 10px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;outline:none;transition:border .15s;background:#fff;width:100%;}
	.edt:focus {border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);}
	.edt::placeholder {color:#6b7280;}
	.btn {display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border:1px solid #e5e7eb;background:#fff;color:#1f2937;border-radius:6px;cursor:pointer;font-size:13px;}
	.btn:hover {background:#eff6ff;border-color:#2563eb;}
	.btnprimary {background:#2563eb;color:#fff;border-color:#2563eb;}
	.btnprimary:hover {background:#1d4ed8;border-color:#1d4ed8;}
	.fs12 {font-size:12px;}
	.tc {text-align:center;}
	.loginwrap {min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);}
	.loginbox {background:#fff;border-radius:14px;padding:40px;width:360px;box-shadow:0 20px 60px rgba(0,0,0,.25);}
	.loginlogo {text-align:center;margin-bottom:28px;}
	.loginlogo .ico {font-size:42px;color:#2563eb;}
	.loginlogo h2 {margin-top:8px;font-size:20px;font-weight:600;}
	.loginform .row {margin-bottom:18px;}
	.loginform .row label {display:block;margin-bottom:6px;font-size:13px;color:#6b7280;}
	.loginform .inputwrap {position:relative;}
	.loginform .inputwrap .ico {position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6b7280;}
	.loginform .inputwrap .edt {padding-left:36px;}
	.loginbtn {width:100%;padding:10px;justify-content:center;font-size:15px;}
	.loginerr {color:#ef4444;font-size:12px;margin-top:8px;min-height:16px;}
	</style>
</head>
<body>
	<div class="loginwrap">
		<div class="loginbox">
			<div class="loginlogo">
				<i class="ico fa-solid fa-folder-open"></i>
				<h2>Web文件管理系统</h2>
			</div>
			<form class="loginform" id="frm">
				<div class="row">
					<label>用户名</label>
					<div class="inputwrap">
						<i class="ico fa fa-user"></i>
						<input class="edt" id="ename" placeholder="请输入用户名" autocomplete="off">
					</div>
				</div>
				<div class="row">
					<label>密码</label>
					<div class="inputwrap">
						<i class="ico fa fa-lock"></i>
						<input class="edt" id="epass" type="password" placeholder="请输入密码">
					</div>
				</div>
				<div class="row">
					<button type="submit" class="btn btnprimary loginbtn" id="blogin">
						<i class="fa fa-sign-in"></i> 登录
					</button>
				</div>
				<div class="row" style="margin-bottom:0;">
					<label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#6b7280;">
						<input type="checkbox" id="ekeep" checked> 保持登录（2个月内免登录）
					</label>
				</div>
				<div class="loginerr tc" id="lberr"></div>
			</form>
		</div>
	</div>
	<script>
	document.getElementById('frm').onsubmit = function(e) {
		e.preventDefault();
		var n = document.getElementById('ename').value.trim();
		var p = document.getElementById('epass').value;
		var k = document.getElementById('ekeep').checked ? 1 : 0;
		var lb = document.getElementById('lberr');
		if (!n || !p) { lb.textContent = '用户名和密码不能为空'; return; }
		var xhr = new XMLHttpRequest();
		xhr.open('POST', 'login.php', true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onload = function() {
			var r = JSON.parse(xhr.responseText);
			if (r.ok) { window.location.href = 'index.php?u=' + encodeURIComponent(n); }
			else { lb.textContent = r.err; }
		};
		xhr.send('name=' + encodeURIComponent(n) + '&pass=' + encodeURIComponent(p) + '&keep=' + k);
	};
	</script>
</body>
</html>