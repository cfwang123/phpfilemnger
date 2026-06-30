<?php
// 系统入口 - 路由分发 + 主页面 + 登录页面

require_once __DIR__ . '/lib/init.php';

function show_login_page() {
	?><!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>登录 - Web文件管理系统</title>
	<link rel="stylesheet" href="lib/fontawesome/css/all.min.css">
	<link rel="stylesheet" href="lib/style.css">
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
		xhr.open('POST', '', true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onload = function() {
			var r = JSON.parse(xhr.responseText);
			if (r.ok) { window.location.href = ''; }
			else { lb.textContent = r.err; }
		};
		xhr.send('name=' + encodeURIComponent(n) + '&pass=' + encodeURIComponent(p) + '&keep=' + k);
	};
	</script>
</body>
</html>
<?php
}

$act = isset($_GET['act']) ? $_GET['act'] : '';

// act=down 公开下载，不校验登录
if ($act === 'down') {
	require_once __DIR__ . '/lib/file.php';
	require_once __DIR__ . '/lib/log.php';
	require_once __DIR__ . '/lib/down.php';
	$path = isset($_GET['path']) ? $_GET['path'] : '';
	log_add('download', $path, '');
	down_file($path);
	exit;
}

// POST: 登录验证
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act === '') {
	$name = isset($_POST['name']) ? trim($_POST['name']) : '';
	$pass = isset($_POST['pass']) ? $_POST['pass'] : '';
	$keep = isset($_POST['keep']) ? intval($_POST['keep']) : 0;
	if ($name === '' || $pass === '') {
		header('Content-Type: application/json');
		echo json_encode(array('ok' => false, 'err' => '用户名和密码不能为空'), JSON_UNESCAPED_UNICODE);
		exit;
	}
	if ($keep && auth_login_keep($name, $pass)) {
		log_add('login', '', $name);
		header('Content-Type: application/json');
		echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
	} else if (auth_login($name, $pass)) {
		log_add('login', '', $name);
		header('Content-Type: application/json');
		echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
	} else {
		header('Content-Type: application/json');
		echo json_encode(array('ok' => false, 'err' => '用户名或密码错误'), JSON_UNESCAPED_UNICODE);
	}
	exit;
}

// 退出登录
if ($act === 'logout') {
	auth_logout();
	header('Content-Type: text/html; charset=utf-8');
	echo '<html><head><meta http-equiv="refresh" content="0;url=index.php"></head><body></body></html>';
	exit;
}

// 检查登录
$u = auth_user();
if (!$u) {
	show_login_page();
	exit;
}

// 已登录
$uname = $u['name'];
$ulevel = $u['level'];

