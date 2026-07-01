<?php
// 公共初始化

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 加载库文件（按依赖顺序）
require_once __DIR__ . '/conf.php';
require_once __DIR__ . '/auth.php';

// 登录检查（需要在 auth 之后，但 login.php 自己不需要）
// 页面自行调用 auth_check()

require_once __DIR__ . '/log.php';

// 定义常量
define('ROOT_DIR', dirname(__DIR__));
define('UP_DIR', ROOT_DIR);