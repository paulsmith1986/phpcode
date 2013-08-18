<?php
class first_cached
{
	//cached类
	private static $cached = null;

	//操作次数
   	public static $opt_count = 0;

	//是否开启debug
	private static $is_debug = false;

	/**
	 * 连接服务器
	 */
	private static function connect()
	{
		if ( null != self::$cached )
		{
			return true;
		}
		self::$is_debug = ( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_PRINT_MEMCACHED );
		$cached = new Memcached();
		$ser_list = $cached->getServerList();
		if ( empty( $ser_list ) )
		{
			global $GAME_CACHE;
			$cached->addServer( $GAME_CACHE[ 'host' ], $GAME_CACHE[ 'port' ] );
		}
		self::$cached = $cached;
		return true;
	}

	/**
	 * 获取缓存
	 * @param string $key 键值
	 * @param mix $cas 校验值
	 * @return mixed 缓存数据
	 */
	public static function get ( $key, &$cas )
	{
		if ( null == self::$cached )
    	{
			self::connect();
		}
    	if ( self::$is_debug )
    	{
			$time1 = microtime ( true );
			++self::$opt_count;
			$ret = self::$cached->get( $key, null, $cas );
			$use_time = microtime ( true ) - $time1;
			self::make_debug( 'Get', $key, $ret, $use_time );
		}
		else
		{
			$ret = self::$cached->get( $key, null, $cas );
		}
		if ( empty( $ret ) )
		{
			$err_no = self::$cached->getResultCode();
			if ( 0 !== $err_no )
			{
				if ( Memcached::RES_SOME_ERRORS == $err_no )
				{
					trigger_error( 'Memcached get error!', E_USER_WARNING );
				}
				$ret = false;
			}
		}
		return $ret;
	}

	/**
	 * 获取多个缓存
	 * @param array $arr_keys 键值组
	 * @param array $arr_cas 标记组
	 * @return mixed 缓存数据
	 */
	public static function get_multi ( $arr_keys, & $arr_cas )
	{
		if ( null == self::$cached )
    	{
			self::connect();
		}
    	if ( self::$is_debug )
    	{
			$time1 = microtime ( true );
			++self::$opt_count;
			$ret = self::$cached->getMulti( $arr_keys, $arr_cas );
			$use_time = microtime ( true ) - $time1;
			self::make_debug( 'getMulti', $arr_keys, $ret, $use_time );
		}
		else
		{
			$ret = self::$cached->getMulti( $arr_keys, $arr_cas );
		}
		return $ret;
	}

    /**
     * 存储缓存
	 * @param string $key 键值
	 * @param mix $val 值
	 * @param int $exp 过期时间
	 * @return boolean 成功 true 失败 false
     */
    public static function set( $key, $val, $exp = 7200 )
    {
		if ( null == self::$cached )
    	{
			self::connect();
		}
    	if ( self::$is_debug )
    	{
			$time1 = microtime ( true );
			++self::$opt_count;
			$ret = self::$cached->set( $key, $val, $exp );
			$use_time = ( microtime ( true ) - $time1 );
			self::make_debug( 'Save', $key, $val, $use_time );
		}
		else
		{
			$ret = self::$cached->set( $key, $val, $exp );
		}
		return $ret;
    }

    /**
     * 存储缓存
	 * @param float $cas 检验值
	 * @param string $key 键值
	 * @param mix $val 值
	 * @param int $exp 过期时间
	 * @return boolean 成功 true 失败 false
     */
	public static function cas( $cas, $key, $val, $exp = 7200 )
	{
		if ( null == self::$cached )
    	{
			self::connect();
		}
    	if ( self::$is_debug )
    	{
			$time1 = microtime ( true );
			++self::$opt_count;
			$ret = self::$cached->cas( $cas, $key, $val, $exp );
			$use_time = ( microtime ( true ) - $time1 );
			self::make_debug( 'CAS_SAVE', $key, $val, $use_time );
		}
		else
		{
			$ret = self::$cached->cas( $cas, $key, $val, $exp );
		}
		return $ret;
	}

	//获取错误码
	public static function error_no()
	{
		return self::$cached->getResultCode();
	}

	/**
     * 删除某个缓存
     * @param string $key 缓存值
	 * @return boolean 成功 true 失败 false
     */
    public static function delete( $key )
    {
		if ( null == self::$cached )
    	{
			self::connect();
		}
		if ( self::$is_debug )
    	{
			++self::$opt_count;
			$ret = self::$cached->delete( $key );
			self::make_debug( 'Delete', $key );
		}
		else
		{
			$ret = self::$cached->delete( $key );
		}
		return $ret;
    }

	/**
     * 增加一个cache项
     * @param string $key 键名
     * @param mixed $value 键值
	 * @param int $expire 时间限制
	 * @return boolean 成功 true 失败 false
	 */
	public static function add ( $key, $value, $expire = 7200 )
	{
		if ( null == self::$cached )
    	{
			self::connect();
		}
    	if ( self::$is_debug )
    	{
			++self::$opt_count;
			$ret = self::$cached->add( $key, $value, $expire );
			self::make_debug( 'Add', $key, $value );
		}
		else
		{
			$ret = self::$cached->add( $key, $value, $expire );
		}
		if ( true !== $ret )
		{
			$err_no = self::$cached->getResultCode();
			if ( $err_no == Memcached::RES_NOTSTORED )
			{
				show_error( 'Memcached key:`'. $key .'` has exist!', 14 );
			}
			elseif ( Memcached::RES_WRITE_FAILURE == $err_no )
			{
				trigger_error( 'Memcached write failure!', E_USER_WARNING );
			}
		}
		return $ret;
	}

	/**
	 * 清空缓存
	 */
	public static function flush()
	{
		if ( null == self::$cached )
		{
			self::connect();
		}
		if ( self::$is_debug )
		{
			self::make_debug( 'Flush' );
		}
    	return self::$cached->flush();
    }

	/**
	 * 打印memcache调试信息
	 */
	public static function make_debug ( $do, $key = '', $val =  false, $use_time = 0 )
	{
		$str = '【MEMCACHED】'. $do;
		if ( !empty( $key ) )
		{
			$str .= ' Key:'. $key;
		}
		if ( !empty( $use_time ) )
		{
			$str .= ' Usetime:'. round( $use_time * 1000 , 2 ) .' 毫秒';
		}
		$value = '';
		if ( false !== $val )
		{
			if ( is_array( $val ) )
			{
				$value = print_r( $val, true );
			}
			else
			{
				$value = (string)$val;
			}
			if ( is_binary( $value ) )
			{
				$value = '[Binary]';
				$str .= ' Value:[Binary]';
			}
			elseif ( strlen( $value <= 20 ) )
			{
				$str .= ' Value:'. $value;
				$value = '';
			}
		}
		$str .= ' Recode:'. self::$cached->getResultCode();
		$GLOBALS[ 'DEBUG_STR' ][] = $str;
		if ( !empty( $value ) )
		{
			$GLOBALS[ 'DEBUG_STR' ][] = 'Value:'. $value;
		}
		$GLOBALS[ 'DEBUG_STR' ][] = '-----------------------------------------------------------------';
	}
}