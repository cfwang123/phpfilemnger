<?php
// 文件下载 & ZIP 流式压缩

// MIME 类型映射
function down_mime($ext) {
	$map = array(
		'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
		'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'bmp' => 'image/bmp', 'ico' => 'image/x-icon',
		'mp4' => 'video/mp4', 'mkv' => 'video/x-matroska', 'avi' => 'video/x-msvideo',
		'webm' => 'video/webm', 'mov' => 'video/quicktime', 'wmv' => 'video/x-ms-wmv',
		'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac', 'ogg' => 'audio/ogg',
		'pdf' => 'application/pdf', 'zip' => 'application/zip',
		'html' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript',
		'json' => 'application/json', 'xml' => 'application/xml', 'csv' => 'text/csv',
		'txt' => 'text/plain', 'php' => 'text/plain', 'toml' => 'text/plain', 'md' => 'text/plain',
	);
	$ext = strtolower($ext);
	return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

// 单个文件下载（支持 Range 断点续传）
function down_file($rel) {
	$abs = file_safepath($rel);
	if ($abs === false || !file_exists($abs) || is_dir($abs)) {
		header('HTTP/1.0 404 Not Found');
		echo 'File not found';
		exit;
	}
	$ext = pathinfo($abs, PATHINFO_EXTENSION);
	$name = basename($abs);
	$size = filesize($abs);
	$mime = down_mime($ext);

	header('Content-Type: ' . $mime);
	header('Accept-Ranges: bytes');

	// 解析 Range 头
	$range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : '';
	if ($range !== '' && preg_match('/bytes=(\d+)-(\d*)/', $range, $m)) {
		$start = intval($m[1]);
		$end = $m[2] !== '' ? intval($m[2]) : $size - 1;
		if ($start >= $size || $end >= $size) {
			header('HTTP/1.1 416 Range Not Satisfiable');
			header('Content-Range: bytes */' . $size);
			exit;
		}
		$len = $end - $start + 1;
		header('HTTP/1.1 206 Partial Content');
		header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
		header('Content-Length: ' . $len);
		if (isset($_GET['dl']) && $_GET['dl'] === '1') {
			header('Content-Disposition: attachment; filename="' . $name . '"');
		}
		$fh = fopen($abs, 'rb');
		fseek($fh, $start);
		$left = $len;
		while ($left > 0) {
			$read = min($left, 8192);
			echo fread($fh, $read);
			$left -= $read;
		}
		fclose($fh);
	} else {
		header('Content-Length: ' . $size);
		if (isset($_GET['dl']) && $_GET['dl'] === '1') {
			header('Content-Disposition: attachment; filename="' . $name . '"');
		}
		readfile($abs);
	}
	exit;
}

// ZIP 压缩下载（PHP 8 原生 ZipArchive）
function down_zip($paths, $zipname) {
	if (!is_array($paths)) $paths = array($paths);
	if (count($paths) === 0) {
		header('HTTP/1.0 400 Bad Request');
		echo 'No files specified';
		exit;
	}
	if ($zipname === '') $zipname = 'download.zip';
	if (substr($zipname, -4) !== '.zip') $zipname .= '.zip';

	$tmp = tempnam(sys_get_temp_dir(), 'zip_');
	$zip = new ZipArchive();
	if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
		header('HTTP/1.0 500 Internal Server Error');
		echo 'Failed to create zip';
		exit;
	}
	foreach ($paths as $rel) {
		$abs = file_safepath($rel);
		if ($abs === false || !file_exists($abs)) continue;
		down_zip_add($zip, $abs, basename($abs));
	}
	$zip->close();

	$size = filesize($tmp);
	header('Content-Type: application/zip');
	header('Content-Length: ' . $size);
	header('Content-Disposition: attachment; filename="' . $zipname . '"');

	$fh = fopen($tmp, 'rb');
	while (!feof($fh)) echo fread($fh, 8192);
	fclose($fh);
	unlink($tmp);
	exit;
}

function down_zip_add($zip, $abs, $entry) {
	if (is_dir($abs)) {
		$zip->addEmptyDir($entry);
		$dh = @opendir($abs);
		if ($dh) {
			while (($n = readdir($dh)) !== false) {
				if ($n === '.' || $n === '..') continue;
				// 与文件列表保持一致，跳过 .php 文件
				if (stripos($n, '.php') !== false) continue;
				down_zip_add($zip, $abs . '/' . $n, $entry . '/' . $n);
			}
			closedir($dh);
		}
	} else {
		// 单个文件加入时也检查
		if (stripos(basename($abs), '.php') !== false) return;
		$zip->addFile($abs, $entry);
	}
}
