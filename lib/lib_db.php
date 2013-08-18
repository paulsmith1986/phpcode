<?php
class first_db
{
	//存放连接
	private $link_obj = null;
	//调试模式标志
	public $is_debug = 0;
	//是否使用事务
	public $use_commit = false;
	//是否记录慢查询
	public $slow_sql = true;
	//执行SQL数组
	public $exec_sql = array();
	//不需要立即写进数据库的insert
	public $insert_pool_data = array();
	//用于合并写库的update
	public $update_pool_data = array();
	//二进制SQL缓存
	private $bin_sql_key = array();
	//提交事务是否回调
	private $commit_callback = false;
	//rand_key出错是否回调
	private $rand_key_callback = false;
	//连接参数
	public $connect_arg;
	/**
	 * 构造函数
	 * @param array $dsn 连接数据库配置
	 * @return void
	 */
	public function __construct( $dsn )
	{
		$this->connect_arg = $dsn;
		$this->connect();
		$func_name = 'commit_callback';
		if ( function_exists( $func_name ) )
		{
			$this->commit_callback = true;
		}
		$func_name = 'rand_key_callback';
		if ( function_exists( $func_name ) )
		{
			$this->rand_key_callback = true;
		}
	}
	/**
	 * 连接
	 */
	private function connect()
	{
		if ( $this->is_debug )
		{
			$this->connect_time = 0;
			$this->sql_count = 0;
			$this->total_time = 0;
			$time1 = microtime ( true );
		}
		$dsn = $this->connect_arg;
		$link_obj = mysql_connect( $dsn[ 'host' ], $dsn[ 'user' ], $dsn[ 'pass' ], true );
		if ( !$link_obj )
		{
			throw new Exception( 'MYSQL '. $dsn[ 'host' ] .' connect failed', 99999 );
		}
		mysql_set_charset( 'utf8', $link_obj );
		//mysql_query( 'set names utf8', $link_obj );
		if ( !mysql_select_db( $dsn[ 'path' ], $link_obj ) )
		{
			throw new Exception( 'MYSQL DB '. $dsn[ 'path' ] .' database is not exist', 99999 );
		}
		$this->link_obj = $link_obj;
		if ( $this->is_debug )
		{
			$use_time = microtime ( true ) - $time1;
			$this->connect_time = $use_time;
			$tmp_str = '连接数据库时间：' . $use_time * 1000 . '毫秒';
			if ( 2 == $this->is_debug )
			{
				$tmp_str = '【APC】'.$tmp_str;
			}
			$GLOBALS[ 'DEBUG_STR' ][] = $tmp_str;
			$GLOBALS[ 'DEBUG_STR' ][] = '-----------------------------------------------------------------';
		}
	}

	/**
	 * mysql心跳包，如果断开，自动重连
	 */
	public function ping()
	{
		$re = mysql_ping( $this->link_obj );
		if ( !$re )
		{
			$this->close();		//关闭当前连接
			$this->clean_up();
			$retry_time = 0;
			$is_connect = false;
			while( ++$retry_time < 3 )
			{
				try
				{
					$this->connect();	//重新连接
					$is_connect = true;
					break;
				}
				catch( Exception $excp_obj )
				{
					do_with_excp( $excp_obj );
				}
			}
		}
		else
		{
			//重连上后，要重新设置连接utf8
			mysql_set_charset( 'utf8', $link_obj );
		}
	}

	/**
	 * 释放数据库连接
	 * @return void
	 */
	public function close()
	{
		if ( is_resource( $this->link_obj ) )
		{
			mysql_close( $this->link_obj );
		}
		unset( $this->link_obj );
	}

