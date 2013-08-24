<?php
/**
 * 获取游戏主数据库连接
 */
function get_db()
{
	global $DB_MAINDB_OBJECT;
	if ( null === $DB_MAINDB_OBJECT )
	{
		$DB_MAINDB_OBJECT = new first_db( $GLOBALS[ 'GAME_DB' ] );
		if ( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_PRINT_SQL )
		{
			$DB_MAINDB_OBJECT->is_debug = 1;
		}
	}
	return $DB_MAINDB_OBJECT;
}

/**
 * 用于数据库验证
 * @param int $old_rand 旧验证数字
 */
function game_rand_key( $old_rand )
{
	if ( ++$old_rand > 65535 )
	{
		$old_rand = 0;
	}
	return $old_rand;
}

/**
 * 将数据写入数据库，以及一些相应的操作
 */
function game_commit()
{
	global $DB_MAINDB_OBJECT, $OUT_MEMCACHE_ARR;
	//首先将数据写入数据库
	if ( null != $DB_MAINDB_OBJECT )
	{
		$DB_MAINDB_OBJECT->commit();
	}
	//memcache缓存写入
	if ( !empty( $OUT_MEMCACHE_ARR ) )
	{
		global $MEMCACHE_CAS_ARR;
		foreach ( $OUT_MEMCACHE_ARR as $key => $item )
		{
			if ( isset( $MEMCACHE_CAS_ARR[ $key ] ) )
			{
				$re = first_cached::cas( $MEMCACHE_CAS_ARR[ $key ], $key, $item[ 0 ], $item[ 1 ] );
			}
			else
			{
				$re = first_cached::set( $key, $item[ 0 ], $item[ 1 ] );
			}
			if ( false === $re )
			{
				remove_cache( $key );
			}
		}
		$OUT_MEMCACHE_ARR = array();
	}
	//释放互斥锁
	free_mutex_lock();
}

/**
 * 输入协议数据
 * @param string $protocol_name 协议名称
 * @param array $data 数据
 * @param mix $data_aim 目标类型 默认给当前用户, 发给指定用户 传 用户ID, 发频道传E_1023字符串 发多个人 用户id,用户id2 -1:全世界
 */
function out_protocol( $protocol_name, $data, $data_aim )
{
	global $OUT_IM_PROTOCOLS;
	$tmp_data = array( $protocol_name, $data );
	$aim_type = PROTOCOL_TO_ROLE;
	if ( is_numeric( $data_aim ) )  //全是数字 单个玩家 或者 全世界
	{
		if ( -1 == $data_aim )
		{
			$aim_type = PROTOCOL_TO_WORLD;
			$data_aim = 'im_world'; //全服
		}
	}
	else
	{
		if ( false != strpos( $data_aim, '_' ) )  //带 "_" 表示发给频道
		{
			$aim_type = PROTOCOL_TO_CHANNEL;
		}
		elseif ( false != strpos( $data_aim, ',' ) ) //带 "," 表示发给多个玩家
		{
			$aim_type = PROTOCOL_TO_ROLE_LIST;
		}
	}
	if ( !isset( $OUT_IM_PROTOCOLS[ $aim_type ][ $data_aim ] ) )
	{
		$OUT_IM_PROTOCOLS[ $aim_type ][ $data_aim ] = array( );
	}
	$OUT_IM_PROTOCOLS[ $aim_type ][ $data_aim ][] = $tmp_data;
}

/**
 * 整理要发送的数据
 */
function protocol_encode( $out_protocol )
{
	global $PROTOCOL_ID_LIST;
	$out_data = array( );
	foreach ( $out_protocol as $tmp_data )
	{
		$proto_name = $tmp_data[ 0 ];
		if ( !isset( $PROTOCOL_ID_LIST[ $proto_name ] ) )
		{
			show_excp( '不存在协议:' . $proto_name );
		}
		$out_data[ ] = $PROTOCOL_ID_LIST[ $proto_name ];
		$func = 'prot_' . $proto_name . '_out';
		$out_data[] = $func( $tmp_data[ 1 ] );
	}
	return $out_data;
}

