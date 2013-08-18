<?php
/**
 * 将一个副本数字串转换成数组
 * @param string $scen_str 字符串
 */
function im_channel_to_array( $scen_str )
{
	$scen_arr = explode( '_', $scen_str );
	if ( !isset( $scen_arr[ 2 ] ) )
	{
		show_excep( 'Channel string error!' );
	}
	$re = array(
		'ch_type'	=> ord( $scen_arr[ 0 ] ),
		'msg_time'	=> 0,
		'sub_id'	=> $scen_arr[ 1 ],
		'scene_id'	=> $scen_arr[ 2 ]
	);
	return $re;
}

/**
 * 发出IM管理数据包
 * @param string $pack_name 包名
 * @param array $pack_data 数据
 */
function im_admin ( $pack_name, $pack_data )
{
	global $OUT_IM_PROTOCOLS;
	if ( !isset( $OUT_IM_PROTOCOLS[ PROTOCOL_TO_ADMIN ] ) )
	{
		$OUT_IM_PROTOCOLS[ PROTOCOL_TO_ADMIN ] = array( );
	}
	$OUT_IM_PROTOCOLS[ PROTOCOL_TO_ADMIN ][ ] = array( $pack_name, $pack_data );
}