	/**
	 * 查询操作
	 * @param string $querysql 要执行查询的SQL语句
	 * @param bool $is_wirte 是否是写数据库
	 * @return mixed 执行结果
	 */
	private function execute_sql( $querysql, $is_write = false )
	{
		if ( $is_write && !$this->use_commit )
		{
			$this->execute_sql( 'BEGIN' );
			$this->use_commit = true;
		}
		$this->exec_sql[] = $querysql;
		if ( $this->is_debug )
		{
			$time = microtime( true );
			$res = mysql_query( $querysql, $this->link_obj );
			$run_time = microtime( true ) - $time;
			$this->total_time += $run_time;
			$this->sql_count++;
			$tmp_str = $querysql;
			$sql_begin = substr( $tmp_str, 0, 6 );
			//二进制SQL判断
			if ( 'INSERT' == $sql_begin || 'UPDATE' == $sql_begin )
			{
				foreach ( $this->bin_sql_key as $tmp_key => $v )
				{
					if ( 0 === strpos( $tmp_str, $tmp_key ) )
					{
						$tmp_str = $tmp_key .' [BINARY]';
					}
				}
			}
			if ( 2 == $this->is_debug )
			{
				$tmp_str = '【APC】'. $tmp_str;
			}
			$GLOBALS[ 'DEBUG_STR' ][] = $tmp_str;
			$GLOBALS[ 'DEBUG_STR' ][] = 'Affectrows: '. mysql_affected_rows ( $this->link_obj );
			$GLOBALS[ 'DEBUG_STR' ][] = 'Query time: '. round( $run_time * 1000, 2 ) .' 毫秒';
			$GLOBALS[ 'DEBUG_STR' ][] = '-----------------------------------------------------------------';
			if ( $run_time > 0.1 && 'COMMIT' != $querysql )
			{
				$this->record_slow_sql( $run_time, $querysql );
				$GLOBALS[ '_SLOW_SQL_' ] = true;
			}
		}
		elseif ( $this->slow_sql )
		{
			$time = microtime( true );
			$res = mysql_query( $querysql, $this->link_obj );
			$run_time = microtime( true ) - $time;
			if ( $run_time > 0.1 && 'COMMIT' != $querysql )
			{
				$this->record_slow_sql( $run_time, $querysql );
			}
		}
		else
		{
			$res = mysql_query( $querysql, $this->link_obj );
		}
		if ( false === $res )
		{
			//如果是【MySql server has gone away.】错误，则ping一下，如果是通的，再执行一遍
			if ( 2006 == mysql_errno( $this->link_obj ) )
			{
				$use_commit = $this->use_commit;
				//尝试ping或者重连
				$this->ping();
				//如果不在事务中，该SQL可以重新执行。如果在事务中，应该抛出错误，因为事务已经中断，前面执行的SQL会自动rollback。
				if ( !$use_commit )
				{
					return $this->execute_sql( $querysql );
				}
			}
			show_excp( mysql_error( $this->link_obj ) );
		}
		return $res;
	}

	/**
	 * 慢查询记录
	 * @param float $run_time 时间
	 * @param string $querysql sql语句
	 */
	private function record_slow_sql( $run_time, $querysql )
	{
		$log_time = round( $run_time * 1000, 3 );
		$save_str = '['. $log_time .']'. $querysql ."\n";
		save_game_log( $save_str, 'slow_sql' );
	}

	/**
	 * 是否为二进制SQL
	 * @param string $db_table 表名
	 * @param array $data 数据
	 */
	private function is_binary_sql( $db_table, $data )
	{
		$tmp = explode( '_', $db_table );
		if ( is_numeric( end( $tmp ) ) )
		{
			array_pop( $tmp );
		}
		$table = join( '_', $tmp );
		$bin_set = array(
			'combat_data'		=> array( 'data' => 1 ),
			'task_queue'		=> array( 'args' => 1 ),
		);
		$re = false;
		if ( isset( $bin_set[ $table ] ) )
		{
			foreach ( $data as $key => $value )
			{
				if ( isset( $bin_set[ $table ][ $key ] ) )
				{
					$re = true;
					break;
				}
			}
		}
		return $re;
	}

