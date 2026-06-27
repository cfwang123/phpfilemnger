<?php
// 文件上传处理

function up_file($dirRel) {
	require_once __DIR__ . '/file.php';

	$absDir = file_safepath($dirRel);
	if ($absDir === false) return array('ok' => false, 'err' => '无效的目录路径');
	if (!file_exists($absDir)) {
		if (!mkdir($absDir, 0755, true))
			return array('ok' => false, 'err' => '创建目录失败');
	}

	$files = isset($_FILES['files']) ? $_FILES['files'] : null;
	if (!$files) return array('ok' => false, 'err' => '没有上传文件');

	// 支持单文件和多文件
	$names = is_array($files['name']) ? $files['name'] : array($files['name']);
	$tmps = is_array($files['tmp_name']) ? $files['tmp_name'] : array($files['tmp_name']);
	$errs = is_array($files['error']) ? $files['error'] : array($files['error']);
	$sizes = is_array($files['size']) ? $files['size'] : array($files['size']);

	$results = array();
	$okCount = 0;

	for ($i = 0; $i < count($names); $i++) {
		$name = $names[$i];
		$tmp = $tmps[$i];
		$err = isset($errs[$i]) ? $errs[$i] : UPLOAD_ERR_NO_FILE;
		$size = isset($sizes[$i]) ? $sizes[$i] : 0;

		if ($err !== UPLOAD_ERR_OK) {
			$results[] = array('name' => $name, 'ok' => false, 'err' => '上传错误 code:' . $err);
			continue;
		}
		if ($tmp === '' || !file_exists($tmp)) {
			$results[] = array('name' => $name, 'ok' => false, 'err' => '临时文件不存在');
			continue;
		}
		// 禁止上传 .php 文件
		if (stripos($name, '.php') !== false) {
			$results[] = array('name' => $name, 'ok' => false, 'err' => '禁止上传 PHP 文件');
			continue;
		}

		$dst = $absDir . '/' . basename($name);
		$dst = file_uniquepath($dst);
		if (move_uploaded_file($tmp, $dst)) {
			$results[] = array('name' => basename($dst), 'ok' => true, 'size' => $size);
			$okCount++;
		} else {
			$results[] = array('name' => $name, 'ok' => false, 'err' => '移动文件失败');
		}
	}

	return array(
		'ok' => $okCount > 0,
		'count' => $okCount,
		'total' => count($names),
		'results' => $results,
	);
}