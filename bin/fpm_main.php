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
//所有连接信息
$GLOBALS[ 'connect_list' ] = array();

//初始化进程
fpm_init( __FILE__, YILE_FPM_MAIN );

file_put_contents( $GAME_INI[ 'fpm_pid_file' ], first_getpid() );
while ( $FPM_RUN_FLAG )
{
	fpm_wait( 'fpm_main_event', true );
}
fpm_main_wait_child_quit();
unlink( $GAME_INI[ 'fpm_pid_file' ] );
echo "\nfpm_main quit!\n";