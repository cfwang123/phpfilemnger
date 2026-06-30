<?php
// 文件上传处理

function up_file($dirRel) {

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
		// 对文件名消毒（$_FILES 来自系统编码如 GBK，先转 UTF-8）
		$name = file_fromfspath($name);
		$name = file_safename($name);
		if ($name === '') {
			$results[] = array('name' => $names[$i], 'ok' => false, 'err' => '文件名不合法');
			continue;
		}
		// 禁止上传 .php 文件
		if (stripos($name, '.php') !== false) {
			$results[] = array('name' => $name, 'ok' => false, 'err' => '禁止上传 PHP 文件');
			continue;
		}

		$nameFs = file_tofspath($name);
		$dst = $absDir . '/' . $nameFs;
		$dst = file_uniquepath($dst);
		if (move_uploaded_file($tmp, $dst)) {
			$results[] = array('name' => $name, 'ok' => true, 'size' => $size);
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