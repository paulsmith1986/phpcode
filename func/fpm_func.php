<?php
require_once ROOT_PATH .'lib/lib_global.php';
/**
 * 转发数据
 * @param array $req_data 转发数据
 */
function fpm_proxy( $req_data )
{
	global $fpm_list;
	$fpm_id = $req_data[ 'hash_id' ] % $GLOBALS[ 'FPM_CHILD_NUM' ];
	$fpm_info = $fpm_list[ $fpm_id ];
	if ( $fpm_info[ 'fd' ] > 0 && FIRST_FPM_STATUS_IDLE == $fpm_info[ 'status' ] )
	{
		fpm_wakeup( $fpm_id, $req_data );
	}
	else
	{
		$fpm_list[ $fpm_id ][ 'proxy' ][] = $req_data;
	}
}

/**
 * 让某个进程开始工作
 * @param int $fmp_id 进程id
 * @param string $data 需要发送的数据
 */
function fpm_wakeup( $fpm_id, $data )
{
	global $fpm_list;
	$fpm_info = $fpm_list[ $fpm_id ];
	if ( $fpm_info[ 'fd' ] < 0 )
	{
		return;
	}
	first_send_data( $fpm_info[ 'fd' ], $data[ 'proto_data' ] );
	$fpm_list[ $fpm_id ][ 'status' ] = FIRST_FPM_STATUS_WORK;
}

/**
 * 初始化进程
 * @param string $file_name 进程的文件名
 * @param int $fpm_type 进程类型
 */
function fpm_init( $file_name, $fpm_type )
{
	global $argv, $PROTOCOL_ID_LIST, $FPM_RUN_FLAG;
	if ( isset( $argv[ 2 ] ) && is_numeric( $argv[ 2 ] ) )
	{
		$GLOBALS[ 'PHP_FPM_PID' ] = (int)$argv[ 2 ];
	}
	$FPM_RUN_FLAG = true;
	$GLOBALS[ 'TASK_FILE' ] = basename( $file_name, '.php' );
	if ( FIRST_FPM_MAIN == $fpm_type )
	{
		//创建主机
		first_host( '127.0.0.1', $GLOBALS[ 'FPM_CONF' ][ 'port' ] );
		first_set_fpm_type( FIRST_FPM_MAIN );
		fpm_init_child();
		fpm_main_timeup( null );
	}
	else
	{
		$join_re = fpm_connect_im( $fpm_type );
		if ( !$join_re )
		{
			show_excp( 'Can not join im server' );
		}
		first_set_fpm_type( FIRST_FPM_SUB );
		$GLOBALS[ 'FPM_MAIN_FD' ] = first_socket_fd( '127.0.0.1', $GLOBALS[ 'FPM_CONF' ][ 'port' ] );
		$data = array( 'pid' => first_getpid(), 'fpm_id' => $GLOBALS[ 'PHP_FPM_PID' ] );
		fpm_child_to_main( 'fpm_join', $data );
	}
	//日志文件检查
	fpm_check_log_file();
	//设置需要捕获的信号
	first_signal_fd( array( FIRST_SIGHUP, FIRST_SIGINT, FIRST_SIGTERM, FIRST_SIGPIPE, FIRST_SIGUSR1, FIRST_SIGUSR2 ) );
}

/**
 * 初始化子进程
 */
function fpm_init_child()
{
	global $fpm_list, $FPM_CHILD_NUM;
	$FPM_CHILD_NUM = $GLOBALS[ 'FPM_CONF' ][ 'max_fpm' ];
	$fpm_list = array();
	for ( $i = 0; $i < $FPM_CHILD_NUM; ++$i )
	{
		$fpm_info = array(
			'status'		=> FIRST_FPM_STATUS_IDLE,
			'fd'			=> -1,
			'proxy'			=> array(),					//代理池列表
		);
		$fpm_list[ $i ] = $fpm_info;
	}
}

/**
 * 设置主进程的倒计时
 */
function fpm_main_set_timeout()
{
	if ( !isset( $GLOBALS[ 'heart_beat' ] ) )
	{
		$GLOBALS[ 'heart_beat' ] = first_timer_fd();
	}
	first_set_timeout( $GLOBALS[ 'heart_beat' ], 15000 );
}

