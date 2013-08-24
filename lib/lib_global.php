<?php
ini_set( 'display_errors', 1 );
$ROOT_PATH = ROOT_PATH;
require_once $ROOT_PATH .'etc/game_conf.php';
require_once $ROOT_PATH .'lib/lib_core.php';
//加载配置文件
load_game_config();
//加载协议
load_proto_map();
//设置时区
date_default_timezone_set( $GAME_INI[ 'time_zone' ] );

//PHP自定义错误处理函数
set_error_handler( 'error_handle' );

//PHP异常退出处理函数
register_shutdown_function( 'shutdown_function' );

//常用的全局变量
$OUT_IM_PROTOCOLS	= array();				//im_send池
$DB_MAINDB_OBJECT	= null;					//全局数据库对象变量
$YAC_CACHE_OBJECT	= null;					//yac操作类
$OUT_MEMCACHE_ARR	= array();				//更新cache的时候，先存放在这里
$OUT_DATA			= array();				//返回客户端的数据
$APCCACHE			= array();				//apc的cache
$MEMCACHE			= array();				//memcache的cache
$MEMCACHE_CAS_ARR	= array();				//缓存校验码
$OUTLOG_POOL_DATA	= array();				//要记录的log
$GAMELOCK			= array();				//游戏中添加的锁
$EXCP_ARG			= null;					//捕捉异常时需要用到的参数
$ROLE_COOKIE		= array();				//玩家COOKIE
$ERR_CODE			= 0;					//错误状态码
$IM_SERVER_PING		= -1;					//与im服务器连接fd
$DEBUG_ERR			= array();				//出错信息
$DEBUG_STR			= array();				//调试信息
$LOG_FILE			= array();				//日志的文件句柄

/**
 * 加载配置文件
 */
function load_game_config()
{
	global $SERVER_HOST;
	//默认是进程类型
	$GLOBALS[ 'SERVER_TYPE' ] = SERVER_TYPE_SOCKET;
	//请求类型
	$GLOBALS[ 'REQUEST_TYPE' ] = DEF_WEB_REQUEST;
	//返回数据格式
	$GLOBALS[ 'RESPONSE_TYPE' ] = DEF_OUT_DATA_PRINT;
	//调试级别
	$GLOBALS[ 'DEBUG_LEVEL' ] = DEF_SAVE_ERROR_LOG;
	//加载框架主配置文件
	try_config_file( 'config', '' );
	$host_str = explode( '.', $SERVER_HOST );
	$max_part = count( $host_str ) - 1;
	//尝试前2段
	$conf_file = $host_str[ $max_part - 1 ] .'.'. $host_str[ $max_part ];
	$re = try_config_file( $conf_file );
	//尝试前3段
	if ( !$re )
	{
		$conf_file = $host_str[ $max_part - 2 ] .'.'. $conf_file;
		try_config_file( $conf_file );
	}
	//尝试完全域名自定义配置文件
	try_config_file( $SERVER_HOST );
}

/**
 * 检查指定的配置文件是否存在
 * @param string $ini_file 配置文件名称
 * @param string $ini_path 相对路径
 */
function try_config_file ( $ini_file, $ini_path = 'main' )
{
	$file_name = ROOT_PATH . $ini_path .'/etc/host/'. $ini_file .'.ini';
	if ( !is_file( $file_name ) )
	{
		return false;
	}
	$tmp_config = parse_ini_file( $file_name, true );
	foreach ( $tmp_config as $conf_key => $conf_value )
	{
		if ( is_array( $conf_value ) && isset( $GLOBALS[ $conf_key ] ) )
		{
			foreach ( $conf_value as $sub_key => $sub_value )
			{
				$GLOBALS[ $conf_key ][ $sub_key ] = $sub_value;
			}
		}
		else
		{
			$GLOBALS[ $conf_key ] = $conf_value;
		}
	}
	return true;
}

/**
 * 自定义错误处理函数
 * @param int $error_no 错误编号
 * @param string $error_str 错误描述
 * @param string $err_file 错误文件
 * @param int $err_line 错误位置行号
 * @return void
 */
