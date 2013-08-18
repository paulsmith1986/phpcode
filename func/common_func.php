<?php
/**
 * 传入数组，返回某一项概率
 * @param array	 $array	 概率数组array( 'key_1' => 0.1, 'key_2' => 0.3, 'key_3' => 0.4 )
 * @param int $degree 精度
 * @param string $except 忽略项
 */
function get_rand_key ( $array, $degree = 1, $except = null )
{
	if ( null != $except )
	{
		unset( $array[ $except ] );
	}
	$total = array_sum ( $array ) * $degree;
	if ( !$total )
	{
		show_excp( 'get_rand_key 传入的array数据出错' );
	}
	$intRand = mt_rand ( 0, $total );
	$offset = $value = 0;
	foreach ( $array as $key => $item )
	{
		$value = $item * $degree;
		if ( $intRand <= $value + $offset )
		{
			return $key;
		}
		$offset += $value;
	}
}

/**
 * print_r
 */
function pr( $is_out = false )
{
	$re = print_r( $is_out );
	if ( $is_out )
	{
		return $re;
	}
}

/**
 * 将数组里所有全是数字组成的字符串变成int型
 * @param array $init_arr 原始数组
 * @param mixed $excp_key 不处理的key
 */
function array_intval( &$init_arr, $excp_key = null )
{
	if ( null == $excp_key )
	{
		foreach( $init_arr as $key => &$value )
		{
			if ( is_numeric( $value ) )
			{
				$value = (int)$value;
			}
		}
	}
	else
	{
		$excp_key = array_flip( $excp_key );
		foreach( $init_arr as $key => &$value )
		{
			if ( is_numeric( $value ) && !isset( $excp_key[ $key ] ) )
			{
				$value = (int)$value;
			}
		}
	}
}

/**
 * 将数组的key和value都int处理
 * @param array $init_arr 传入数组
 */
function array_key_intval( &$init_arr )
{
	$new_data = array();
	if ( is_array( $init_arr ) )
	{
		foreach ( $init_arr as $key => $value )
		{
			$new_data[ (int)$key ] = (int)$value;
		}
	}
	$init_arr = $new_data;
}

/**
 * 将二维数组里所有的数字组成的字符串变成int型
 * @param array $init_arr 原始数组
 * @param mixed $excp_key 不处理的key
 */
function array_array_intval( &$init_arr, $excp_key = null )
{
	foreach ( $init_arr as $key => &$temp_arr )
	{
		array_intval( $temp_arr, $excp_key );
	}
}

/**
 * 判断一个概率事件有没有发生
 * @param double $value 概率
 * @param int $base 基数
 * @param int $degree 倍率
 */
function is_rand( $value, $base = 100, $degree = 1 )
{
	if ( $value <= 0 )
	{
		return false;
	}
	$rand = mt_rand( 1, $base * $degree );
	return $rand <= $value;
}

/**
 * 将数组以转化成简单的字符串
 * @param array $arr 数组
 * @param string $split_main 主分割字符
 * @param string $split_sub 辅分割字符
 */
function array_to_str( $arr, $split_main = ',', $split_sub = ':' )
{
	$re = array();
	foreach ( $arr as $item => $value )
	{
		$re[] = $item . $split_sub . $value;
	}
	return implode( $split_main, $re );
}

/**
 * 两个时间戳是否同一天
 * @param int $t1
 * @param int $t2
 * @return bool
 */
function is_same_day( $t1, $t2 )
{
	$day_1 = floor( $t1 / 86400 );
	$day_2 = floor( $t2 / 86400 );
	return $day_1 == $day_2;
}

/**
 * 判断传入的时间是不是今天 之内的时间点
 */
function is_today( $tmp_time )
{
	return strtotime( 'today' ) >= $tmp_time;
}