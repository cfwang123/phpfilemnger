<?php
// 文件下载 & ZIP 流式压缩

require_once __DIR__ . '/file.php';

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

// 单个文件下载
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

	header('Content-Type: ' . down_mime($ext));
	header('Content-Length: ' . $size);
	header('Content-Disposition: attachment; filename="' . $name . '"');	header('Accept-Ranges: bytes');
	readfile($abs);
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
		$dh = opendir($abs);
		if ($dh) {
			while (($n = readdir($dh)) !== false) {
				if ($n === '.' || $n === '..') continue;
				down_zip_add($zip, $abs . '/' . $n, $entry . '/' . $n);
			}
			closedir($dh);
		}
	} else {
		$zip->addFile($abs, $entry);
	}
}