// 路由分发
if ($act !== '') {
	require_once __DIR__ . '/lib/file.php';
	require_once __DIR__ . '/lib/log.php';

	header('Content-Type: application/json');

	if ($act === 'list') {
		$path = isset($_GET['path']) ? $_GET['path'] : '';
		$items = file_list($path, file_ignore_list());
		if ($items === false) {
			echo json_encode(array('ok' => false, 'err' => '无效路径'), JSON_UNESCAPED_UNICODE);
		} else {
			echo json_encode(array('ok' => true, 'path' => $path, 'items' => $items), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	if ($act === 'mkdir') {
		$path = isset($_POST['path']) ? $_POST['path'] : '';
		if (file_mkdir($path)) {
			log_add('mkdir', $path, '');
			echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
		} else {
			echo json_encode(array('ok' => false, 'err' => '创建失败'), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	if ($act === 'del') {
		$path = isset($_POST['path']) ? $_POST['path'] : '';
		if (file_is_ignored($path)) {
			echo json_encode(array('ok' => false, 'err' => '不能删除受保护的文件'), JSON_UNESCAPED_UNICODE);
		} else if (file_del($path)) {
			log_add('del', $path, '');
			echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
		} else {
			echo json_encode(array('ok' => false, 'err' => '删除失败'), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	if ($act === 'ren') {
		$path = isset($_POST['path']) ? $_POST['path'] : '';
		$name = isset($_POST['name']) ? $_POST['name'] : '';
		if (file_is_ignored($path)) {
			echo json_encode(array('ok' => false, 'err' => '不能修改受保护的文件'), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$oldName = basename(file_safepath($path));
		if (file_ren($path, $name)) {
			$newPath = dirname($path) . '/' . $name;
			if (dirname($path) === '.') $newPath = $name;
			log_add('ren', $newPath, $oldName . '→' . $name);
			echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
		} else {
			echo json_encode(array('ok' => false, 'err' => '重命名失败'), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	if ($act === 'paste') {
		$dst = isset($_POST['path']) ? $_POST['path'] : '';
		$src = isset($_POST['src']) ? $_POST['src'] : '';
		$type = isset($_POST['type']) ? $_POST['type'] : '';
		if ($src !== '') {
			$result = file_paste_src($src, $dst, $type);
			$actType = $type === 'cut' ? 'paste' : 'copy';
			if ($result) log_add($actType, ($dst ? $dst.'/' : '') . $result, 'from ' . $src);
			echo json_encode($result ? array('ok' => true, 'name' => $result) : array('ok' => false, 'err' => '粘贴失败'), JSON_UNESCAPED_UNICODE);
		} else {
			$result = file_paste($dst);
			if ($result) {
				$clip = isset($_SESSION['clip']) ? $_SESSION['clip'] : array();
				$actType = $clip['type'] === 'cut' ? 'paste' : 'copy';
				log_add($actType, ($dst ? $dst.'/' : '') . $result, 'from ' . $clip['path']);
				echo json_encode(array('ok' => true, 'name' => $result), JSON_UNESCAPED_UNICODE);
			} else {
				echo json_encode(array('ok' => false, 'err' => '粘贴失败'), JSON_UNESCAPED_UNICODE);
			}
		}
		exit;
	}

	if ($act === 'info') {
		$path = isset($_GET['path']) ? $_GET['path'] : '';
		$info = file_info($path);
		echo json_encode($info ? array('ok' => true, 'info' => $info) : array('ok' => false), JSON_UNESCAPED_UNICODE);
		exit;
	}

	if ($act === 'upload') {
		require_once __DIR__ . '/lib/up.php';
		$dir = isset($_POST['dir']) ? $_POST['dir'] : '';
		if (file_is_ignored($dir)) {
			echo json_encode(array('ok' => false, 'err' => '不能上传文件到受保护的目录'), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$result = up_file($dir);
		if ($result['ok'] && isset($result['results'])) {
			foreach ($result['results'] as $r) {
				if ($r['ok']) log_add('upload', ($dir ? $dir . '/' : '') . $r['name'], '');
			}
		}
		echo json_encode($result, JSON_UNESCAPED_UNICODE);
		exit;
	}

	if ($act === 'zip') {
		require_once __DIR__ . '/lib/down.php';
		$paths = isset($_GET['paths']) ? explode(',', $_GET['paths']) : array();
		if (empty($paths) && isset($_GET['path'])) {
			$rel = $_GET['path'];
			$abs = UP_DIR . ($rel ? '/' . $rel : '');
			if (is_dir($abs)) {
				if ($rel === '') {
					$ignore = file_ignore_list();
					$dh = @opendir(UP_DIR);
					if ($dh) {
						while (($n = readdir($dh)) !== false) {
							if ($n === '.' || $n === '..') continue;
							if (in_array($n, $ignore)) continue;
							$paths[] = $n;
						}
						closedir($dh);
					}
				} else {
					if (file_is_ignored($rel)) {
						echo json_encode(array('ok' => false, 'err' => '不能打包受保护的文件'), JSON_UNESCAPED_UNICODE);
						exit;
					}
					$paths = array($rel);
				}
			}
		}
		$name = isset($_GET['name']) ? $_GET['name'] : 'download.zip';
		log_add('zip', implode(',', $paths), $name);
		down_zip($paths, $name);
		exit;
	}

	if ($act === 'log') {
		$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
		$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
		$filter = array();
		if (isset($_GET['filter_act'])) $filter['act'] = $_GET['filter_act'];
		if (isset($_GET['filter_user'])) $filter['user'] = $_GET['filter_user'];
		echo json_encode(log_query($limit, $offset, $filter), JSON_UNESCAPED_UNICODE);
		exit;
	}

	if ($act === 'read') {
		$path = isset($_GET['path']) ? $_GET['path'] : '';
		$abs = file_safepath($path);
		if ($abs === false || !file_exists($abs) || is_dir($abs)) {
			echo json_encode(array('ok' => false, 'err' => '无效路径'), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$content = file_get_contents($abs);
		echo json_encode(array('ok' => true, 'content' => $content), JSON_UNESCAPED_UNICODE);
		exit;
	}

	echo json_encode(array('ok' => false, 'err' => '未知操作'), JSON_UNESCAPED_UNICODE);
	exit;
}

// GET 无 act: 输出主页面 HTML
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Web文件管理系统</title>
	<link rel="stylesheet" href="lib/fontawesome/css/all.min.css">
	<link rel="stylesheet" href="lib/viewer1.11.6/viewer.min.css">
	<link rel="stylesheet" href="lib/style.css">
	<script src="lib/viewer1.11.6/viewer.min.js"></script>
</head>
<body class="fcol">

	<!-- 顶栏 -->
	<div class="fmbar">
		<div class="title"><i class="ico fa-solid fa-folder-open"></i> Web文件管理系统</div>
		<button class="btn btnsm bnewwin"><i class="fa-solid fa-plus"></i> 新建窗口</button>
		<div class="spacer"></div>
		<div class="userinfo">
			<i class="fa-solid fa-user-circle"></i>
			<span id="lbuser"><?php echo htmlspecialchars($uname); ?></span>
			<span class="badge" id="lblevel"><?php echo $ulevel === 'admin' ? '管理员' : '普通用户'; ?></span>
		</div>
		<div class="sysmenu" style="position:relative;">
			<button class="btn btnsm btnsys" title="系统"><i class="fa-solid fa-gear"></i></button>
			<div class="sysdrop hide">
				<div class="mitem" data-act="log"><i class="ico fa-solid fa-list"></i> 操作日志</div>
				<div class="msep"></div>
				<div class="mitem" data-act="uplist"><i class="ico fa-solid fa-upload"></i> 上传列表</div>
			</div>
		</div>
		<button class="btn btnsm btndanger bexit ml10" onclick="window.location.href='?act=logout'"><i class="fa-solid fa-sign-out"></i> 退出</button>
	</div>

	<!-- 窗口区域 -->
	<div class="winarea">
		<div class="winlist"></div>
	</div>

	<!-- 上传面板 -->
	<div class="uploadpanel hide">
		<div class="uphead">
			<div class="uptitle"><i class="fa-solid fa-upload"></i> 上传任务 <span class="upcount">0</span></div>
			<div class="upbtns">
				<button class="upbtn clear" title="清除已完成"><i class="fa-solid fa-check"></i></button>
				<button class="upbtn close" title="关闭面板"><i class="fa-solid fa-times"></i></button>
			</div>
		</div>
		<div class="upbody"></div>
		<div class="uptotal"><span>共 0 个任务</span><span>已完成 0</span></div>
	</div>

	<!-- 隐藏文件选择 -->
	<input type="file" multiple style="display:none" id="fileinput">

	<!-- 右键菜单 -->
	<div class="ctxmenu" id="ctxmenu"></div>

	<!-- 拖拽幽灵 -->
	<div class="dragghost" id="dragghost"></div>

	<script src="lib/app.js"></script>
	<script>
	FM_CFG = {dcf:<?php echo json_encode(conf_get('system.delete_confirm_file', true), JSON_UNESCAPED_UNICODE); ?>, dcd:<?php echo json_encode(conf_get('system.delete_confirm_dir', true), JSON_UNESCAPED_UNICODE); ?>};
	</script>
</body>
</html>
