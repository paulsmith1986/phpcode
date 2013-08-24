<?php
$GLOBALS[ 'SERVER_HOST' ] = $argv[ 1 ];
define( 'ROOT_PATH', dirname( dirname( __FILE__ ) ) .'/' );
require_once ROOT_PATH .'func/fpm_func.php';
fpm_daemon();
require_once ROOT_PATH .'lib/lib_db.php';
require_once ROOT_PATH .'lib/lib_cached.php';
require_once ROOT_PATH .'lib/lib_plugin.php';
require_once ROOT_PATH .'func/cache_func.php';
require_once ROOT_PATH .'func/common_func.php';
require_once ROOT_PATH .'func/im_func.php';
require_once ROOT_PATH .'func/game_func.php';
require_once ROOT_PATH .'main/etc/include.php';
//初始化进程
fpm_init( __FILE__, YILE_FPM_SUB );
while( $FPM_RUN_FLAG )
{
	fpm_wait( 'fpm_event' );
}
first_close_fd( $GLOBALS[ 'FPM_MAIN_FD' ] );
