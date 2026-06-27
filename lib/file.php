<?php
// 文件操作 API

if (!defined('UP_DIR')) define('UP_DIR', realpath(__DIR__ . '/../up'));

// 安全路径：将相对路径转为绝对路径，防止穿越
function file_safepath($rel) {
	$rel = str_replace('\\', '/', $rel);
	$rel = preg_replace('#/+#', '/', $rel);
	$rel = trim($rel, '/');
	// 手动解析 .. 防止穿越
	$parts = $rel === '' ? array() : explode('/', $rel);
	$clean = array();
	foreach ($parts as $p) {
		if ($p === '..') {
			if (count($clean) === 0) return false; // 超出 UP_DIR
			array_pop($clean);
		} elseif ($p !== '.' && $p !== '') {
			$clean[] = $p;
		}
	}
	$abs = UP_DIR . (count($clean) ? '/' . implode('/', $clean) : '');
	return $abs;
}

// 获取文件类型图标名
function file_typeicon($ext) {
	$map = array(
		'jpg' => 'img', 'jpeg' => 'img', 'png' => 'img', 'gif' => 'img', 'svg' => 'img', 'webp' => 'img', 'bmp' => 'img',
		'mp4' => 'video', 'mkv' => 'video', 'avi' => 'video', 'webm' => 'video', 'mov' => 'video', 'wmv' => 'video', 'flv' => 'video',
		'mp3' => 'audio', 'wav' => 'audio', 'flac' => 'audio', 'aac' => 'audio', 'ogg' => 'audio', 'wma' => 'audio',
		'pdf' => 'doc', 'doc' => 'doc', 'docx' => 'doc', 'xls' => 'doc', 'xlsx' => 'doc', 'ppt' => 'doc', 'pptx' => 'doc',
		'zip' => 'zip', 'rar' => 'zip', '7z' => 'zip', 'tar' => 'zip', 'gz' => 'zip',
		'php' => 'code', 'html' => 'code', 'css' => 'code', 'js' => 'code', 'json' => 'code', 'xml' => 'code', 'toml' => 'code', 'ini' => 'code', 'txt' => 'code',
	);
	$ext = strtolower($ext);
	return isset($map[$ext]) ? $map[$ext] : 'file';
}

// 格式化文件大小
function file_size($bytes) {
	if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
	if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
	if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
	return $bytes . ' B';
}

// 列出目录
function file_list($rel) {
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	$items = array();
	$dh = opendir($abs);
	if (!$dh) return false;
	while (($n = readdir($dh)) !== false) {
		if ($n === '.' || $n === '..') continue;
		// 隐藏 .php 后缀文件
		if (stripos($n, '.php') !== false) continue;
		$p = $abs . '/' . $n;
		$isDir = is_dir($p);
		$st = stat($p);
		$items[] = array(
			'name' => $n,
			'type' => $isDir ? 'dir' : 'file',
			'ext' => $isDir ? '' : pathinfo($n, PATHINFO_EXTENSION),
			'icon' => $isDir ? 'folder' : file_typeicon(pathinfo($n, PATHINFO_EXTENSION)),
			'size' => $isDir ? 0 : $st['size'],
			'sizetxt' => $isDir ? '' : file_size($st['size']),
			'time' => date('Y-m-d H:i', $st['mtime']),
		);
	}
	closedir($dh);
	// 文件夹在前，按名称排序
	usort($items, function($a, $b) {
		if ($a['type'] !== $b['type']) return $a['type'] === 'dir' ? -1 : 1;
		return strcmp($a['name'], $b['name']);
	});
	return $items;
}

// 新建文件夹
function file_mkdir($rel) {
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	if (file_exists($abs)) return false;
	return mkdir($abs, 0755, true);
}

// 删除文件/文件夹
function file_del($rel) {
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	if (!file_exists($abs)) return false;
	if (is_dir($abs)) {
		$dh = opendir($abs);
		if ($dh) {
			while (($n = readdir($dh)) !== false) {
				if ($n === '.' || $n === '..') continue;
				file_del($rel . '/' . $n);
			}
			closedir($dh);
		}
		return rmdir($abs);
	}
	return unlink($abs);
}

