<?php
// 站点配置
return array(
	'system' => array(
		'site_name' => 'Web文件管理系统',
		'fs_encoding' => 'UTF-8', // 文件系统编码，PHP 7.4+ Windows 已支持 UTF-8 路径
	),
	'users' => array(
		array('name' => 'admin', 'pass' => '123456', 'level' => 'admin'),
		array('name' => 'user', 'pass' => '123456', 'level' => 'normal'),
	),
);