/**
 * 将IM要发送的数据发出
 */
function send_im_data()
{
	global $OUT_IM_PROTOCOLS, $IM_SERVER_PING;
	if ( empty( $OUT_IM_PROTOCOLS ) )
	{
		return;
	}
	if ( $IM_SERVER_PING < 0 )
	{
		fpm_connect_im( FIRST_FPM_SUB, $GLOBALS[ 'SERVER_TYPE' ] == SERVER_TYPE_SOCKET );
	}
	foreach ( $OUT_IM_PROTOCOLS as $data_type => $data_arr )
	{
		switch ( $data_type )
		{
			case PROTOCOL_TO_ADMIN: //发给im进程的协议
				send_data_to_im( $IM_SERVER_PING, $data_arr );
			break;
			case PROTOCOL_TO_ROLE:   //IM发指定用户数据
				send_data_to_role( $IM_SERVER_PING, $data_arr );
			break;
			case PROTOCOL_TO_CHANNEL:   //IM发频道数据
				send_data_to_channel( $IM_SERVER_PING, $data_arr );
			break;
			case PROTOCOL_TO_WORLD:   //IM发全服数据
				send_data_to_world( $IM_SERVER_PING, $data_arr );
			break;
			case PROTOCOL_TO_ROLE_LIST:   //IM发指定一批用户的数据
				send_data_to_role_list( $IM_SERVER_PING, $data_arr );
			break;
		}
	}
	$OUT_IM_PROTOCOLS = array();
}

/**
 * 发给单个用户的数据
 * @param int $sock_fd 连接fd
 * @param array $data_arr 待发送的数据
 */
function send_data_to_role( $socket_fd, $data_arr )
{
	$pack_id = $GLOBALS[ 'PROTOCOL_ID_LIST' ][ 'so_push_role_data' ];
	foreach ( $data_arr as $call_aim => $aim_data )
	{
		$aim_data = pack_send_data( $aim_data );
		$send_arr = array(
			'role_id' => $call_aim,
			'data' => $aim_data,
		);
		first_send_pack( $socket_fd, $pack_id, $send_arr );
	}
}

/**
 * 发送频道数据
 * @param int $sock_fd 连接fd
 * @param array $data_arr 待发送的数据
 */
function send_data_to_channel( $socket_fd, $data_arr )
{
	$pack_id = $GLOBALS[ 'PROTOCOL_ID_LIST' ][ 'so_push_channel_data' ];
	foreach ( $data_arr as $call_aim => $aim_data )
	{
		$aim_data = pack_send_data( $aim_data );
		$chan_arr = im_channel_to_array( $call_aim );
		$send_arr = array(
			'channel' => $chan_arr,
			'data' => $aim_data,
		);
		first_send_pack( $socket_fd, $pack_id, $send_arr );
	}
}

/**
 * 发给全服的数据
 * @param int $sock_fd 连接fd
 * @param array $data_arr 待发送的数据
 */
function send_data_to_world( $socket_fd, $data_arr )
{
	$pack_id = $GLOBALS[ 'PROTOCOL_ID_LIST' ][ 'so_push_world_data' ];
	foreach ( $data_arr as $call_aim => $aim_data )
	{
		$aim_data = pack_send_data( $aim_data );
		$send_arr = array(
			'data' => $aim_data,
		);
		first_send_pack( $socket_fd, $pack_id, $send_arr );
	}
}

/**
 * 发给多个用户的数据
 * @param int $sock_fd 连接fd
 * @param array $data_arr 待发送的数据
 */