// 重命名
function file_ren($rel, $newname) {
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	if (!file_exists($abs)) return false;
	$dir = dirname($abs);
	$dst = $dir . '/' . basename($newname);
	if (file_exists($dst)) return false;
	return rename($abs, $dst);
}

// 剪切 - 存入 session
function file_cut($rel) {
	if (!isset($_SESSION)) session_start();
	$_SESSION['clip'] = array('type' => 'cut', 'path' => $rel, 'paths' => array($rel));
	return true;
}

// 复制 - 存入 session
function file_copy($rel) {
	if (!isset($_SESSION)) session_start();
	$_SESSION['clip'] = array('type' => 'copy', 'path' => $rel, 'paths' => array($rel));
	return true;
}

// 粘贴（从 session 读源路径，旧版兼容）
function file_paste($rel) {
	if (!isset($_SESSION)) session_start();
	if (!isset($_SESSION['clip'])) return false;
	$clip = $_SESSION['clip'];
	return file_paste_src($clip['path'], $rel, $clip['type']);
}

// 粘贴（直接指定源路径，前端无需设置 session）
function file_paste_src($srcRel, $dstRel, $type) {
	$srcAbs = file_safepath($srcRel);
	$dstAbs = file_safepath($dstRel);
	if ($srcAbs === false || $dstAbs === false) return false;
	if (!file_exists($srcAbs)) return false;
	$basename = basename($srcAbs);
	$dst = $dstAbs . '/' . $basename;
	$dst = file_uniquepath($dst);
	if ($type === 'cut') {
		return rename($srcAbs, $dst) ? $basename : false;
	} else {
		return file_recursive_copy($srcAbs, $dst) ? $basename : false;
	}
}

function file_uniquepath($path) {
	if (!file_exists($path)) return $path;
	$dir = dirname($path);
	$name = pathinfo($path, PATHINFO_FILENAME);
	$ext = pathinfo($path, PATHINFO_EXTENSION);
	$i = 1;
	while (file_exists($dir . '/' . $name . '(' . $i . ').' . $ext)) $i++;
	return $dir . '/' . $name . '(' . $i . ').' . $ext;
}

function file_recursive_copy($src, $dst) {
	if (is_dir($src)) {
		if (!file_exists($dst)) mkdir($dst, 0755, true);
		$dh = opendir($src);
		if (!$dh) return false;
		while (($n = readdir($dh)) !== false) {
			if ($n === '.' || $n === '..') continue;
			file_recursive_copy($src . '/' . $n, $dst . '/' . $n);
		}
		closedir($dh);
		return true;
	}
	return copy($src, $dst);
}

// 获取单文件信息
function file_info($rel) {
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	if (!file_exists($abs)) return false;
	$isDir = is_dir($abs);
	$st = stat($abs);
	$info = array(
		'name' => basename($abs),
		'type' => $isDir ? 'dir' : 'file',
		'isdir' => $isDir,
		'ext' => $isDir ? '' : pathinfo($abs, PATHINFO_EXTENSION),
		'size' => $isDir ? 0 : $st['size'],
		'sizetxt' => $isDir ? '' : file_size($st['size']),
		'time' => date('Y-m-d H:i', $st['mtime']),
		'ctime' => date('Y-m-d H:i', $st['ctime']),
	);
	// 文件夹统计文件数量和总大小
	if ($isDir) {
		$fcnt = 0;
		$fsize = 0;
		$dh = opendir($abs);
		if ($dh) {
			while (($n = readdir($dh)) !== false) {
				if ($n === '.' || $n === '..') continue;
				$fcnt++;
				$fp = $abs . '/' . $n;
				if (is_file($fp)) $fsize += filesize($fp);
			}
			closedir($dh);
		}
		$info['file_count'] = $fcnt;
		$info['total_size'] = file_size($fsize);
	}
	// 图片返回宽高
	if (!$isDir && preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $abs)) {
		$img = @getimagesize($abs);
		if ($img) {
			$info['width'] = $img[0];
			$info['height'] = $img[1];
		}
	}
	return $info;
}