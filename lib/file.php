<?php
// 文件操作 API

if (!defined('UP_DIR')) define('UP_DIR', file_fromfspath(realpath(__DIR__ . '/..')));

// 安全文件名：只保留合法字符，去除路径穿越/控制字符/Windows保留名
function file_safename($name) {
	// 去除路径分隔符和空字节
	$name = str_replace(array('\\', '/', "\0"), '', $name);
	// 去除控制字符（0x00-0x1F、0x7F）
	$name = preg_replace('/[\x00-\x1f\x7f]/', '', $name);
	// 只保留：中/日/韩/字母/数字/空格/常见符号
	$name = preg_replace('/[^\x{4e00}-\x{9fff}\x{3040}-\x{309f}\x{30a0}-\x{30ff}\x{ac00}-\x{d7af}a-zA-Z0-9._\-()\[\]{}~!@#$%^&+= ,]/u', '', $name);
	// 去除末尾的点/空格（Windows 会静默删除）
	$name = rtrim($name, '. ');
	// 限制长度 200
	if (mb_strlen($name) > 200) $name = mb_substr($name, 0, 200);
	// Windows 保留名处理
	if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(\..*)?$/i', $name)) {
		$name = '_' . $name;
	}
	return $name;
}

// 将 UTF-8 路径转为文件系统编码（根据配置）
function file_tofspath($path) {
	if (DIRECTORY_SEPARATOR !== '\\') return $path;
	$enc = conf_get('system.fs_encoding', 'UTF-8');
	if ($enc === 'UTF-8') return $path;
	// 显式转换：调用方保证传入 UTF-8
	$conv = mb_convert_encoding($path, $enc, 'UTF-8');
	return $conv !== false ? $conv : $path;
}

// 将文件系统编码转为 UTF-8（根据配置）
function file_fromfspath($path) {
	if (DIRECTORY_SEPARATOR !== '\\') return $path;
	$enc = conf_get('system.fs_encoding', 'UTF-8');
	if ($enc === 'UTF-8') return $path;
	if (mb_check_encoding($path, 'UTF-8')) return $path;
	$conv = mb_convert_encoding($path, 'UTF-8', $enc);
	return $conv !== false ? $conv : $path;
}

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
	// 转为文件系统编码（根据配置）
	$abs = file_tofspath($abs);
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
		'md' => 'code', 'yml' => 'code', 'yaml' => 'code', 'cfg' => 'code', 'env' => 'code', 'sql' => 'code',
		'exe' => 'exe', 'msi' => 'exe', 'bin' => 'exe',
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

// 从配置读取忽略文件列表
function file_ignore_list() {
	return conf_get('system.ignore_files', array('lib', 'index.php', '.htaccess', '.gitignore'));
}

// 检查路径是否命中忽略列表（路径首段在忽略列表中则视为忽略）
function file_is_ignored($rel) {
	$rel = str_replace('\\', '/', $rel);
	$rel = trim($rel, '/');
	$first = explode('/', $rel)[0];
	return in_array($first, file_ignore_list());
}

function file_is_protected_ext($name) {
	$name = trim((string)$name);
	if ($name === '') return false;
	$name = basename(str_replace('\\', '/', $name));
	$lower = strtolower($name);
	if ($lower === '.htaccess') return true;
	$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
	if ($ext === '') return false;
	return in_array($ext, array('php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'htaccess'), true);
}

function file_has_protected_ext($rel) {
	$rel = str_replace('\\', '/', $rel);
	$rel = trim($rel, '/');
	if ($rel === '') return false;
	$parts = explode('/', $rel);
	foreach ($parts as $part) {
		if (file_is_protected_ext($part)) return true;
	}
	return false;
}

// 列出目录
function file_list($rel, $ignore = array()) {
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	if (!is_dir($abs)) {
		@mkdir($abs, 0755, true);
		return array();
	}
	$items = array();
	$dh = @opendir($abs);
	if (!$dh) return false;
	while (($n = readdir($dh)) !== false) {
		if ($n === '.' || $n === '..') continue;
		if (in_array($n, $ignore)) continue;
		// 隐藏 .php 后缀文件
		if (stripos($n, '.php') !== false) continue;
		// 文件名转 UTF-8 输出（json_encode 要求）
		$nOut = file_fromfspath($n);
		$nFs = file_tofspath($n);
		$p = $abs . '/' . $nFs;
		$isDir = @is_dir($p);
		$st = @stat($p);
		$items[] = array(
			'name' => $nOut,
			'type' => $isDir ? 'dir' : 'file',
			'ext' => $isDir ? '' : pathinfo($nOut, PATHINFO_EXTENSION),
			'icon' => $isDir ? 'folder' : file_typeicon(pathinfo($nOut, PATHINFO_EXTENSION)),
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
	// 对末级目录名消毒
	$parts = explode('/', trim($rel, '/'));
	$last = array_pop($parts);
	$last = file_safename($last);
	if ($last === '') return false;
	$parts[] = $last;
	$rel = implode('/', $parts);
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	if (file_exists($abs)) return false;
	return mkdir($abs, 0755, true);
}

// 删除文件/文件夹
function file_del($rel) {
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	clearstatcache(true, $abs);
	if (!file_exists($abs)) return false;
	return file_del_byabs($abs);
}

// 内部递归删除（使用绝对路径，避免编码问题）
function file_del_byabs($abs) {
	if (is_link($abs)) {
		// 软链接/目录交接点：只删除链接本身，不删目标内容
		return rmdir($abs);
	}
	if (is_dir($abs)) {
		$dh = @opendir($abs);
		if ($dh) {
			while (($n = readdir($dh)) !== false) {
				if ($n === '.' || $n === '..') continue;
				file_del_byabs($abs . '/' . $n);
			}
			closedir($dh);
		}
		return rmdir($abs);
	}
	return unlink($abs);
}

// 重命名
function file_ren($rel, $newname) {
	$newname = file_safename($newname);
	if ($newname === '') return false;
	if (file_has_protected_ext($rel) || file_is_protected_ext($newname)) return false;
	$abs = file_safepath($rel);
	if ($abs === false) return false;
	if (!file_exists($abs)) return false;
	$dir = dirname($abs);
	$dst = $dir . '/' . file_tofspath($newname);
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
	if (file_has_protected_ext($srcRel) || file_has_protected_ext($dstRel)) return false;
	$srcAbs = file_safepath($srcRel);
	$dstAbs = file_safepath($dstRel);
	if ($srcAbs === false || $dstAbs === false) return false;
	if (!file_exists($srcAbs)) return false;
	// basename 是 GBK，先转 UTF-8 再消毒
	$basename = file_fromfspath(basename($srcAbs));
	$basename = file_safename($basename);
	if ($basename === '') return false;
	$dst = $dstAbs . '/' . file_tofspath($basename);
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
	$dst = $dir . '/' . $name . '(' . $i . ').' . $ext;
	return $dst;
}

function file_recursive_copy($src, $dst) {
	if (is_dir($src)) {
		if (!file_exists($dst)) mkdir($dst, 0755, true);
		$dh = @opendir($src);
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
	// 确保文件名 UTF-8 编码
	$name = file_fromfspath(basename($abs));
	$info = array(
		'name' => $name,
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
		$dh = @opendir($abs);
		if ($dh) {
			while (($n = readdir($dh)) !== false) {
				if ($n === '.' || $n === '..') continue;
				$fcnt++;
				$nFs = file_tofspath($n);
				$fp = $abs . '/' . $nFs;
				if (@is_file($fp)) $fsize += @filesize($fp);
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