function send_data_to_role_list( $socket_fd, $data_arr )
{
	$pack_id = $GLOBALS[ 'PROTOCOL_ID_LIST' ][ 'so_push_role_list_data' ];
	foreach ( $data_arr as $call_aim => $aim_data )
	{
		$role_list = explode( ',', $call_aim );
		$aim_data = pack_send_data( $aim_data );
		$send_arr = array(
			'data' => $aim_data,
			'role_list' => $role_list,
		);
		first_send_pack( $socket_fd, $pack_id, $send_arr );
	}
}

/**
 * 发给im进程的协议
 * @param int $sock_fd 连接fd
 * @param array $data_arr 待发送的数据
 */
function send_data_to_im( $socket_fd, $data_arr )
{
	global $PROTOCOL_ID_LIST;
	foreach ( $data_arr as $send_pack )
	{
		$pack_name = $send_pack[ 0 ];
		if ( !isset( $PROTOCOL_ID_LIST[ $pack_name ] ) )
		{
			trigger_error( 'Unkown pack name:'. $pack_name, E_USER_WARNING );
			continue;
		}
		first_send_pack( $socket_fd, $PROTOCOL_ID_LIST[ $pack_name ], $send_pack[ 1 ] );
	}
}

/**
 * 生成发送数据包
 * @param array $pack_data 打包的数据
 */
function pack_send_data( $pack_data )
{
	global $PROTOCOL_ID_LIST;
	$send_arr = array();
	foreach ( $pack_data as $tmp_data )
	{
		$pack_name = $tmp_data[ 0 ];
		if ( !isset( $PROTOCOL_ID_LIST[ $pack_name ] ) )
		{
			trigger_error( 'Unkown protocol name:'. $pack_name );
			continue;
		}
		$func = 'proto_pack_'. $pack_name;
		$tmp_pack = $func( $tmp_data[ 1 ] );
		$send_arr[] = $tmp_pack;
	}
	$encode_str = join( '', $send_arr );
	$len = strlen( $encode_str );
	$pack_id = 1;
	//数据量太大,压缩一下
	/*if ( $len > 5000 )
	{
		$encode_str = gzcompress( $encode_str );
		$len = strlen( $encode_str );
		$pack_id = 2;
	}*/
	$head_str = pack( "LS", $len, $pack_id );
	return $head_str . $encode_str;
}

/**
 * 数据冲突出错
 * @param int $err_code 自定义错误编号
 */
function data_error( $err_code = 10 )
{
	show_error( 'Data error!', $err_code );
}

/**
 * 解密cookie字符串
 * @param string $cookie_str cookie串
 */
function cookie_unpack( $cookie_str )
{
	$re_cookie = false;
	$tmp_pack = msgpack_unpack( base64_decode( $cookie_str ) );
	if ( isset( $tmp_pack[ 'str' ], $tmp_pack[ 'hash' ] ) )
	{
		$hash_str = md5( $tmp_pack[ 'str' ] . $GLOBALS[ 'GAME_INI' ][ 'cookie_key' ] );
		if ( $hash_str == $tmp_pack[ 'hash' ] )
		{
			$re_cookie = msgpack_unpack( $tmp_pack[ 'str' ] );
			/*1小时不心跳过期
			if ( time() - $re_cookie[ 'ping' ] > 3600 )
			{
				$re_cookie = false;
			}*/
		}
	}
	return $re_cookie;
}

/**
 * 初始化游戏 （开发阶段兼容http请求使用）
 */