	/**
	 * 取得所有数据
	 * @param string $querysql SQL语句
	 * @param string $field 以字段做为数组的key
	 * @return array 记录集 如果没有记录,返回array()
	 */
	public function get_all( $querysql, $indexkey = null )
	{
		$res = $this->execute_sql( $querysql );
		$rows = array( );
		if ( !$res )
		{
			return $rows;
		}
		if ( null == $indexkey )
		{
			while ( $row = mysql_fetch_assoc( $res ) )
			{
				$rows[] = $row;
			}
		}
		else
		{
			while ( $row = mysql_fetch_assoc( $res ) )
			{
				$rows[ $row[ $indexkey ] ] = $row;
			}
		}
		mysql_free_result( $res );
		return $rows;
	}
	/**
	 * 以get_row方式取得所有数据
	 * @param string $querysql SQL语句
	 * @param int $index 以字段做为数组的key
	 * @return array 记录信 如果没有记录 返回array()
	 */
	public function get_all_row( $querysql, $indexkey = -1 )
	{
		$res = $this->execute_sql( $querysql );
		$rows = array( );
		if ( !$res )
		{
			return $rows;
		}
		if ( -1 == $indexkey )
		{
			while ( $row = mysql_fetch_row( $res ) )
			{
				$rows[] = $row;
			}
		}
		else
		{
			while ( $row = mysql_fetch_row( $res ) )
			{
				$rows[ $row[ $indexkey ] ] = $row;
			}
		}
		mysql_free_result( $res );
		return $rows;
	}

	/**
	 * 返回所有记录中以第一个字段为值的数组
	 * @param string $querysql SQL语句
	 * @return array 记录信 如果没有记录 返回array()
	 */
	public function get_col( $querysql )
	{
		$res_data = $this->execute_sql( $querysql );
		$res_rows = array( );
		if ( !$res_data )
		{
			return $res_rows();
		}
		while ( $row = mysql_fetch_row( $res_data ) )
		{
			$res_rows[] = $row[ 0 ];
		}
		mysql_free_result( $res_data );
		return $res_rows;
	}

	/**
	 * 返回所有记录中以第一个字段为key,第二个字段为值的数组
	 * @param string $querysql SQL语句
	 * @return array 记录信 如果没有记录 返回array()
	 */
	public function get_pair( $querysql )
	{
		$res_data = $this->execute_sql( $querysql );
		$res_rows = array( );
		if ( !$res_data )
		{
			return $res_rows;
		}
		while ( $row = mysql_fetch_row( $res_data ) )
		{
			$res_rows[ $row[ 0 ] ] = $row[ 1 ];
		}
		mysql_free_result( $res_data );
		return $res_rows;
	}

	/**
	 * 取得第一条记录
	 * @param string $sql SQL语句
	 * @return mixed 记录 如果没有 返回null
	 */
	public function get_row( $querysql )
	{
		$res_data = $this->execute_sql( $querysql );
		if ( !$res_data )
		{
			return null;
		}
		$row = mysql_fetch_assoc( $res_data );
		mysql_free_result( $res_data );
		return $row;
	}

	/**
	 * 取得第一条记录的第一个字段值
	 * @param string $sql SQL语句
	 * @return mixed 字段数据 如果没有 返回null
	 */
	public function get_one( $querysql )
	{
		$res_data = $this->execute_sql( $querysql );
		if ( !$res_data )
		{
			return null;
		}
		$row = mysql_fetch_row( $res_data );
		mysql_free_result( $res_data );
		return $row[ 0 ];
	}

