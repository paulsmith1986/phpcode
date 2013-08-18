<?php
if ( !( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_DEVELOPMENT ) )
{
	var_dump( $GLOBALS[ 'DEBUG_LEVEL' ] );
	die( 'The tool only running on the develop environment' );
}
$main_tool_file = ROOT_PATH .'main/tool/main_tool.php';
if ( is_file( $main_tool_file ) )
{
	require_once $main_tool_file;
}
/**
 * 调试主页面
 */
function c_tool_index()
{
	init_tool_menu();
	global $TOOL_MENU, $OUT_DATA;
	include ROOT_PATH . 'tool/run.php';
	die();
}

/**
 * 调试操作
 */
function c_tool_op()
{
	$GLOBALS[ 'RESPONSE_TYPE' ] = DEF_OUT_DATA_JSON;
	$type = get_var( 'type', GET_TYPE_STRING );
	$fun = 'tool_func_'. $type;
	if ( !function_exists( $fun ) )
	{
		show_excp( 'No tool function:'. $fun );
	}
	$fun();
}

/**
 * 清除memcache缓存
 */
function tool_func_clear_memcache( )
{
	first_cached::flush();
	echo "memcache 已经消除\n";
}

/**
 * 运行代码
 */
function tool_func_run_code()
{
	if( !empty( $_POST['code'] ) )
	{
		eval( $_POST[ 'code' ] );
	}
}

/**
 * 初始化工具的菜单
 */
function init_tool_menu()
{
	global $TOOL_MENU;
	$TOOL_MENU[ 'clear_memcache' ] = array(
		'text'		=> '清除Memcache',
		'title'		=> '清除Memcache缓存'
	);
}

/**
 * 执行shell脚本
 */
function tool_ssh_shell( $run_cmd )
{
	$run_result = '';
	global $TOOL_SSH;
	if ( empty( $TOOL_SSH ) )
	{
		$run_result = 'No ssh config, can not run shell';
		return $run_result;
	}

	$conn = ssh2_connect( $TOOL_SSH[ 'host' ], $TOOL_SSH[ 'port' ], array( 'hostkey' => 'ssh-rsa' ) );
	if ( !$conn )
	{
		trigger_error( 'Can not connect ssh server '. $TOOL_SSH[ 'host' ]. ':'. $TOOL_SSH[ 'port' ], E_USER_WARNING );
		return $run_result;
	}
	$pub_key = $TOOL_SSH[ 'key_path' ] .'/id_rsa.pub';
	$pri_key = $TOOL_SSH[ 'key_path' ] .'/id_rsa';
	$pwd = $TOOL_SSH[ 'passphrase' ];
	$user = $TOOL_SSH[ 'user' ];
	if ( !ssh2_auth_pubkey_file( $conn, $user, $pub_key, $pri_key, $pwd ) )
	{
		trigger_error( 'SSH public key authentication failed!' , E_USER_WARNING );
		return $run_result;
	}
	$stdout_stream = ssh2_exec( $conn, $run_cmd );
	$stderr_stream=  ssh2_fetch_stream( $stdout_stream, SSH2_STREAM_STDERR );
	stream_set_blocking( $stderr_stream, true );
	stream_set_blocking( $stdout_stream, true );
	$run_result = stream_get_contents( $stdout_stream );
	$stderr_result = stream_get_contents( $stderr_stream );
	if ( !empty( $stderr_result ) )
	{
		$GLOBALS[ 'DEBUG_ERR' ][] = $stderr_result;
	}
	fclose( $stdout_stream );
	fclose( $stderr_stream );
	return $run_result;
}

/**
 * 初始化svn
 * @param string $svn_name 配置名称
 * @param string $do_type 操作方式
 * @param bool $del_exist 如果已经存在.是否删除
 */
function tool_svn_shell( $svn_name, $do_type = 'co', $del_exist = false )
{
	global $BUILD_SVN;
	if ( !isset( $BUILD_SVN[ $svn_name ] ) )
	{
		trigger_error( 'No svn config: '. $svn_name .' in $BUILD_SVN' );
		return;
	}
	$svn_set = $BUILD_SVN[ $svn_name ];
	$sh_file = $svn_set[ 'shpath' ] .'svn_'. $do_type .'.sh';
	if ( !is_file( $sh_file ) )
	{
		trigger_error( 'file: '. $sh_file .' is not exist!' );
		return;
	}
	if ( !is_executable( $sh_file ) )
	{
		trigger_error( 'file: '. $sh_file .' is not executable!' );
		return;
	}
	$build_path = tool_svn_root_path( $svn_name );
	if ( 'co' === $do_type && is_dir( $build_path ) )
	{
		if ( $del_exist )
		{
			tool_ssh_shell( 'rm -rf '. $build_path );
		}
		else
		{
			trigger_error( $build_path .' is exist!' );
			return;
		}
	}
	//提交的时候.目录不存在.自动check out
	if ( 'commit' === $do_type )
	{
		if ( !is_dir( $build_path ) )
		{
			tool_svn_shell( $svn_name );
		}
		$up_re = tool_svn_shell( $svn_name, 'up' );
		echo $up_re;
		$svn_change = tool_ssh_shell( 'svn st '. $build_path );
		//没有改变
		if ( empty( $svn_change ) )
		{
			echo "\n没有改变，无需提交\n";
			return;
		}
		$change_list = explode( "\n", $svn_change );
		foreach ( $change_list as $each_change )
		{
			if ( empty( $each_change ) )
			{
				continue;
			}
			$commit_type = $each_change{0};
			if ( 'M' == $commit_type )
			{
				continue;
			}
			$commit_file = trim( substr( $each_change, 1 ) );
			if ( '!' == $commit_type )
			{
				tool_ssh_shell( 'svn del '. $commit_file .' --force' );
			}
			elseif ( '?' == $commit_type )
			{
				tool_ssh_shell( 'svn add '. $commit_file );
			}
		}
	}
	$cmd = $sh_file;
	if ( 'co' == $do_type )
	{
		$cmd .= ' '. $svn_set[ 'url' ];
	}
	$cmd .= ' '. $build_path .' '. $svn_set[ 'user' ] .' '. $svn_set[ 'passwd' ];
	$re = tool_ssh_shell( $cmd );
	if ( 'commit' !== $do_type )
	{
		tool_ssh_shell( 'chmod 777 '. $build_path .' -R' );
	}
	echo $re;
}

/**
 * 获取 svn 的父级目录
 * @param string $svn_name 目录后缀
 * @param bool $check_permission 权限检查
 */
function tool_svn_root_path( $svn_name, $check_permission = true )
{
	$build_path = dirname( ROOT_PATH );
	if ( $check_permission && !is_writable( $build_path ) )
	{
		show_excp( $build_path .' is not writeable!' );
	}
	if ( is_file( $build_path ) )
	{
		show_excp( $build_path .' is a regular file!' );
	}
	$build_path .= '/'. $svn_name;
	return $build_path;
}

/**
 * 检测是否发生了某种类型的错误
 * @param string $key_word 关键字
 */
function tool_error_search( $key_word )
{
	global $DEBUG_ERR;
	if ( empty( $DEBUG_ERR ) )
	{
		return false;
	}
	if ( empty( $key_word ) )
	{
		return true;
	}
	$err_msg = join( '', $DEBUG_ERR );
	return false !== strpos( $err_msg, $key_word );
}