function http_request()
{
	//如果不是开发模式，不允许http方式请求
	if ( !( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_DEVELOPMENT ) )
	{
		die( 'Request Error!' );
	}
	$GLOBALS[ '_GLOBAL_KEY_' ] = null;
	$GLOBALS[ 'TASK_FILE' ] = 'index';
	//客户端判断
	if( isset( $_SERVER[ 'HTTP_CONTENT_TYPE' ] ) && 'application/x-amf' == $_SERVER[ 'HTTP_CONTENT_TYPE' ] )
	{
		$GLOBALS[ 'RESPONSE_TYPE' ] = DEF_OUT_DATA_AMF;
		$tmp_param = (array) amf_decode( file_get_contents( 'php://input' ), 1 );
		if ( !isset( $tmp_param[ 'QUERY_PARAMS' ] ) )
		{
			show_excp( 'HTTP swf request params error' );
		}
		$_POST = proto_unpack_data( base64_decode( $tmp_param[ 'QUERY_PARAMS' ] ) );
	}
	elseif ( isset( $_GET[ 'QUERY_PARAMS' ] ) )
	{
		$_POST = proto_unpack_data( base64_decode( $_GET[ 'QUERY_PARAMS' ] ) );
	}
	init_php_total();
	if ( isset( $_GET[ 'test_request' ] ) )
	{
		$GLOBALS[ 'RESPONSE_TYPE' ] = DEF_OUT_DATA_JSON;
	}
	if ( empty( $_COOKIE[ 'auth_str' ] ) )
	{
		$_COOKIE[ 'auth_str' ] = get_var( 'auth_str', GET_TYPE_STRING, false );
	}
	$GLOBALS[ 'SERVER_TYPE' ] = SERVER_TYPE_HTTP;
	global $ROLE_COOKIE, $PROTO_ID_NAME;
	ob_start();
	$pack_id = get_var( 'pack_id', GET_TYPE_INT, false );
	$pack_name = 'main_index';
	if ( null == $pack_id )
	{
		$ctl_name = isset ( $_GET[ 'c' ] ) ? $_GET[ 'c' ] : 'tool';
		$act_name = isset ( $_GET[ 'a' ] ) ? $_GET[ 'a' ] : 'index';
		$pack_name = $ctl_name .'_'. $act_name;
		foreach ( $PROTO_ID_NAME as $tmp_pack_id => $tmp_name )
		{
			if ( $pack_name === $tmp_name )
			{
				$pack_id = $tmp_pack_id;
				break;
			}
		}
	}
	//如果指定了pack_id一定要确定协议存在
	elseif ( !isset( $PROTO_ID_NAME[ $pack_id ] ) )
	{
		show_excp( 'Unkown pack_id:'. $pack_id );
	}
	if ( isset( $PROTO_ID_NAME[ $pack_id ] ) )
	{
		$pack_name = $PROTO_ID_NAME[ $pack_id ];
		$GLOBALS[ 'ROLE_COOKIE' ][ 'pack_id' ] = $pack_id;
	}
	if ( 'tool_login' != $pack_name )
	{
		$login_flag = false;
		if ( !empty( $_COOKIE[ 'auth_str' ] ) )
		{
			$re = cookie_unpack( $_COOKIE[ 'auth_str' ] );
			if ( !empty( $re ) )
			{
				$login_flag = true;
				try
				{
					$account = api_role_account( $re[ 'username' ] );
					$GLOBALS[ 'ROLE_COOKIE' ][ 'role_id' ] = $account[ 'role_id' ];
				}
				catch ( Exception $excp_obj )
				{}
			}
		}
		if ( !$login_flag )
		{
			header( 'Location: login.php' );
			die();
		}
	}
	try
	{
		action_dispatch( $pack_name );
		game_commit();
	}
	catch( Exception $excep_obj )
	{
		do_with_excp( $excep_obj );
	}
	do_end();
}

/**
 * 获取cookie值，此时的cookie是加密存
 * @param string $key cookie键
 */
function get_cookie( $key )
{
	$re = false;
	if ( isset( $GLOBALS[ 'ROLE_COOKIE' ][ $key ] ) )
	{
		$re = $GLOBALS[ 'ROLE_COOKIE' ][ $key ];
	}
	return $re;
}

/**
 * 获取规则对应的表或分表
 * @param int $db_table 表名
 * @param int $the_hash 用于分表的数字
 * @return string table_name
 */
