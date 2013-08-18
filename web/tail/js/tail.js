
function flash_call_func( arg )
{
	return true;
}

/**
 * Flash准备好回调
 */
function set_ready_flag ( is_ready )
{
	if ( !is_ready )
	{
		return;
	}
	flash_socket = document.getElementById( 'Tailas' );
	if ( !flash_socket )
	{
		console.error( 'Can not get flash' );
	}
	else
	{
		connect_server();
	}
}

/**
 * 连接服务器
 */
function connect_server ()
{
	var str = '连接 tail server:'+ tail_host +':'+ tail_port;
	console.debug( str );
	set_tail_content( str );
	flash_socket.socket_connect( tail_host, tail_port );
}

/**
 * 发送数据
 */
function socket_send ( data )
{
	if ( !is_socket_connect )
	{
		console.warn( '未连接服务器, 不能发送数据:' + data );
		return;
	}
	flash_socket.socket_send( data );
}

/**
 * 网段选择
 */
function join_host ( host_type )
{
	var tmp = tail_host.split( '.' );
	if ( 1 == tmp[ 2 ] || 2 == tmp[ 2 ] )
	{
		tmp[ 2 ] = host_type;
	}
	tail_host = tmp.join( '.' );
	connect_server();
}

/**
 * 连接结果
 */
function on_connect ( arg )
{
	if ( arg )
	{
		console.debug( '成功连接服务器' );
		is_socket_connect = true;
		load_tree();
		//每10秒刷新一次目录结构
		setInterval( load_tree, 10 * 1000 );
		retry_time = 0;
		set_tail_content( '连接成功,请选择查看文件' );
	}
	else
	{
		console.error( '连接服务器失败' );
		if ( ++retry_time > 10 )
		{
			return;
		}
		var tmp = tail_host.split( '.' );
		if ( '1' == tmp[ 2 ] || '2' == tmp[ 2 ] )
		{
			if ( '1' == tmp[ 2 ] )
			{
				tmp[ 2 ] = '2';
			}
			else
			{
				tmp[ 2 ] = '1';
			}
		}
		tail_host = tmp.join( '.' );
		console.debug( '重试, 第 '+ retry_time +' 次' );
		connect_server();
	}
}

/**
 * 加载目录列表
 */
function load_tree ( )
{
	if ( is_sending_file )
	{
		return;
	}
	//console.info('请求目录...');
	socket_send( 'tree|' + file_expire );
}

/**
 * 收到数据
 */
function on_data ( data )
{
	var split_pos = data.indexOf( '|' );
	var head = data.substring( 0, split_pos );
	var content = data.substring( split_pos + 1 );
	switch ( head )
	{
		case 'tree':		//列出目录树
			list_tree( content );
		break;
		case 'tail':		//文件更新内容
			if(!is_tail_pause)
			{
				tail_update( content );
			}
		break;
		case 'file':
			tail_file_re( content );
		break;
	}
}

/**
 * 更新内容
 */
function tail_update ( content )
{
	var content_div = $( 'content' );
	var new_content = content_div.innerHTML + content;
	if ( new_content.length > 65535 )
	{
		new_content = new_content.substring( new_content.length - 35000 );
	}
	content_div.innerHTML = new_content;
	var sh = content_div.scrollHeight;
	content_div.scrollTop = sh + 10;
}

/**
 * 设置右边内容
 */
function set_tail_content ( new_content )
{
	$( 'content' ).innerHTML = new_content;
}

/**
 * 选择查看文件返回
 */
function tail_file_re ( content )
{
	is_sending_file = false;
	if ( !is_tail_file )
	{
		is_tail_file = true;
		//tail_time_id = setInterval( send_tail, 500 );
		send_tail();
		$( 'pause' ).style.display = 'block';
	}
	set_tail_content( '' );
	tail_update( content );
}

/**
 * 不停更新文件
 */
function send_tail ()
{
	if ( is_sending_file )
	{
		return;
	}
	socket_send( 'tail' );
}

/**
 * 出错了
 */
function on_error ( err_type )
{
	if ( 1 == err_type )
	{
		console.warn( '安全沙箱错误' );
	}
	else if ( 2 == err_type )
	{
		console.warn( 'IO错误' );
	}
	on_connect( false );
}

/**
 * 列出文档目标
 */
function list_tree ( content )
{
	list_arr = json_decode( content );
	if ( 'string' == typeof( list_arr ) )
	{
		console.warn( '目录返回出错:'+ content );
		return;
	}
	var list_div = $( 'nav' );
	if ( !list_div )
	{
		return;
	}
	html_str = [ '<ul>' ];
	recurse_list_tree( list_arr, html_str, 0, '' );
	list_div.innerHTML = html_str.join( '' );
}

/**
 * 递归列出文档树
 */