	/**
	 * 插入一条记录
	 * @param string $table 表数
	 * @param array $row 数据
	 * @param bool $push_out 是否缓时写数据库
	 * @return mixed 如果实时写库,返回影响条数
	 */
	public function insert( $db_table, $data_row, $push_out = true )
	{
		$cols_arr = array();
		$vals_arr = array();
		foreach ( $data_row as $col_item => $val_item )
		{
			$cols_arr[] = $col_item;
			$vals_arr[] = $val_item;
		}
		$join_col = join( '`,`', $cols_arr );
		$join_val = "('". join( "','", $vals_arr ) ."')";
		//调试模式 二进制sql判断
		if ( $this->is_debug && $this->is_binary_sql( $db_table, $data_row ) )
		{
			$tmp_key = 'INSERT INTO `'. $db_table .'` (`' . $join_col . '`) VALUES';
			$this->bin_sql_key[ $tmp_key ] = true;
		}
		$re = 0;
		if ( $push_out )
		{
			$this->insert_pool_data[ $db_table ][ $join_col ][] = $join_val;
		}
		else
		{
			$querysql = 'INSERT INTO `'. $db_table .'` (`' . $join_col . '`) VALUES '. $join_val;
			$this->execute_sql( $querysql, true );
			$re = mysql_affected_rows ( $this->link_obj );
		}
		return $re;
	}

	/**
	 * 根据步长插入数据
	 * @param string $db_table 表名
	 * @param array $data_row 数据
	 * @param int $the_hash 分表数据
	 */
	public function step_insert( $db_table, $data_row, $the_hash )
	{
		//测试环境
		if ( $GLOBALS[ 'DEBUG_LEVEL' ] & DEF_DEVELOPMENT )
		{
			$this->insert( $db_table .'_0', $data_row, false );
		}
		else
		{
			$the_hash %= 16;
			$step = 0 == $the_hash ? 16 : $the_hash;
			mysql_query( 'set auto_increment_increment = '. 16, $this->link_obj );
			mysql_query( 'set auto_increment_offset = '. $step, $this->link_obj );
			$db_table .= '_'. $the_hash;
			$this->insert( $db_table, $data_row, false );
			mysql_query( 'set auto_increment_increment = 1;', $this->link_obj );
			mysql_query( 'set auto_increment_offset = 1', $this->link_obj );
		}
		return $this->last_insert_id();
	}

	/**
	 * 数据更新 只能缓时写
	 * @param string $db_table 表名
	 * @param array $new_data 新数据
	 * @param string $where 更新条件
	 * @param bool $push_out 是否缓时写数据库
	 * @return void
	 */
	public function update( $db_table, $new_data, $up_where = '1', $push_out = true )
	{
		//如果更新数据为空，直接返回
		if ( empty( $up_where ) )
		{
			return;
		}
		if ( !isset( $this->update_pool_data[ $db_table ][ $up_where ] ) )
		{
			//如果有用于验证的随机值
			if ( isset( $new_data[ 'rand_key' ] ) )
			{
				$tmp_value = $new_data[ 'rand_key' ] - 1;
				if ( -1 == $tmp_value )
				{
					$tmp_value = 65534;
				}
				//当randkey验证失败时，回调函数
				if ( !isset( $new_data[ 'rand_key_fail' ] ) )
				{
					show_excp( '自动rand_key验证需要传失败时的回调参数' );
				}
				$new_data[ 'old_rand_key' ] = $tmp_value;
			}
			$this->update_pool_data[ $db_table ][ $up_where ] = $new_data;
		}
		else
		{
			$this->update_pool_data[ $db_table ][ $up_where ] = array_merge( $this->update_pool_data[ $db_table ][ $up_where ], $new_data );
		}
		//调试模式 二进制sql判断
		if ( $this->is_debug && $this->is_binary_sql( $db_table, $new_data ) )
		{
			$tmp_key = 'UPDATE `' . $db_table . '` SET';
			$this->bin_sql_key[ $tmp_key ] = true;
		}
		//不缓时写库.立即更新
		if ( false === $push_out )
		{
			$this->do_db_update( $db_table, $up_where, $this->update_pool_data[ $db_table ][ $up_where ] );
			unset( $this->update_pool_data[ $db_table ][ $up_where ] );
			return mysql_affected_rows ( $this->link_obj );
		}
	}

