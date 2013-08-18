<?php
/**
 * 获取一个APC 缓存
 * @param string $cache_key 键值
 * @return mixed 缓存内容
 */
function get_apc_cache ( $cachekey )
{
	global $APCCACHE, $YAC_CACHE_OBJECT;
	if ( null == $YAC_CACHE_OBJECT )
	{
		$YAC_CACHE_OBJECT = new Yac();
	}
	if ( !isset( $APCCACHE[ $cachekey ] ) )
	{
		$re = $YAC_CACHE_OBJECT->get( $cachekey );
		if ( false !== $re )
		{
			$APCCACHE[ $cachekey ] = $re;
		}
	}
	else
	{
		$re = $APCCACHE[ $cachekey ];
	}
	return $re;
}

/**
 * 存储apc
 * @param string $cachekey 缓存key
 * @param mixed $apc_data 值
 */
function apc_save_cache( $cachekey, $apc_data )
{
	global $APCCACHE, $YAC_CACHE_OBJECT;
	if ( null == $YAC_CACHE_OBJECT )
	{
		$YAC_CACHE_OBJECT = new Yac();
	}
	$YAC_CACHE_OBJECT->set( $cachekey, $apc_data );
	$APCCACHE[ $cachekey ] = $apc_data;
}

/**
 * 初始化APC数据
 * @param string $xml_sql xpath查询语句
 * @param string $query_cols 查询的字段
 * @param string $cachekey 缓存键
 * @param string $get_type 获取数据的方式
 * @param int $optimize 缓存优化的方式
 * @param bool $store_empty 当遇到空值时是否保存
 * @return mixed 缓存内容
 */
function apc_xml_cache( $xml_sql, $query_cols, $cachekey, $get_type = 'get_all', $optimize = 1, $store_empty = false )
{
	$sql_split = explode( '/', $xml_sql );
	$db_table = $sql_split[ 1 ]; // /hero/row[hero_id < 10]
	$xml_func = 'static_'. $get_type;
	$apc_data = $xml_func( $db_table, $xml_sql, $query_cols );
	//统计将数据intval处理一下
	if ( 'get_all' == $get_type )
	{
		array_array_intval( $apc_data );
	}
	else if ( is_array( $apc_data ) )
	{
		array_intval( $apc_data );
	}
	else if( is_numeric( $apc_data ) )
	{
		$apc_data = (int)$apc_data;
	}
	$opt_func = $db_table .'_apc_check';
	//有指定apc优化方式
	if ( function_exists( $opt_func ) )
	{
		$opt_func( $apc_data, $optimize );
	}
	if ( !empty( $apc_data ) || $store_empty )
	{
		apc_save_cache( $cachekey, $apc_data );
	}
	return $apc_data;
}

/**
 * 获取memcache里缓存的数据
 * @param string $cachekey 键值
 * @return mixed 成功 返回数据 失败 返回 false
 */
function get_cache( $cachekey )
{
	global $MEMCACHE;
	if ( !isset( $MEMCACHE[ $cachekey ] ) )
	{
		$tmp_data = first_cached::get( $cachekey, $cas );
		if ( false !== $tmp_data )
		{
			$MEMCACHE[ $cachekey ] = $tmp_data;
			$GLOBALS[ 'MEMCACHE_CAS_ARR' ][ $cachekey ] = $cas;
		}
		return $tmp_data;
	}
	return $MEMCACHE[ $cachekey ];
}

/**
 * 获取memcache里多个缓存
 * @param string $arr_keys
 * @return array 成功返回数据，无数据返回false
 */
function get_cache_many( $arr_keys )
{
	$caches = array();

	global $MEMCACHE;

	$un_global = array();

	foreach( $arr_keys as $cache_key )
	{
		if( isset( $MEMCACHE[ $cache_key ] ) )
		{
			$caches[ $cache_key ] = $MEMCACHE[ $cache_key ];
		}
		$un_global[] = $cache_key;
	}

	$arr_cached = first_cached::get_multi( $un_global, $arr_cas );
	if( !empty( $arr_cached ) )
	{
		$caches = array_merge( $caches, $arr_cached );
	}

	return !empty( $caches ) ? $caches : false;
}

/**
 * 清除掉缓存
 * @param string $cachekey 键值
 * @return void
 */
function remove_cache( $cachekey )
{
	global $MEMCACHE, $OUT_MEMCACHE_ARR;
	unset( $MEMCACHE[ $cachekey ], $OUT_MEMCACHE_ARR[ $cachekey ], $GLOBALS[ 'MEMCACHE_CAS_ARR' ][ $cachekey ] );
	first_cached::delete( $cachekey );
}

/**
 * 是否有某一个缓存值
 * @param string $cachekey 键值
 * @param mix $re 如果有缓存，将值写入$re变量
 * @return boolean true or false
 */
function has_cache ( $cachekey, &$re = false )
{
	$tmpvalue = get_cache( $cachekey );
	if ( false !== $re )
	{
		$re = $tmpvalue;
	}
	return false !== $tmpvalue;
}

/**
 * 更新memcache
 * @param string $key cache键值
 * @param mixed $value 值
 * @param int $expire 有效期
 * @param bool $need_cas 是否需要做cas校验
 * @return void
 */
