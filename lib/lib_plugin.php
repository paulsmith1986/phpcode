<?php
/**
 * 游戏插件核心代码
 */
/**
 * 初使化所有的插件的事件监听
 */
function plugin_init_event_listener( )
{
	$GLOBALS[ 'PLUGIN_EVENT' ] = array(
		'gold_pay'				=> true,			//黄金消耗
		'gold_recharge'			=> true,			//充值
		'reg'					=> true,			//注册
		'login'					=> true,			//登录
		'logout'				=> true,			//退出
	);
	$path = ROOT_PATH .'plugins';
	//$static_db = get_static_db();
	//$plugin_rs = $static_db->get_all( 'select id, plugin_name from plugin', 'id' );
	$plugin_rs = array();		//todo
	$main_db = get_db();
	//过滤掉已经关闭的插件
	$plugin_set = $main_db->get_col( 'select id from plugin_set where is_open=0' );
	if ( !empty( $plugin_set ) )
	{
		foreach ( $plugin_set as $plugin_id )
		{
			unset( $plugin_rs[ $plugin_id ] );
		}
	}
	$GLOBALS[ 'PLUGIN_CALLBACK' ] = array();
	foreach ( $plugin_rs as $rs )
	{
		$plugin_name = $rs[ 'plugin_name' ];
		$plugin_file = $path .'/'. $plugin_name .'.plugin.php';
		if ( !is_file( $plugin_file ) )
		{
			trigger_error( '找不到插件文件:'. $plugin_file, E_USER_WARNING );
			continue;
		}
		require $plugin_file;
		$init_plugin_func = 'plugin_'. $plugin_name .'_init';
		if ( !function_exists( $init_plugin_func ) )
		{
			trigger_error( '插件 '. $plugin_name .' 必须存在初使化函数:'. $init_plugin_func, E_USER_WARNING );
			continue;
		}
		$GLOBALS[ 'current_plugin_name' ] = $plugin_name;
		$init_plugin_func();
	}
	//将每个插件触发点写入APC
	foreach ( $GLOBALS[ 'PLUGIN_CALLBACK' ] as $event => $callback_func )
	{
		$cachekey = 'plugin_event_'. $event;
		apc_store( $cachekey, $callback_func );
	}
	$plugin_version = (int)get_config( 'plugin_version' );
	apc_store( 'plugin_version', $plugin_version );
}

/**
 * 设置一个回调函数
 * @param string $event 触发器
 * @param string $fun_name 函数名
 */
function plugin_add_event_listener( $event, $fun_name )
{
	if ( !isset( $GLOBALS[ 'PLUGIN_EVENT' ][ $event ] ) )
	{
		trigger_error( '不存在插件事件 '. $event, E_USER_WARNING );
		return;
	}
	if ( !function_exists( $fun_name ) )
	{
		trigger_error( '不存在回调函数 '. $fun_name, E_USER_WARNING );
		return;
	}
	//这里的plugin_name主要为了加载插件运行时需要的文件
	$plugin_name = $GLOBALS[ 'current_plugin_name' ];
	if ( !isset( $GLOBALS[ 'PLUGIN_CALLBACK' ][ $event ][ $plugin_name ] ) )
	{
		$GLOBALS[ 'PLUGIN_CALLBACK' ][ $event ][ $plugin_name ] = $fun_name;
	}
	else
	{
		$GLOBALS[ 'PLUGIN_CALLBACK' ][ $event ][] = $fun_name;
	}
}

/**
 * 抛出一个事件
 * @param string $event 事件名称
 * @param mixed $args 参数
 */
function plugin_dispatch_event( $event, $args )
{
	$apc_plugin_version = get_apc_cache( 'plugin_version' );
	//如果apc里未存plugin_version或者和game_conf表存的值不一样，重新初始化
	if ( false === $apc_plugin_version || $apc_plugin_version != (int)get_config( 'plugin_version' ) )
	{
		plugin_init_event_listener();
	}
	$cachekey = 'plugin_event_'. $event;
	$callbacks = get_apc_cache( $cachekey );
	if ( false === $cachekey )
	{
		return;
	}
	$is_file_load = false;
	//依次执行监听事件
	foreach ( $callbacks as $plugin_name => $fun_name )
	{
		//加载相应的文件
		if ( is_string( $plugin_name) )
		{
			$is_file_load = false;
			$file = ROOT_PATH .'plugins/'. $plugin_name .'.plugin.php';
			if ( is_file( $file ) )
			{
				require_once $file;
				$is_file_load = true;
			}
			else
			{
				trigger_error( '不存在插件文件 '. $file, E_USER_WARNING );
			}
		}
		if ( $is_file_load )
		{
			$fun_name( $args );
		}
	}
}