	/**
	 * 删除数据
	 * @param string $table 表名
	 * @param string $where 条件
	 * @return int 影响条数
	 */
	public function delete( $db_table, $de_where )
	{
		$this->execute_sql( 'DELETE FROM ' . $db_table . ' WHERE ' . $de_where, true );
		return mysql_affected_rows ( $this->link_obj );
	}

	/**
	 * 取得最后的lastInsertId
	 * @return int 最后插入的id
	 */
	public function last_insert_id()
	{
		return mysql_insert_id( $this->link_obj );
	}

	/**
	 * 事务提交
	 * @return void
	 */
	public function commit()
	{
		if ( $this->commit_callback )
		{
			commit_callback();
		}

		//写入的数据
		if ( !empty( $this->insert_pool_data ) )
		{
			foreach ( $this->insert_pool_data as $db_table => $tmp_data )
			{
				foreach ( $tmp_data as $cols_arr => $cols_val )
				{
					$querysql = 'INSERT INTO `'. $db_table .'` (`'. $cols_arr .'`) VALUES '. join( ',', $cols_val );
					$this->execute_sql( $querysql, true );
				}
			}
			$this->insert_pool_data = array(  );
		}

		//更新的数据
		if ( !empty( $this->update_pool_data ) )
		{
			foreach ( $this->update_pool_data as $db_table => $tmp_data )
			{
				foreach ( $tmp_data as $up_where => $data_arr )
				{
					$this->do_db_update( $db_table, $up_where, $data_arr );
				}
			}
			$this->update_pool_data = array(  );
		}

		if ( $this->use_commit )
		{
			game_atom_unlock();		//数据原子锁验证
			$this->execute_sql( 'COMMIT' );
		}
		$this->use_commit = false;
	}

	/**
	 * 数据库更新操作
	 * @param string $db_table 数据表
	 * @param string $up_where 条件
	 * @param array $data_arr 数据
	 */
	private function do_db_update( $db_table, $up_where, $data_arr )
	{
		$need_affect_rows = false;
		if ( isset( $data_arr[ 'old_rand_key' ] ) )
		{
			$up_where .= ' AND rand_key='. $data_arr[ 'old_rand_key' ];
			$up_fail_args = $data_arr[ 'rand_key_fail' ];
			unset( $data_arr[ 'old_rand_key' ], $data_arr[ 'rand_key_fail' ] );
			$need_affect_rows = true;
		}
		$sets_arr = array();
		foreach ( $data_arr as $col_item => $val_item )
		{
			$sets_arr[] = '`'. $col_item ."`='". $val_item ."'";
		}
		$querysql = 'UPDATE `' . $db_table . '` SET ' . implode( ', ', $sets_arr ) . ' WHERE ' . $up_where;
		$this->execute_sql( $querysql, true );
		//影响条数为0
		if ( $need_affect_rows && 0 == mysql_affected_rows( $this->link_obj ) )
		{
			//将分表后边的数字过滤掉
			$tmp = explode( '_', $db_table );
			if ( is_numeric( end( $tmp ) ) )
			{
				array_pop( $tmp );
			}
			//执行回调
			if ( $this->rand_key_callback )
			{
				rand_key_callback( join( '_', $tmp ), $up_fail_args );
			}
			//抛出重试异常
			data_error( 8 );
		}
	}

	/**
	 * 事务回滚
	 */
	public function roll_back()
	{
		if ( !empty( $this->insert_pool_data ) )
		{
			$this->insert_pool_data = array();
		}
		if ( !empty( $this->update_pool_data ) )
		{
			$this->update_pool_data = array();
		}
		if ( !$this->use_commit )
		{
			return;
		}
		$this->use_commit = false;
		$this->execute_sql( 'ROLLBACK' );
	}

	/**
	 * 清理执行过程中的sql
	 */
	public function clean_up ()
	{
		$this->exec_sql = array( );
		$this->bin_sql_key = array( );
		$this->use_commit = false;
	}
}