function error_handle ( $error_no, $error_str, $err_file, $err_line )
{
	switch ( $error_no )
	{
		case E_WARNING:
		case E_USER_WARNING:
			$type_str = 'PHP Warning';
		break;
		case E_NOTICE:
		case E_USER_NOTICE:
			$type_str = 'PHP Notice';
		break;
		case E_ERROR:
		case E_USER_ERROR:
		case E_COMPILE_ERROR:
			$type_str = 'PHP Fatal error';
		break;
		case E_PARSE:
			$type_str = 'Parse error';
		break;
		default:
			$type_str = 'PHP Error[error_no:' . $error_no .']';
		break;
	}
	$trac_info = deal_debug_trace( debug_backtrace() );
	$save_str = $type_str .': '. $error_str .' in '. $err_file .' on line '. $err_line ."\n". $trac_info;
	if ( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_DEVELOPMENT )
	{
		$GLOBALS[ 'DEBUG_STR' ][] = $save_str;
		$GLOBALS[ 'DEBUG_ERR' ][] = $save_str;
	}
	save_game_log( $save_str, 'error' );
}

/**
 * 处理debug trace数组
 * @param array $trace debug_trace数组
 * @return array $error	出错内容数组
 */
function deal_debug_trace( $trace )
{
	$error_arr = array( );
	$index = 0;
	foreach ( $trace as $each_step )
	{
		$error_msg = '#'. $index .' ';
		if ( isset( $each_step[ 'file' ] ) )
		{
			$error_msg .= $each_step[ 'file' ];
		}
		if ( isset( $each_step[ 'line' ] ) )
		{
			$error_msg .= '(' . $each_step[ 'line' ] . ') ';
		}
		if ( isset( $each_step[ 'class' ] ) )
		{
			$error_msg .= $each_step[ 'class' ];
		}
		if ( isset( $each_step[ 'type' ] ) )
		{
			$error_msg .= $each_step[ 'type' ];
		}
		if ( isset( $each_step[ 'function' ] ) )
		{
			$error_msg .= $each_step[ 'function' ];
		}
		if ( isset( $each_step[ 'args' ] ) )
		{
			$error_msg .= '(' . deal_trace_args( $each_step[ 'args' ] ) . ')';
		}
		$error_arr[] = $error_msg;
		++$index;
	}
	return join( "\n", $error_arr ). "\n";
}

/**
 * 处理trace_args数组
 * @param array $args 处理debug trace中args信息数组
 * @return string 处理后debug信息字符串
 */
function deal_trace_args( $args )
{
	$tmpArr = array( );
	foreach ( $args as $value )
	{
		if ( is_object( $value ) )
		{
			$tmpArr[] = 'Object( ' . get_class( $value ) . ' )';
		}
		elseif ( is_array( $value ) )
		{
			$tmpArr[] = 'Array';
		}
		else
		{
			$tmpArr[] = $value;
		}
	}
	return implode( ', ', $tmpArr );
}

/**
 * 异常处理函数
 * @return void
 */
function shutdown_function ()
{
	$last_err = error_get_last();
	if ( !empty( $last_err ) )
	{
		catch_error();
		error_handle( $last_err[ 'type' ], $last_err[ 'message' ], $last_err[ 'file' ], $last_err[ 'line' ] );
		$GLOBALS[ 'ERR_CODE' ] = 100000;
		if ( E_ERROR == $last_err[ 'type' ] )
		{
			do_end( true );
		}
	}
}

/**
 * 输出运行的SQL
 * @return string SQL执行语句
 */
function dump_run_sql ()
{
	global $DB_MAINDB_OBJECT;
	$run_sqls = '';
	if ( $DB_MAINDB_OBJECT && !empty( $DB_MAINDB_OBJECT->exec_sql ) )
	{
		$run_sqls .= "\n=============[MAIN_SQL]===========\n". join( "\n\n", $DB_MAINDB_OBJECT->exec_sql ) ."\n";
	}
	return $run_sqls;
}

/**
 * 记录游戏运行日志
 * @param string $log_str 日志内容
 * @param string $pre_fix 前缀
 * @return void
 */
function save_game_log ( $log_str, $pre_fix )
{
	global $LOG_FILE;
	//未打开该文件类型
	if ( !isset( $LOG_FILE[ $pre_fix ] ) )
	{
		$file_name = make_log_file( $pre_fix );
		$f_handle = fopen( $file_name, 'a+' );
		$LOG_FILE[ $pre_fix ] = $f_handle;
	}
	fwrite( $LOG_FILE[ $pre_fix ], "\n[". date( 'H:i:s', time() ) ."]\n". $log_str );
}

/**
 * 初始化日志文件
 * @param string $pre_fix 日志文件前缀
 */
