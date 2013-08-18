<?php
//根路径
define( 'ROOT_PATH',  dirname( dirname( __FILE__ ) ) . '/' );
$GLOBALS[ 'SERVER_HOST' ] = $_SERVER[ 'HTTP_HOST' ];
//加载全局库文件
require ROOT_PATH .'lib/lib_global.php';
$file = ROOT_PATH .'main/tool/main_tool.php';
if ( is_file( $file ) )
{
	require $file;
}
/**
 * 生成cookie加密字符串
 * @param mixed $pack_dat 数据
 */
function cookie_pack( $pack_dat, $private_key )
{
	//默认cookie值
	$def_cookie = array(
		'username'			=> '',
	);
	$pack_arr = array( );
	foreach ( $def_cookie as $cok_item => $default_value )
	{
		$pack_arr[ $cok_item ] = isset( $pack_dat[ $cok_item ] ) ? $pack_dat[ $cok_item ] : $default_value;
	}
	//设置cookie的时间截
	$pack_arr[ 'ping' ] = time();
	$encode_str = msgpack_pack( $pack_arr );
	$result = array(
		'str' => $encode_str,
		'hash' => md5( $encode_str . $private_key )
	);
	return base64_encode( msgpack_pack( $result ) );
}

/**
 * 是否需要密码验证
 * @param bool $re_func_name 是否返回函数名
 */
function login_need_passwd( $re_func_name = false )
{
	$func_name = 'tool_auth_login';
	if ( $re_func_name )
	{
		return $func_name;
	}
	return function_exists( $func_name );
}

if ( isset( $_GET[ 'action' ] ) && 'login' == $_GET[ 'action' ] && !empty( $_POST[ 'username' ] ) )
{
	$username = $_POST[ 'username' ];
	if ( login_need_passwd() )
	{
		if ( empty( $_POST[ 'passwd' ] ) )
		{
			die( 'Please input password!' );
		}
		$func_name = login_need_passwd( true );
		if ( !$func_name( $username, $_POST[ 'passwd' ] ) )
		{
			die( 'Authentication failed!' );
		}
	}
	$auth_str = cookie_pack( array( 'username' => $username ), $GLOBALS[ 'GAME_INI' ][ 'cookie_key' ] );
	setcookie( 'auth_str', $auth_str );
	header( 'Location: index.php' );
	die();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>战姬无双 - 开发区</title>
<style>
<!--
ul{
	list-style-type: none;
}
li{
	padding-bottom:10px;
	color:#000;
	font-size:14px;
	height:22px;
}
li .left{
	width:100px;
	font-weight:bold;
	float:left;
	text-align:right;
	margin-right:10px;
}
form div
{
	font-size:14px;
}
-->

</style>
</head>
	<body>
	<div id="nav_div" style="height:40px;">
		<form action="login.php?action=login" method="post">
			<fieldset>
				<legend>登录</legend>
				<input type="hidden" name="act" value="login">
				<ul>
					<li style="text-align:center;"></li>
					<li>
						<div class="left">用户名: </div>
						<input name="username" type="text" maxlength="20"/>
					</li>
					<?php
					if ( login_need_passwd() )
					{
					?>
					<li>
						<div class="left">密码: </div>
						<input name="passwd" type="password" maxlength="20"/>
					</li>
					<?php
					}
					?>
				</ul>
				<div style="padding:0 0 0 200px">
					<input type="submit" value=" 登 录 "/>
				</div>
			</fieldset>
		</form>
	</div>
</body>
</html>
