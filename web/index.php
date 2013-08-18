<?php
//根路径
define( 'ROOT_PATH',  dirname( dirname( __FILE__ ) ) . '/' );
$GLOBALS[ 'SERVER_HOST' ] = $_SERVER[ 'HTTP_HOST' ];
//加载全局库文件
require ROOT_PATH .'lib/lib_global.php';
require ROOT_PATH .'tool/tool.php';
require_once ROOT_PATH .'lib/lib_db.php';
require_once ROOT_PATH .'lib/lib_cached.php';
require_once ROOT_PATH .'lib/lib_plugin.php';
require_once ROOT_PATH .'func/cache_func.php';
require_once ROOT_PATH .'func/common_func.php';
require_once ROOT_PATH .'func/im_func.php';
require_once ROOT_PATH .'func/game_func.php';
require_once ROOT_PATH .'main/etc/include.php';
http_request();