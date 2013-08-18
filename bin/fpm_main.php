<?php
if ( !isset( $argv[ 1 ] ) )
{
	die( "please input host!\n" );
}
$is_daemon = isset( $argv[ 2 ] ) && '-d' == $argv[ 2 ];
$GLOBALS[ 'SERVER_HOST' ] = $argv[ 1 ];
define( 'ROOT_PATH', dirname( dirname( __FILE__ ) ) .'/' );
require_once ROOT_PATH .'func/fpm_func.php';
if ( $is_daemon )
{
	fpm_daemon();
}
$GLOBALS[ 'SERVER_TYPE' ] = SERVER_TYPE_FPM_MAIN;
//检查主进程是否已经在运行
if ( is_file( $GAME_INI[ 'fpm_pid_file' ] ) && first_kill( file_get_contents( $GAME_INI[ 'fpm_pid_file' ] ), 0 ) )
{
	die( "fpm_main already running!\n" );
}
//初始化进程
fpm_init( __FILE__, true );

//所有连接信息
$GLOBALS[ 'connect_list' ] = array();
//所有进程信息
$GLOBALS[ 'fpm_list' ] = array();
//代理数据池
$GLOBALS[ 'proxy_poll' ] = array();
//初始化其它进程
fpm_check_child();
file_put_contents( $GAME_INI[ 'fpm_pid_file' ], first_getpid() );

//系统是否正在退出
$SERVICE_QUIT_FLAG = false;
//程序是否继续运行
$SERVICE_RUN_FLAG = true;
while ( $SERVICE_RUN_FLAG )
{
	fpm_wait( 'fpm_main_event', true );
}
unlink( $GAME_INI[ 'fpm_pid_file' ] );
echo "\nfpm_main quit!\n";