function recurse_list_tree ( list_arr, html_str, rank, path )
{
	html_str.push( '<ul>' );
	for ( var p in list_arr )
	{
		var file = list_arr[ p ];
		if ( 1 == file[ 'type' ] && 0 == file[ 'list' ].length )
		{
			continue;
		}
		html_str.push( '<li' );
		if ( rank > 0 )
		{
			html_str.push( ' class="item_rank"' );
		}
		html_str.push( '>' );
		if ( 1 == file[ 'type' ] )
		{
			html_str.push( file[ 'name' ] );
			recurse_list_tree( file[ 'list' ], html_str, rank + 1, path + file[ 'name' ] + '/' );
		}
		else
		{
			html_str.push( '<a href="javascript:void(0)" onclick="set_tail_file(\''+ path + file[ 'name' ] +'\')">'+ file[ 'name' ] + '[<span class="file_size"><a href="tail.php?file='+ path + file[ 'name' ] +'">' + file_size( file[ 'size' ] ) + '</a></span>]' +'</a>' );
			html_str.push( '[<a class="file_down" href="http://'+ tail_host +':'+ tail_port +'?file='+ encodeURIComponent( path + file[ 'name' ] ) +'" target="_blank">下载</a>]' );
		}
		html_str.push( '</li>' );
	}
	html_str.push( '</ul>' );
}

/**
 * 文件大小显示
 */
function file_size ( size )
{
	if ( size > 1048576 )
	{
		size /= 1048576;
		size = size.toFixed( 2 ) +'M';
	}
	else if ( size > 1024 )
	{
		size /= 1024;
		size = size.toFixed( 2 ) +'K';
	}
	return size;
}

/**
 * 选择查看文件
 */
function set_tail_file ( file_name )
{
	if ( is_sending_file )
	{
		console.warn( '正在与服务器通讯, 请稍候' );
	}
	var obj = $( 'file_name' );
	obj.innerHTML = '文件:' + file_name;
	socket_send( 'file|' + file_name );
	set_tail_content( '加载 '+ file_name +'...' );
	is_sending_file = true;
}

/**
 * 将一个字符串重复多少次
 */
function str_repeat ( str, time )
{
	if ( 0 == time )
	{
		return str;
	}
	else
	{
		var tmp_arr = [];
		for ( var i = 0; i <= time; ++i )
		{
			tmp_arr.push( str );
		}
		return tmp_arr.join( '' );
	}
}

/**
 * json_decode
 */
function json_decode( str )
{
	try
	{
		return eval('(' + str + ')' );
	}
	catch( excp )
	{
		return str;
	}
}

function $( domId )
{
	return document.getElementById( domId );
}

/**
 * 断开连接
 */
function on_close ()
{
	console.warn( '服务器断开' );
	is_socket_connect = false;
	setTimeout( connect_server, 3000 );
}

//是否正在设置查看文件
var is_sending_file = false;

//连接重试
var retry_time = 0;
//是否连接服务器
var is_socket_connect = false;
//是否选择tail文件
var is_tail_file = false;
//是否暂停
var is_tail_pause = false;
//host
var tail_host = '192.168.2.9';
//port
var tail_port = 6688;
//tail的time
tail_time_id = -1;

//显示多久以内的文件
var file_expire = 86400 * 5;

var flash_socket;
var flashvars = {};
var params = {
	menu: "false",
	scale: "noScale",
	allowFullscreen: "true",
	allowScriptAccess: "always",
	bgcolor: "",
	wmode: "direct"
};
var attributes = {
	id:"Tailas"
};
swfobject.embedSWF(
	"Tailas.swf",
	"altContent", "100%", "100%", "10.0.0",
	"expressInstall.swf",
	flashvars, params, attributes
);

/**
 * 设置大小
 */
function set_size ()
{
	var h = document.body.clientHeight;
	var tree_div = $( 'nav' );
	var content_div = $( 'content' );
	tree_div.style.height = content_div.style.height = ( h - 135 ) + 'px';
}

/**
 * 暂停或者重开
 */
function pause_tail ()
{
	var obj_str = $( 'pause_str' );
	if ( is_tail_pause )
	{
		is_tail_pause = false;
		obj_str.innerHTML = '停止';
		//tail_time_id = setInterval( send_tail, 500 );
	}
	else
	{
		is_tail_pause = true;
		obj_str.innerHTML = '开始';
		/*
		if ( tail_time_id > 0 )
		{
			clearInterval( tail_time_id );
			tail_time_id = 0;
		}
		*/
	}
}

/**
 * 控制台检查
 */
function check_console()
{
	if ( !window.console )
	{
		alert( window.console );
		window.console = {
			'debug': function(){},
			'info': function(){},
			'error': function(){},
			'warn': function(){}
		};
	}
}

/**
 * 初始化tail客户端
 */
function init_tail()
{
	check_console();
	window.onresize = set_size;
	set_size();
	var query_arg = window.location.search.substring( 1 ).split( '&' );
	for ( var i = 0; i < query_arg.length; ++i )
	{
		var each_arg = query_arg[ i ].split( '=' );
		if ( 'host' == each_arg[ 0 ] && 2 == each_arg.length )
		{
			if ( 'public' == each_arg[ 1 ] )
			{
				tail_host = '192.168.1.9';
			}
			break;
		}
	}
}