function make_log_file( $pre_fix )
{
	$file_path = $GLOBALS[ 'GAME_INI' ][ 'log_path' ] .'/'. $GLOBALS[ 'SERVER_HOST' ] .'/';
	if ( !is_dir( $file_path ) && !mkdir( $file_path, '0755', true ) )
	{
		die( 'Log path:'. $file_path ." is not exist!\n" );
	}
	//如果文件不可写
	if ( !is_writable( $file_path ) )
	{
		die( 'Log path:'. $file_path ." is unwriteable!\n" );
	}
	return $file_path . $pre_fix .'_'. date( 'Ymd', time() ) .'.log';
}

/**
 * 保存进程日志
 */
function record_game_log()
{
	global $OUT_DATA, $DEBUG_ERR;
	if ( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_PRINT_ERROR )
	{
		//输出调试数据
		if ( !empty( $GLOBALS[ 'DEBUG_STR' ] ) )
		{
			$OUT_DATA[ '_DEBUG_STR_' ] = join( "\n", $GLOBALS[ 'DEBUG_STR' ] );
		}
		if ( !empty( $DEBUG_ERR ) )
		{
			$OUT_DATA[ '_DEBUG_ERR_' ] = $DEBUG_ERR;
		}
	}
	$log_str = '';
	if ( !empty( $DEBUG_ERR ) )
	{
		$log_str = join( "\n", $DEBUG_ERR ) ."\n";
	}
	$echo_str = ob_get_contents();
	if ( !empty( $echo_str ) )
	{
		$OUT_DATA[ '_ECHO_STR_' ] = $echo_str;
		$log_str .= $echo_str;
	}
	if ( $GLOBALS[ 'ERR_CODE' ] > 0 )
	{
		$log_str .= dump_run_sql();
	}
	if ( empty( $log_str ) )
	{
		return;
	}
	save_game_log( $log_str, $GLOBALS[ 'TASK_FILE' ] );
}

/**
 * 输出调试数据
 */
function out_debug_data( $php_total = true )
{
	return;
	global $OUT_DATA, $PROTO_ID_NAME;
	$role_id = get_role_id();
	if ( $role_id <= 0 )
	{
		return;
	}
	$title = array();
	$pack_id = get_cookie( 'pack_id' );
	if ( $pack_id )
	{
		$fun_name = 'c_'. $PROTO_ID_NAME[ $pack_id ];
		$title[ 'pack_id' ] = $pack_id .'['. $fun_name .']';
	}
	$time = microtime( true );
	$title[ 'time' ] = round( ( $time - $GLOBALS[ '_START_TIME_' ] ) * 1000, 2 ) .'[ms]';
	$now_memuse = memory_get_usage();
	$title[ 'mem' ] = round( ( $now_memuse - $GLOBALS[ '_MEM_USE_' ] ) / 1024, 2 ) .'[kb]';
	$title[ 'total' ] = round( ( $now_memuse / 1024 ) / 1024, 2 ) .'[mb]';
	$title[ 'cache' ] = first_cached::$opt_count .'[次]';
	$db = get_db();
	$mysql_time = count( $db->exec_sql );
	$title[ 'mysql' ] = $mysql_time .'[次]';
	if ( isset( $GLOBALS[ '_SLOW_SQL_' ] ) )
	{
		$OUT_DATA[ '_SLOW_SQL_' ] = true;
	}
	unset( $GLOBALS[ '_SLOW_SQL_' ] );
	$now_global_key = array_keys( $GLOBALS );
	$old_global_key = $GLOBALS[ '_GLOBAL_KEY_' ];
	unset( $GLOBALS[ '_GLOBAL_KEY_' ] );
	foreach ( $now_global_key as $index => $tmp_key )
	{
		if ( isset( $old_global_key[ $tmp_key ] ) )
		{
			unset( $now_global_key[ $index ] );
		}
	}
	//有多余的global变量
	if ( !empty( $now_global_key ) )
	{
		$OUT_DATA[ '_GLOBAL_KEY_' ] = array_values( $now_global_key );
	}
	$data = base64_encode( amf_encode( $OUT_DATA, 1|2 ) );
	out_protocol( 'game_debug', array( 'title' => array_to_str( $title, ' ' ), 'data' => $data ), $role_id );
	//如果有错误发生，按用户id生成错误日志
	if ( isset( $OUT_DATA[ '_DEBUG_ERR_' ] ) )
	{
		$file_path = $GLOBALS[ 'GAME_INI' ][ 'log_path' ] . $GLOBALS[ 'SERVER_HOST' ] .'/';
		$now_time = time( );
		$file_name = date( 'Ymd', $now_time ) .'_r_'. $role_id .'.log';
		$log_str = '['. date( 'H:i:s', $now_time ) ."]\n". join( "\n", $OUT_DATA[ '_DEBUG_ERR_' ] ) ."\n";
		file_put_contents( $file_path . $file_name, $log_str, FILE_APPEND );
	}
}