function get_table( $db_table, $the_hash )
{
	if ( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_DEVELOPMENT )
	{
		return $db_table . '_0';
	}
	else
	{
		return $db_table . '_' . ( $the_hash % 16 );
	}
}

/**
 * 直接获取当前登录的用户ID
 * @return int
 */
function get_role_id()
{
	return get_cookie( 'role_id' );
}

/**
 * PHP版的confirm函数
 * @param int $lang_id 语言包id
 * @param array $lang_arg 语言包中的变量
 */
function confirm( $lang_id, $lang_arg = array() )
{
	//前端才生效
	if ( $GLOBALS[ 'SERVER_TYPE' ] <= SERVER_TYPE_SOCKET )
	{
		return;
	}
	$php_confirm_arr = array( $lang_id );
	$check_str = get_var( 'confirm_key', GET_TYPE_STRING, false );
	$check_list = array();
	if ( null !== $check_str )
	{
		$check_list = explode( ',', $check_str );
	}
	if ( !in_array( $lang_id, $check_list ) )
	{
		$check_list[] = $lang_id;
		set_var( 'confirm_key', join( ',', $check_list ) );
		//重试本次请求
		action_retry( $lang_id, $lang_arg );
	}
}

/**
 * 获取某一个配置
 * @param string $key 配置名称
 */
function get_config( $key )
{
	$cachekey = 'game_conf_' . $key;
	if ( !has_cache( $cachekey, $re ) )
	{
		$db = get_db();
		$re = $db->get_one( 'select set_value from game_conf where set_key="' . $key . '"' );
		if ( null === $re )
		{
			show_excp( '不存在游戏配置' . $key );
		}
		update_cache( $cachekey, $re, 86400 );
	}
	return $re;
}

/**
 * 设置配置
 * @param string $key 名称
 * @param mixed $value 值
 */
function set_config( $key, $value )
{
	$cachekey = 'game_conf_' . $key;
	$db = get_db();
	$db->update( 'game_conf', array( 'set_value' => $value ), 'where key="' . $key . '"' );
	update_cache( $cachekey, $value, 86400 );
}

/**
 * 和蟹字符检测
 * @param string $input_str 待检测字符
 */
function php_check_hexie ( $input_str )
{
	$cachekey = 'hexie_key';
	$hexie_key = get_apc_cache( $cachekey );
	if ( false === $hexie_key )
	{
		$hexie_key = hexie_init( $cachekey );
	}
	$filt_data = get_apc_cache( 'hexie_filte_data' );
	$clean_str = filt_string( $input_str, $filt_data );
	$checkpos = 0;
	$find_bad = false;
	while ( false !== ( $tmp_word = char_from_string( $clean_str, $checkpos, true ) ) )
	{
		if ( !isset( $hexie_key[ $tmp_word ] ) )
		{
			continue;
		}
		$bad_list = get_apc_cache( 'hexie_item_'. $tmp_word );
		if ( false === $bad_list )
		{
			continue;
		}
		//单字
		if ( true === $bad_list )
		{
			$bad_char = $tmp_word;
			$find_bad = true;
		}
		else
		{
			foreach ( $bad_list as $each_bad )
			{
				if ( hexie_cmp( $clean_str, $checkpos, $each_bad ) )
				{
					$bad_char = $tmp_word . $each_bad;
					$find_bad = true;
					break;
				}
			}
		}
		if ( $find_bad )
		{
			break;
		}
	}
	if ( $find_bad )
	{
		show_error( '您输入的内容含有系统屏蔽字符【' . $bad_char . '】', 3, array( $bad_char ) );
	}
}

/**
 * html格式返回结果
 * @param mixed $data 需要打印的数据
 */
function html_print_r( $data )
{
	$result = print_r( $data, true );
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
	'<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>tool</title>',
	'</head><body><div><pre>', $result,
	'</pre></div></body></html>';
}