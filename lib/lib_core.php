<?php
/**
 * 获取传入参数的方法
 * @param string $var_name 参数名称
 * @param string $var_type 参数类型
 * @param bool $is_musg 是否是必须要的参数
 * @return mixed 参数值
 */
function get_var ( $var_name, $var_type = GET_TYPE_INT, $var_must = true )
{
	$value = null;
	if ( isset( $_POST[ $var_name ] ) )
	{
		$value = $_POST[ $var_name ];
	}
	elseif ( isset( $_GET[ $var_name ] ) )
	{
		$value = $_GET[ $var_name ];
	}

	if ( null === $value )
	{
		if ( $var_must )
		{
			throw new Exception( 'NO ARG:'. $var_name, 10000 );
		}
		else
		{
			return $value;	//可以不传递的参数，如果不传就为null;
		}
	}
	switch ( $var_type )
	{
		case GET_TYPE_STRING:		//字符统一addslashes处理
			$re = addslashes( $value );
		break;
		case GET_TYPE_INT:			//数字全部int处理，并且不能传递小于0的数字
			$re = (int)$value;
			if ( $re < 0 )
			{
				$re = 0;
			}
		break;
		case GET_TYPE_NAME:		//用于名字
			$a = true;
			$bad_char = array( '%' => $a, ':' => $a, '\\' => $a, '|' => $a, '"' => $a, "\r" => $a, "\n" => $a, "'" => $a );
			$re_str = '';
			$re = trim( $value );
			//首先检查有没有绝对不能出现的字符
			for ( $i = 0; $i < strlen( $re ); ++$i )
			{
				if ( isset( $bad_char[ $re{$i} ] ) )
				{
					show_error( '名字输入里不能带' . $re{$i} . '字符', 2, $re{$i} );
				}
			}
			php_check_hexie( $re );
		break;
		case GET_TYPE_HEXIE:		//用于玩家输入，如：信函等
			$re = addslashes( $value );
			php_check_hexie( $re );
		break;
		case GET_TYPE_ARRAY:		//直接传入数组
			$re = $value;
			if ( !is_array( $re ) )
			{
				$re = array();
			}
		break;
	}
	return $re;
}

/**
 * 手动设置请求变量
 * @param string $var_name		参数名称
 * @param int $var_data 参数值
 * @return void
 */
function set_var ( $var_name, $var_data )
{
	$_POST[ $var_name ] = $var_data;
}

/**
 * 抛出系统运行异常
 * @param string $excp_msg 异常消息
 * @param int $error_no 异常编号 ( 默认编号11000 )
 * @param mix $arg_data 参数
 */
function show_excp ( $excp_msg )
{
	show_error( $excp_msg, 11000 );
}

/**
 * 抛出错误信息到前台
 * @param string $excp_msg 错误消息
 * @param int $error_no 异常编号
 * @param mix $arg 参数
 */
function show_error ( $error_msg, $error_no = 100, $arg_data = null )
{
	global $EXCP_ARG;
	if ( null !== $arg_data )
	{
		$EXCP_ARG = $arg_data;
	}
	throw new Exception( $error_msg, $error_no );
}

/**
 * 处理结果时的收尾
 * @param bool $fatal_error 是否是出错
 * @return void
 */
function do_end ( $fatal_error = false )
{
	global $SERVER_TYPE;
	switch ( $SERVER_TYPE )
	{
		case SERVER_TYPE_HTTP:				//HTTP请求(开发使用)
			record_game_log();
			ob_end_clean();
			switch ( $GLOBALS[ 'RESPONSE_TYPE' ] )
			{
				case DEF_OUT_DATA_AMF:		//Flash模式
					out_debug_data();
				break;
				case DEF_OUT_DATA_JSON:		//Json
					echo json_encode( $GLOBALS[ 'OUT_DATA' ] );
				break;
				default:	//print_r
					html_print_r( $GLOBALS[ 'OUT_DATA' ] );
				break;
			}
			send_im_data();
			clear_global_var();
			if ( -1 !== $GLOBALS[ 'IM_SERVER_PING' ] )
			{
				first_close_fd( $GLOBALS[ 'IM_SERVER_PING' ] );
			}
		break;
		case SERVER_TYPE_SOCKET:			//后端
			record_game_log();
			if ( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_DEVELOPMENT )
			{
				out_debug_data();
			}
			send_im_data();
			if ( !$fatal_error )
			{
				ob_end_clean();
			}
			clear_global_var();
			clear_game_var();
		break;
		case SERVER_TYPE_FPM_MAIN:			//主进程
			record_game_log();
			if ( !$fatal_error )
			{
				ob_end_clean();
			}
		break;
	}
	clear_server_var();
}

/**
* 路由到相应的逻辑(仅HTTP请求时)
 * @param string $pack_name 逻辑名
 * @return void
*/
function action_dispatch ( $pack_name )
{
	$fun_name = 'c_'. $pack_name;
	if ( !function_exists( $fun_name ) )
	{
		show_excp( '请加载 '. $pack_name .' 所在的control' );
	}
	$fun_name();
}
/**
 * 重试本次请求
 * @param int $lang_id 语言包id
 * @param int $lang_arg 语言包中的变量
 */
function action_retry( $lang_id, $lang_arg = array() )
{
	unset( $_POST[ 'pack_id' ] );
	$exp_args = array(
		'lang_id'		=> $lang_id,
		'lang_arg'		=> $lang_arg,
		'pack_id'		=> get_cookie( 'pack_id' ),
		'params'		=> $_POST,
	);
	show_error( '', 500, $exp_args );
}