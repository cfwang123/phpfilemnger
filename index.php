<?php
// 系统入口 - 路由分发 + 主页面 + 登录页面

require_once __DIR__ . '/lib/init.php';

function show_login_page() {
	$L = lang_pack();
	?><!DOCTYPE html>
<html lang="<?php echo lang_current(); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $L['login_title']; ?></title>
	<link rel="stylesheet" href="lib/fontawesome/css/all.min.css">
	<link rel="stylesheet" href="lib/style.css">
</head>
<body>
	<div class="loginwrap">
		<div class="loginbox">
			<div class="loginlogo">
				<i class="ico fa-solid fa-folder-open"></i>
				<h2><?php echo $L['app_title']; ?></h2>
			</div>
			<form class="loginform" id="frm">
				<div class="row">
					<label><?php echo $L['username']; ?></label>
					<div class="inputwrap">
						<i class="ico fa fa-user"></i>
						<input class="edt" id="ename" placeholder="<?php echo $L['ph_username']; ?>" autocomplete="off">
					</div>
				</div>
				<div class="row">
					<label><?php echo $L['password']; ?></label>
					<div class="inputwrap">
						<i class="ico fa fa-lock"></i>
						<input class="edt" id="epass" type="password" placeholder="<?php echo $L['ph_password']; ?>">
					</div>
				</div>
				<div class="row">
					<button type="submit" class="btn btnprimary loginbtn" id="blogin">
						<i class="fa fa-sign-in"></i> <?php echo $L['login']; ?>
					</button>
				</div>
				<div class="row" style="margin-bottom:0;">
					<label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#6b7280;">
						<input type="checkbox" id="ekeep" checked> <?php echo $L['keep_login']; ?>
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
		if (!n || !p) { lb.textContent = <?php echo json_encode($L['err_empty_fields'], JSON_UNESCAPED_UNICODE); ?>; return; }
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
		echo json_encode(array('ok' => false, 'err' => lang('err_empty_fields')), JSON_UNESCAPED_UNICODE);
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
		echo json_encode(array('ok' => false, 'err' => lang('err_wrong_credentials')), JSON_UNESCAPED_UNICODE);
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

// 语言切换
if ($act === 'lang') {
	$lang = isset($_GET['l']) ? $_GET['l'] : 'en';
	if (!in_array($lang, array('en', 'zh'), true)) $lang = 'en';
	if (!isset($_SESSION)) session_start();
	$_SESSION['lang'] = $lang;
	setcookie('lang', $lang, time() + 86400 * 60, '/');
	$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
	header('Location: ' . $ref);
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

	// 非管理员禁止写操作
	if (in_array($act, array('mkdir', 'del', 'ren', 'paste', 'upload', 'save'), true) && $ulevel !== 'admin') {
		echo json_encode(array('ok' => false, 'err' => lang('err_permission')), JSON_UNESCAPED_UNICODE);
		exit;
	}

	if ($act === 'list') {
		$path = isset($_GET['path']) ? $_GET['path'] : '';
		$items = file_list($path, file_ignore_list());
		if ($items === false) {
			echo json_encode(array('ok' => false, 'err' => lang('err_invalid_path')), JSON_UNESCAPED_UNICODE);
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
			echo json_encode(array('ok' => false, 'err' => lang('err_create_failed')), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	if ($act === 'del') {
		$path = isset($_POST['path']) ? $_POST['path'] : '';
		if (file_is_ignored($path)) {
			echo json_encode(array('ok' => false, 'err' => lang('err_delete_protected')), JSON_UNESCAPED_UNICODE);
		} else if (file_del($path)) {
			log_add('del', $path, '');
			echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
		} else {
			echo json_encode(array('ok' => false, 'err' => lang('delete_failed')), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	if ($act === 'ren') {
		$path = isset($_POST['path']) ? $_POST['path'] : '';
		$name = isset($_POST['name']) ? $_POST['name'] : '';
		if (file_is_ignored($path) || file_has_protected_ext($path) || file_is_protected_ext($name)) {
			echo json_encode(array('ok' => false, 'err' => lang('err_modify_protected')), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$oldName = basename(file_safepath($path));
		if (file_ren($path, $name)) {
			$newPath = dirname($path) . '/' . $name;
			if (dirname($path) === '.') $newPath = $name;
			log_add('ren', $newPath, $oldName . '→' . $name);
			echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
		} else {
			echo json_encode(array('ok' => false, 'err' => lang('rename_failed')), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	if ($act === 'paste') {
		$dst = isset($_POST['path']) ? $_POST['path'] : '';
		$src = isset($_POST['src']) ? $_POST['src'] : '';
		$type = isset($_POST['type']) ? $_POST['type'] : '';
		if (file_has_protected_ext($dst) || ($src !== '' && file_has_protected_ext($src))) {
			echo json_encode(array('ok' => false, 'err' => lang('err_modify_protected')), JSON_UNESCAPED_UNICODE);
			exit;
		}
		if ($src !== '') {
			$result = file_paste_src($src, $dst, $type);
			$actType = $type === 'cut' ? 'paste' : 'copy';
			if ($result) log_add($actType, ($dst ? $dst.'/' : '') . $result, 'from ' . $src);
			echo json_encode($result ? array('ok' => true, 'name' => $result) : array('ok' => false, 'err' => lang('paste_failed')), JSON_UNESCAPED_UNICODE);
		} else {
			$result = file_paste($dst);
			if ($result) {
				$clip = isset($_SESSION['clip']) ? $_SESSION['clip'] : array();
				$actType = $clip['type'] === 'cut' ? 'paste' : 'copy';
				log_add($actType, ($dst ? $dst.'/' : '') . $result, 'from ' . $clip['path']);
				echo json_encode(array('ok' => true, 'name' => $result), JSON_UNESCAPED_UNICODE);
			} else {
				echo json_encode(array('ok' => false, 'err' => lang('paste_failed')), JSON_UNESCAPED_UNICODE);
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
			echo json_encode(array('ok' => false, 'err' => lang('err_upload_protected')), JSON_UNESCAPED_UNICODE);
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
						echo json_encode(array('ok' => false, 'err' => lang('err_zip_protected')), JSON_UNESCAPED_UNICODE);
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
		if (file_has_protected_ext($path)) {
			echo json_encode(array('ok' => false, 'err' => lang('err_modify_protected')), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$abs = file_safepath($path);
		if ($abs === false || !file_exists($abs) || is_dir($abs)) {
			echo json_encode(array('ok' => false, 'err' => lang('err_invalid_path')), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$content = file_get_contents($abs);
		echo json_encode(array('ok' => true, 'content' => $content), JSON_UNESCAPED_UNICODE);
		exit;
	}

	if ($act === 'save') {
		$path = isset($_POST['path']) ? $_POST['path'] : '';
		$content = isset($_POST['content']) ? $_POST['content'] : '';
		if (file_is_ignored($path) || file_has_protected_ext($path)) {
			echo json_encode(array('ok' => false, 'err' => lang('err_modify_protected')), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$abs = file_safepath($path);
		if ($abs === false || !file_exists($abs) || is_dir($abs)) {
			echo json_encode(array('ok' => false, 'err' => lang('err_invalid_path')), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$r = file_put_contents($abs, $content);
		if ($r === false) {
			echo json_encode(array('ok' => false, 'err' => lang('save_failed')), JSON_UNESCAPED_UNICODE);
		} else {
			log_add('save', $path, '');
			echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	echo json_encode(array('ok' => false, 'err' => lang('err_unknown')), JSON_UNESCAPED_UNICODE);
	exit;
}

// GET 无 act: 输出主页面 HTML
$L = lang_pack();
$langCode = lang_current();
?><!DOCTYPE html>
<html lang="<?php echo $langCode; ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $L['app_title']; ?></title>
	<link rel="stylesheet" href="lib/fontawesome/css/all.min.css">
	<link rel="stylesheet" href="lib/viewer1.11.6/viewer.min.css">
	<link rel="stylesheet" href="lib/style.css">
	<script src="lib/viewer1.11.6/viewer.min.js"></script>
</head>
<body class="fcol">

	<!-- 顶栏 -->
	<div class="fmbar">
		<div class="title"><i class="ico fa-solid fa-folder-open"></i> <?php echo $L['app_title']; ?></div>
		<button class="btn btnsm bnewwin"><i class="fa-solid fa-plus"></i> <?php echo $L['new_window']; ?></button>
		<div class="spacer"></div>
		<div class="userinfo">
			<i class="fa-solid fa-user-circle"></i>
			<span id="lbuser"><?php echo htmlspecialchars($uname); ?></span>
			<span class="badge" id="lblevel"><?php echo $ulevel === 'admin' ? $L['admin'] : $L['normal_user']; ?></span>
		</div>
		<div class="sysmenu" style="position:relative;">
			<button class="btn btnsm btnsys" title="<?php echo $L['system']; ?>"><i class="fa-solid fa-gear"></i></button>
			<div class="sysdrop hide">
				<div class="mitem" data-act="log"><i class="ico fa-solid fa-list"></i> <?php echo $L['operation_log']; ?></div>
				<?php if ($ulevel === 'admin') { ?>
				<div class="msep"></div>
				<div class="mitem" data-act="uplist"><i class="ico fa-solid fa-upload"></i> <?php echo $L['upload_list']; ?></div>
				<?php } ?>
				<div class="msep"></div>
				<div class="mitem" data-act="langen"><i class="ico fa-solid fa-language"></i> <?php echo $L['lang_en']; ?></div>
				<div class="mitem" data-act="langzh"><i class="ico fa-solid fa-language"></i> <?php echo $L['lang_zh']; ?></div>
			</div>
		</div>
		<button class="btn btnsm btndanger bexit ml10" onclick="window.location.href='?act=logout'"><i class="fa-solid fa-sign-out"></i> <?php echo $L['exit']; ?></button>
	</div>

	<!-- 窗口区域 -->
	<div class="winarea">
		<div class="winlist"></div>
	</div>

	<!-- 上传面板 -->
	<div class="uploadpanel hide">
		<div class="uphead">
			<div class="uptitle"><i class="fa-solid fa-upload"></i> <?php echo $L['upload_tasks']; ?> <span class="upcount">0</span></div>
			<div class="upbtns">
				<button class="upbtn clear" title="<?php echo $L['clear_completed']; ?>"><i class="fa-solid fa-check"></i></button>
				<button class="upbtn close" title="<?php echo $L['close_panel']; ?>"><i class="fa-solid fa-times"></i></button>
			</div>
		</div>
		<div class="upbody"></div>
		<div class="uptotal"><span><?php echo lang('total_tasks', array('count'=>0)); ?></span><span><?php echo lang('completed_n', array('count'=>0)); ?></span></div>
	</div>

	<!-- 隐藏文件选择 -->
	<input type="file" multiple style="display:none" id="fileinput">

	<!-- 右键菜单 -->
	<div class="ctxmenu" id="ctxmenu"></div>

	<!-- 拖拽幽灵 -->
	<div class="dragghost" id="dragghost"></div>

	<script src="lib/app.js"></script>
	<script>
	FM_CFG = {dcf:<?php echo json_encode(conf_get('system.delete_confirm_file', true), JSON_UNESCAPED_UNICODE); ?>, dcd:<?php echo json_encode(conf_get('system.delete_confirm_dir', true), JSON_UNESCAPED_UNICODE); ?>, level:<?php echo json_encode($ulevel, JSON_UNESCAPED_UNICODE); ?>};
	window.LANG = <?php echo json_encode($L, JSON_UNESCAPED_UNICODE); ?>;
	</script>
</body>
</html>