/**
 * 子进程向主进程发数据包
 * @param string $pack_name 协议包名
 * @param array $data 数据
 */
function fpm_child_to_main( $pack_name, $data )
{
	global $PROTOCOL_ID_LIST, $FPM_MAIN_FD;
	if ( -1 == $FPM_MAIN_FD )
	{
		return;
	}
	$pack_id = $PROTOCOL_ID_LIST[ $pack_name ];
	$re = first_send_pack( $FPM_MAIN_FD, $pack_id, $data );
	if ( true !== $re )
	{
		show_excp( 'Can not set data to fpm_main' );
	}
}

/**
 * fpm子进程加入主进程
 */
function fpm_join_mod( $req_data )
{
	global $connect_list, $fpm_list;
	$fd = $req_data[ 'fd' ];
	$fpm_id = $req_data[ 'fpm_id' ];
	$fd_info = array(
		'pid'			=> $req_data[ 'pid' ],
		'ping'			=> time(),
		'fpm_id'		=> -1
	);
	//fpm_id出错
	if ( !isset( $fpm_list[ $fpm_id ] ) )
	{
		show_error( 'Join bad fpm, fpm_id:', $fpm_id, "\n" );
		fpm_close_fd( $fd );
		return;
	}
	echo 'Join child pid:', $fpm_id, "\n";
	$fpm_info = $fpm_list[ $fpm_id ];
	if ( $fpm_info[ 'fd' ] > 0 )
	{
		fpm_close_fd( $fpm_info[ 'fd' ] );
	}
	$fd_info[ 'fpm_id' ] = $fpm_id;
	$connect_list[ $fd ] = $fd_info;
	$fpm_list[ $fpm_id ][ 'fd' ] = $fd;
	$report = array( 'fpm_id' => $fpm_id, 'fd' => $fd );
	fpm_idle_report_mod( $report );
}

/**
 * 关闭一个fd
 * @param int $fd 连接标志
 * @param bool $force_kill 是否强杀
 */
function fpm_close_fd( $fd, $force_kill = false )
{
	global $connect_list, $fpm_list;
	$fd_info = fpm_get_fd_info( $fd );
	if ( $fd_info )
	{
		$fpm_id = $fd_info[ 'fpm_id' ];
		if ( $fpm_id >= 0 && isset( $fpm_list[ $fpm_id ] ) && $fpm_list[ $fpm_id ][ 'fd' ] == $fd )
		{
			$fpm_list[ $fpm_id ][ 'fd' ] = -1;
		}
		first_kill( $fd_info[ 'pid' ], $force_kill ? FIRST_SIGKILL : FIRST_SIGTERM );
	}
	unset( $connect_list[ $fd ] );
}

/**
 * 获取一个fd的信息
 * @param int $fd 连接id
 *
 */
function fpm_get_fd_info( $fd )
{
	global $connect_list;
	return isset( $connect_list[ $fd ] ) ? $connect_list[ $fd ] : null;
}

/**
 * 子进程响应主进程ping
 * @param array $req_data 请求数据包
 */
function fpm_ping_re_mod( $req_data )
{
	global $connect_list;
	$fd = $req_data[ 'fd' ];
	if ( !isset( $connect_list[ $fd ] ) )
	{
		return;
	}
	$connect_list[ $fd ][ 'ping' ] = $req_data[ 'time' ];
}

/**
 * 断开连接
 * @param array $req_data 请求数据包
 */
function fpm_quit_mod( $req_data )
{
	$fd = $req_data[ 'fd' ];
	if ( $GLOBALS[ 'IM_SERVER_PING' ] == $fd )
	{
		echo "Im fd quit!\n";
		$GLOBALS[ 'IM_SERVER_PING' ] = -1;
		return;
	}
	//进程正在关闭
	if ( !$GLOBALS[ 'FPM_RUN_FLAG' ] )
	{
		return;
	}
	$fd_info = fpm_get_fd_info( $fd );
	if ( !$fd_info )
	{
		return;
	}
	global $connect_list, $fpm_list;
	echo 'Close fd:',$fd, ' pid:', $fd_info[ 'pid' ], ' fpm_id:', $fd_info[ 'fpm_id' ];
	$fpm_id = $fd_info[ 'fpm_id' ];
	unset( $connect_list[ $fd ] );
	if ( isset( $fpm_list[ $fpm_id ] ) && $fpm_list[ $fpm_id ][ 'fd' ] == $fd )
	{
		$fpm_list[ $fpm_id ][ 'fd' ] = -1;
		fpm_check_child();
	}
}