function update_cache( $cachekey, $save_dat, $expire = 7200, $need_cas = true )
{
	global $MEMCACHE, $OUT_MEMCACHE_ARR;
	//如果不做cas校验
	if ( !$need_cas )
	{
		unset( $GLOBALS[ 'MEMCACHE_CAS_ARR' ][ $cachekey ] );
	}
	$MEMCACHE[ $cachekey ] = $save_dat;
	$tmp_data = array ( $save_dat, $expire );
	$OUT_MEMCACHE_ARR[ $cachekey ] = $tmp_data;
}

/**
 * 获取PHP cache
 * @param string $php__key 缓存的key
 * @return mixed 成功 返回数据 失败 false
 */
function get_php_cache ( $php__key )
{
	global $MEMCACHE;
	$php__key .= 'p.h.p';
	if ( isset( $MEMCACHE[ $php__key ] ) )
	{
		return $MEMCACHE[ $php__key ];
	}
	return false;
}

/**
 * 更新PHP cache
 * @param string $php_key 缓存key
 * @param array $up_data 更新数据
 * @return void
 */
function update_php_cache ( $php__key, $up_data )
{
	$php__key .= 'p.h.p';
	$GLOBALS[ 'MEMCACHE' ][ $php__key ] = $up_data;
}

/**
 * 检测是否含有某个PHP 缓存
 * @param string $php_key 缓存key
 * @param mix $re 如果有缓存，将值写入$re变量
 * @return boolean true or false
 */
function has_php_cache ( $php__key, &$re = false )
{
	$tmpvalue = get_php_cache( $php__key );
	if ( false !== $re )
	{
		$re = $tmpvalue;
	}
	return false !== $tmpvalue;
}

/**
 * 移除php_cache
 * @param string $php_key 缓存key
 * @return void
 */
function remove_php_cache ( $php__key )
{
	$php__key .= 'p.h.p';
	unset( $GLOBALS[ 'MEMCACHE' ][ $php__key ] );
}

/**
 * 增加一个互斥锁（读写都不允许）
 * @param string $key_name 锁名
 * @return void
 */
function mutex_lock ( $key_name )
{
	$key_name .= '_mutex_';
	global $GAMELOCK;
	if ( isset( $GAMELOCK[ $key_name ] ) )
	{
		return;
	}
	try
	{
		first_cached::add( $key_name, 1, 120 );
	}
	catch ( Exception $excp_obj )
	{
		if ( 14 != $excp_obj->getCode() )
		{
			throw $excp_obj;
		}
		data_error( 15 );
	}
	$GAMELOCK[ $key_name ] = 0;
}

/**
 * 释放一个互斥锁
 * @param string $key_name 锁名称
 */
function mutex_unlock( $key_name )
{
	$key_name .= '_mutex_';
	global $GAMELOCK;
	first_cached::delete( $key_name );
	unset( $GAMELOCK[ $key_name ] );
}

/**
 * 原子锁(不影响其它人读数据)
 * @param string $key_name 锁名称
 */
function atom_lock( $key_name )
{
	$key_name .= '_atom_';
	global $GAMELOCK;
	if ( isset( $GAMELOCK[ $key_name ] ) )
	{
		return;
	}
	$re = get_cache( $key_name );
	if ( false === $re )
	{
		$add_re = first_cached::add( $key_name, 1 );
		$re = get_cache( $key_name );
		//通过memcache加锁失败
		if ( false === $re )
		{
			$re = -1;
		}
	}
	$GAMELOCK[ $key_name ] = $re;
}

/**
 * 原子锁，解锁
 * @param string $key_name 锁名
*/
function atom_unlock( $key_name )
{
	$key_name .= '_atom_';
	global $GAMELOCK;
	if ( !isset( $GAMELOCK[ $key_name ] ) )
	{
		show_excp( '没有加锁:'. $key_name );
	}
	$lock_value = $GAMELOCK[ $key_name ];
	//失败锁
	if ( $lock_value < 0 )
	{
		return;
	}
	global $MEMCACHE_CAS_ARR;
	$unlock_re = first_cached::cas( $MEMCACHE_CAS_ARR[ $key_name ], $key_name, ++$lock_value );
	unset( $GAMELOCK[ $key_name ] );
	if ( true !== $unlock_re && Memcached::RES_DATA_EXISTS == first_cached::error_no() )
	{
		data_error( 15 );
	}
}

/**
 * 对已经加原子锁的数据验证
 */
function game_atom_unlock( )
{
	global $GAMELOCK;
	if ( empty( $GAMELOCK ) )
	{
		return;
	}
	global $MEMCACHE_CAS_ARR;
	foreach ( $GAMELOCK as $key => $value )
	{
		if ( $value <= 0 )
		{
			continue;
		}
		$re = first_cached::cas( $MEMCACHE_CAS_ARR[ $key ], $key, ++$value );
		if ( true !== $re && Memcached::RES_DATA_EXISTS == first_cached::error_no() )
		{
			data_error( 15 );
		}
	}
}

/**
 * 释放所有互斥锁
 * @return void
 */
function free_mutex_lock ( )
{
	global $GAMELOCK;
	if ( empty( $GAMELOCK ) )
	{
		return;
	}
	foreach ( $GAMELOCK as $key => $value )
	{
		if ( $value > 0 )
		{
			continue;
		}
		first_cached::delete( $key );
	}
}