/**
 * 初始化PHP需要统计的信息
 */
function init_php_total()
{
	$GLOBALS[ '_START_TIME_' ] = microtime( true );
	$GLOBALS[ '_MEM_USE_' ] = memory_get_usage();
	$GLOBALS[ '_GLOBAL_KEY_' ] = array_flip( array_keys( $GLOBALS ) );
}

/**
 * 遭遇错误
 */
function catch_error()
{
	global $DB_MAINDB_OBJECT;
	if ( $DB_MAINDB_OBJECT && $DB_MAINDB_OBJECT->use_commit )
	{
		$DB_MAINDB_OBJECT->roll_back();
	}
	if ( $GLOBALS[ 'SERVER_TYPE' ] <= SERVER_TYPE_SOCKET )
	{
		//清除数据
		clear_global_var();
	}
}

/**
 * im连接过程
 * @param int $socket_type socket类型
 * @param bool $is_poll 是否first_poll支持
 */
function fpm_connect_im( $socket_type, $is_poll = true )
{
	global $IM_SERVER_PING, $PROTOCOL_ID_LIST, $GAME_IM;
	$IM_SERVER_PING = first_socket_fd( $GAME_IM[ 'lan_host' ], $GAME_IM[ 'port' ], $is_poll );
	if ( $IM_SERVER_PING < 0 )
	{
		return false;
	}
	$pack_id = $PROTOCOL_ID_LIST[ 'so_php_join' ];
	$re = first_send_pack( $IM_SERVER_PING, $pack_id, array( 'socket_type' => $socket_type, 'join_str' => $GAME_IM[ 'super_key' ] ) );
	if ( true !== $re )
	{
		trigger_error( "Can not join im server ". $GAME_IM[ 'lan_host' ] .":". $GAME_IM[ 'port' ] ." \n", E_USER_WARNING );
		$IM_SERVER_PING = -1;
	}
	return $re;
}

/**
 * 清空PHP全局变量
 */
function clear_global_var ()
{
	//释放所有互斥锁
	free_mutex_lock();
	$GLOBALS[ 'OUT_MEMCACHE_ARR' ] = array();
	$GLOBALS[ 'OUT_DATA' ]		   = array();
	$GLOBALS[ 'OUT_IM_PROTOCOLS' ] = array();
	$GLOBALS[ 'OUTLOG_POOL_DATA' ] = array();
}

/**
 * 清理服务器端缓存
 */
function clear_server_var()
{
	$GLOBALS[ 'DEBUG_ERR' ] = array();
	$GLOBALS[ 'DEBUG_STR' ] = array();
	$GLOBALS[ 'GAMELOCK' ] = array();
	$GLOBALS[ 'MEMCACHE' ] = array();
	$GLOBALS[ 'ROLE_COOKIE' ] = array();
	$GLOBALS[ 'EXCP_ARG' ] = null;
	$GLOBALS[ 'ERR_CODE' ] = 0;
}

/**
 * 清除游戏运行中的变量
 */
function clear_game_var()
{
	first_cached::$opt_count = 0;
	unset( $GLOBALS[ 'TASK_QUEUE_TABLE_ID' ] );
	global $DB_MAINDB_OBJECT;
	if ( $DB_MAINDB_OBJECT )
	{
		$DB_MAINDB_OBJECT->clean_up();
	}
}

/**
 * 处理异常
 */
function do_with_excp( $excp_obj )
{
	catch_error();
	$error_no = $excp_obj->getCode();
	$GLOBALS[ 'ERR_CODE' ] = $error_no;
	//功能型错误
	if ( $error_no < 10000 )
	{
		global $SERVER_TYPE, $EXCP_ARG;
		if ( $SERVER_TYPE <= SERVER_TYPE_SOCKET )
		{
			$err_msg = $excp_obj->getMessage();
			$EXCP_ARG = array();
			//请求重试错误编号
			if ( 500 === $error_no )
			{
				$proto_name = 'game_retry';
				$proto_data = $EXCP_ARG;
			}
			else
			{
				$proto_name = 'game_error';
				$proto_data = array( 'code' => $error_no, 'msg' => $err_msg, 'args' => $EXCP_ARG );
			}
			$role_id = get_role_id();
			if ( $role_id > 0 )
			{
				out_protocol( $proto_name, $proto_data, $role_id );
			}
			else
			{
				$session_id = get_cookie( 'session_id' );
				if ( false !== $session_id )
				{
					$tmp_data = array( array( $proto_name, $proto_data ) );
					$proxy_data = protocol_encode( $tmp_data );
					im_admin( 'so_push_session_data', array( 'data'=> $proxy_data, 'session_id' => $session_id ) );
				}
			}
		}
	}
	else
	{
		$LOG_MSG = parse_exception( $excp_obj );
		$GLOBALS[ 'DEBUG_ERR' ][] = $LOG_MSG;
	}
}