/**
 * 以守护进程方式运行
 */
function fpm_daemon()
{
	if ( first_fork() )
	{
		exit;
	}
	$sid = first_setsid();
	if ( $sid < 0 )
	{
		exit;
	}
}

/**
 * 进程空闲报告
 * @param array $req_data 请求数据包
 */
function fpm_idle_report_mod( $req_data )
{
	global $connect_list, $fpm_list;
	$fd = $req_data[ 'fd' ];
	if ( !isset( $connect_list[ $fd ] ) )
	{
		return;
	}
	$fpm_id = $req_data[ 'fpm_id' ];
	$fd_info = $fpm_list[ $fpm_id ];
	//有数据待发
	if ( !empty( $fd_info[ 'proxy' ] ) )
	{
		$proto_data = array_shift( $fpm_list[ $fpm_id ][ 'proxy' ] );
		fpm_wakeup( $fpm_id, $proto_data );
	}
	else
	{
		$fpm_list[ $fpm_id ][ 'status' ] = FIRST_FPM_STATUS_IDLE;
	}
}

/**
 * 检查进程个数
 */
function fpm_check_child( )
{
	global $fpm_list;
	foreach ( $fpm_list as $fpm_id => $fpm_info )
	{
		if ( -1 != $fpm_info[ 'fd' ] )
		{
			$fd_info = fpm_get_fd_info( $fpm_info[ 'fd' ] );
			if ( $fd_info )
			{
				continue;
			}
		}
		fpm_start( $fpm_id );
	}
}

/**
 * 初始化service进程
 * @param int $pid 进程编号
 */
function fpm_start( $pid )
{
	$start_log_file = make_log_file( 'run' );
	$cmd = 'php '. ROOT_PATH .'bin/fpm.php '. $GLOBALS[ 'SERVER_HOST' ] .' '. $pid .' >> '. $start_log_file .' 2>&1 &';
	exec( $cmd );
}

/**
 * 等待事件发生
 * @param string $callback 回调函数
 * @param bool $is_main 是否是主进程
 */
function fpm_wait( $callback, $is_main = false )
{
	//收集所有发生的事件
	$req_list = first_poll();
	if ( null == $req_list )
	{
		return;
	}
	foreach ( $req_list as $each_req )
	{
		ob_start();
		pr( $each_req );
		try
		{
			$callback( $each_req );
		}
		catch ( Exception $excp_obj )
		{
			do_with_excp( $excp_obj );
		}
		do_end();
	}
	unset( $req_list );
	if ( !$is_main )
	{
		$report_data = array( 'fpm_id' => $GLOBALS[ 'PHP_FPM_PID' ] );
		fpm_child_to_main( 'fpm_idle_report', $report_data );
	}
}

/**
 * 主进程的事件处理
 * @param array $req_data 事件数据
 */
function fpm_main_event( $req_data )
{
	switch ( $req_data[ 'event_type' ] )
	{
		case FIRST_SOCKET_DATA:		//socket数据到达
			//60000数据包直接转发
			if ( 60000 == $req_data[ 'pack_id' ] )
			{
				fpm_proxy( $req_data );
			}
			else
			{
				fpm_main_request( $req_data );
			}
		break;
		case FIRST_SOCKET_CLOSE:		//连接断开
			fpm_quit_mod( $req_data );
		break;
		case FIRST_SIGNAL:			//信号
			fpm_main_signal( $req_data );
		break;
		case FIRST_TIME_UP:			//倒计时
			fpm_main_timeup( $req_data );
		break;
	}
}

/**
 * 服务进程的事件处理
 * @param array $req_data 事件数据
 */
