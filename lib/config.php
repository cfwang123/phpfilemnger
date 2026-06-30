<?php
// 站点配置
return array(
	'system' => array(
		'site_name' => 'Web文件管理系统',
		'fs_encoding' => 'UTF-8', // 文件系统编码，PHP 7.4+ Windows 已支持 UTF-8 路径
		'ignore_files' => array('lib', 'index.php', '.htaccess', '.gitignore'), // 根目录隐藏/保护项
		'delete_confirm_file' => false, // 删除文件时是否确认
		'delete_confirm_dir' => true, // 删除文件夹时是否确认
	),
	'users' => array(
		array('name' => 'admin', 'pass' => '123456', 'level' => 'admin'),
		array('name' => 'user', 'pass' => '123456', 'level' => 'normal'),
	),
);