/*
 * 提取出异常错误里的详细信息
 * @param object $excp_obj 异常对象
 * @param string $imp 换行符
 */
function parse_exception( $excp_obj, $imp = "\n" )
{
	$show_msg = array( "\n=============[CATCH_EXCEPTION]=============" );
	$show_msg[ ] = '# 错误时间 =>' . date( 'Y-m-d H:i:s' );
	$show_msg[ ] = '# 错误消息 =>[' . $excp_obj->getCode() . ']' . $excp_obj->getMessage();
	$show_msg[ ] = '# 错误位置 =>' . $excp_obj->getFile() . ':' . $excp_obj->getLine() . ':' . $excp_obj->getCode();
	$etrac = $excp_obj->getTrace();
	$total_eno = count( $etrac ) - 1;
	$eno = 0;
	foreach ( $etrac as $eno => $each_trace )
	{
		$tmp = "\n第" . ( $total_eno - $eno ) . '步 文件:' . $each_trace[ 'file' ] . ' (' . $each_trace[ 'line' ] . '行)';
		$tmp .= "\n函数名：";
		if ( isset( $each_trace[ 'class' ] ) )
		{
			$tmp .= $each_trace[ 'class' ] . '->';
		}
		$tmp .= $each_trace[ 'function' ] . '()';
		if ( isset( $each_trace[ 'args' ] ) && !empty( $each_trace[ 'args' ] ) )
		{
			$tmp_arg = array( );
			foreach ( $each_trace[ 'args' ] as $ano => $a_arg )
			{
				$atmp = "\n@参数_" . $ano . '( ' . gettype( $a_arg ) . ' ) = ';
				if ( is_numeric( $a_arg ) || is_string( $a_arg ) )
				{
					$atmp .= $a_arg;
				}
				else
				{
					$atmp .= print_r( $a_arg, true );
				}
				$tmp_arg[ ] = $atmp . '';
			}
			$tmp .= implode( $imp, $tmp_arg );
		}
		$show_msg[ ] = $tmp;
	}
	$run_sqls = dump_run_sql();
	//sql如果不为空
	if ( !empty( $run_sqls ) )
	{
		$show_msg[ ] = $run_sqls;
	}
	$show_msg[ ] = "\n=============[EXCEPTION_END]=============\n";
	return implode( $imp, $show_msg );
}

/**
 * 加载协议对应文件
 */
function load_proto_map()
{
	$core_id_list = array (
		'fpm_ping' => 26001,
		'fpm_ping_re' => 26005,
		'fpm_join' => 26002,
		'queue_wakeup' => 30001,
		'fpm_idle_report' => 26006,
		'so_push_role_data' => 20007,
		'so_push_channel_data' => 20008,
		'so_push_world_data' => 20009,
		'so_push_role_list_data' => 20010,
		'so_push_session_data' => 20006,
		'so_role_enter' => 21000,
		'role_join_channel' => 21002,
		'php_ping' => 20002,
		'so_php_join' => 20000,
	);
	$core_id_name = array (
		26001 => 'fpm_ping',
		26005 => 'fpm_ping_re',
		26002 => 'fpm_join',
		30001 => 'queue_wakeup',
		26006 => 'fpm_idle_report',
		21001 => 'game_init',
		20001 => 'so_php_join_re',
		60000 => 'so_fpm_proxy',
	);
	global $PROTOCOL_ID_LIST, $PROTO_ID_NAME;
	$file_path = ROOT_PATH .'main/build/proto_map.php';
	if ( is_file( $file_path ) )
	{
		include $file_path;
	}
	foreach ( $core_id_list as $pack_name => $pack_id )
	{
		$PROTOCOL_ID_LIST[ $pack_name ] = $pack_id;
	}
	foreach ( $core_id_name as $pack_id => $pack_name )
	{
		$PROTO_ID_NAME[ $pack_id ] = $pack_name;
	}
}