function fpm_event( $req_data )
{
	switch ( $req_data[ 'event_type' ] )
	{
		case FIRST_SOCKET_DATA:		//socket数据到达
			fpm_request( $req_data );
		break;
		case FIRST_SIGNAL:			//信号
			fpm_signal( $req_data );
		break;
		case FIRST_SOCKET_CLOSE:
			echo "Fpm socket close!\n";
			$GLOBALS[ 'FPM_RUN_FLAG' ] = false;
			$GLOBALS[ 'FPM_MAIN_FD' ] = -1;
		break;
	}
}

/**
 * main进程收到信号
 * @param array $signal_arr 信号数据
 */
function fpm_main_signal( $signal_arr )
{
	echo 'Catch signal:', $signal_arr[ 'signal' ], "\n";
	switch ( $signal_arr[ 'signal' ] )
	{
		case FIRST_SIGINT:	//Ctrl + c
		case FIRST_SIGTERM:	//kill
			fpm_kill_all();
			$GLOBALS[ 'FPM_RUN_FLAG' ] = false;
		break;
		case FIRST_SIGUSR1:	//代码更新, 子进程重新加载
			echo "All child restart!\n";
			fpm_kill_all();
			fpm_check_child();
		break;
	}
}

/**
 * 服务进程收到信号
 * @param array $signal_arr 信号数据
 */
function fpm_signal( $signal_arr )
{
	echo $GLOBALS[ 'PHP_FPM_PID' ] .':', first_getpid(), ' Catch signal:', $signal_arr[ 'signal' ], "\n";
	switch ( $signal_arr[ 'signal' ] )
	{
		case FIRST_SIGINT:	//Ctrl + c
		case FIRST_SIGTERM:	//kill
			$GLOBALS[ 'FPM_RUN_FLAG' ] = false;
			$GLOBALS[ 'FPM_MAIN_FD' ] = -1;
		break;
	}
}

/**
 * 主进程响应请求
 * @param array $req_data 请求数据
 */
function fpm_main_request( $req_data )
{
	global $PROTO_ID_NAME;
	$pack_id = $req_data[ 'pack_id' ];
	if ( !isset( $PROTO_ID_NAME[ $pack_id ] ) )
	{
		show_excp( 'Unkown pack_id:'. $pack_id );
	}
	$pack_name = $PROTO_ID_NAME[ $pack_id ];
	//主进程需要处理的包
	$main_pack = array(
		'fpm_join'				=> true,					//子进程加入包
		'fpm_idle_report'		=> true,					//空闲报告包
		'fpm_ping_re'			=> true,					//响应ping包
		'so_http_request'		=> true,					//http请求 不解包
	);
	if ( isset( $main_pack[ $pack_name ] ) )
	{
		$data = first_unpack( $req_data[ 'proto_data' ] );
		$data[ 'fd' ] = $req_data[ 'fd' ];
		fpm_dispatch( $pack_id, $data );
	}
	else
	{
		fpm_proxy( $req_data );
	}
}

/**
 * 新请求到达
 * @param array $req_data 请求数据包
 */
function fpm_request( $req_data )
{
	if ( ( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_DEVELOPMENT ) )
	{
		init_php_total();
	}
	$pack_id = $req_data[ 'pack_id' ];
	if ( 60000 == $pack_id )
	{
		$tmp_data = first_proxy_unpack( $req_data[ 'proto_data' ] );
		$pack_id = $tmp_data[ 'PROXY_PACK_ID' ];
		$GLOBALS[ 'ROLE_COOKIE' ] = array( 'role_id' => $tmp_data[ 'role_id' ], 'session_id' => $tmp_data[ 'session_id' ], 'pack_id' => $pack_id );
		$data = proto_unpack_data( $tmp_data[ 'proto_data' ] );
	}
	else
	{
		$data = first_unpack( $req_data[ 'proto_data' ] );
	}
	fpm_dispatch( $pack_id, $data );
	game_commit();
}

/**
 * 派发请求
 * @param int $pack_id 包id
 * @param array $req_data 请求数据
 */
function fpm_dispatch( $pack_id, $req_data )
{
	global $PROTO_ID_NAME;
	if ( !isset( $PROTO_ID_NAME[ $pack_id ] ) )
	{
		show_excp( 'Unkown pack_id:'. $pack_id );
	}
	$_POST = $req_data;
	$fun_name = 'c_'. $PROTO_ID_NAME[ $pack_id ];
	$fun_name();
	unset( $_POST );
}

/**
 * 主进程周期性检查
 * @param array $req_array 收到的数据包
 */
function fpm_main_timeup( $rea_array )
{
	global $connect_list;
	fpm_main_set_timeout();
	if ( $GLOBALS[ 'IM_SERVER_PING' ] < 0 )
	{
		$join_re = fpm_connect_im( FIRST_FPM_MAIN );
		if ( !$join_re )
		{
			return;
		}
	}
	echo "Fpm_main ping child.\n";
	$time = time();
	$pack_id = $GLOBALS[ 'PROTOCOL_ID_LIST' ][ 'fpm_ping' ];
	foreach ( $connect_list as $fd => $fd_info )
	{
		//30秒以上不响应ping的进程，kill掉
		if ( $time - $fd_info[ 'ping' ] > 30 )
		{
			fpm_close_fd( $fd, true );
		}
		else
		{
			first_send_pack( $fd, $pack_id, array( 'time' => $time ) );
		}
	}
	fpm_check_child();
}

/**
 * 检查日志文件是否应该重新打开一个新的
 */
function fpm_check_log_file()
{
	global $LOG_FILE;
	foreach ( $LOG_FILE as $log_type => $file_handle )
	{
		if ( !is_resource( $file_handle) )
		{
			continue;
		}
		fclose( $file_handle );
		unset( $LOG_FILE[ $log_type ] );
	}
	//记录日志文件打开的时间
	$LOG_FILE[ '_STAR_TIME_' ] = time();
}

/**
 * 杀死所有进程
 */
function fpm_kill_all()
{
	global $connect_list;
	foreach ( $connect_list as $fd => $fd_info )
	{
		fpm_close_fd( $fd );
	}
}

/**
 * 执行fpm的shell命令
 * @param int $cmd_type 命令类型
 */
function fpm_run_shell( $cmd_type = 0 )
{
	$grep_str = '"/fpm.php '. $GLOBALS[ 'SERVER_HOST' ] .' [0-9]"';
	switch ( $cmd_type )
	{
		case 0:		//返回进程个数
			$cmd = 'ps -efww | grep '. $grep_str .'|grep -v grep|wc -l';
			exec( $cmd, $out );
			$exec_num = isset( $out[ 0 ] ) ? $out[ 0 ] : 0;
			return $exec_num;
		break;
		case 1:
			$cmd = 'ps -efww | grep '. $grep_str .'|grep -v grep|awk \'{ print $2 }\'|xargs --no-run-if-empty kill -9';
			exec( $cmd );
		break;
	}
}

/**
 * 主进程等待子进程全部退出
 */
function fpm_main_wait_child_quit()
{
	$retry = 0;
	while ( fpm_run_shell() > 0 && $retry < 100 )
	{
		++$retry;
		usleep( 100000 );
	}
	//还有子进程运行，强退
	if ( fpm_run_shell() > 0 )
	{
		echo "Kill child -9";
		fpm_run_shell( 1 );
		sleep( 1 );
	}
}

/**
 * 子进程加入主进程
 * @param array $req_data 请求数据
 */
function c_fpm_join()
{
	fpm_join_mod( $_POST );
}

/**
 * 空闲报告
 * @param array $req_data 请求数据
 */
function c_fpm_idle_report()
{
	fpm_idle_report_mod( $_POST );
}

/**
 * ping返回
 * @param array $req_data 请求数据
 */
function c_fpm_ping_re()
{
	fpm_ping_re_mod( $_POST );
}

/**
 * 主进程ping子进程
 */
function c_fpm_ping()
{
	fpm_check_log_file();
	$pack_id = $GLOBALS[ 'PROTOCOL_ID_LIST' ][ 'fpm_ping_re' ];
	$arr = array( 'time' => time() );
	first_send_pack( $GLOBALS[ 'FPM_MAIN_FD' ], $pack_id, $arr );
}

/**
 * 进程加入im成功
 */
function c_so_php_join_re()
{
}

/**
 * 用户在线统计
 */
function c_so_role_online()
{
}

/**
 * 用户登录log
 */
function c_so_login_log()
{

}

/**
 * 用户退出log
 */
function c_so_logout_log()
{

}