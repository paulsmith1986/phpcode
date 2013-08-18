/**
 * 调试函数
 */
function first_group( data, g_title )
{
	if ( !window.console )
	{
		return;
	}

	if ( data._DEBUG_OBJ_ || data._DEBUG_STR_ )
	{
		if ( data._DEBUG_ERR_ )
		{
			console.error( data._DEBUG_ERR_.join( '' ) );
		}
		if ( data._GLOBAL_KEY_ )
		{
			console.error( "未清除的全局变量 " + data._GLOBAL_KEY_.join( ';' ) );
		}
		if ( data._SLOW_SQL_ )
		{
			console.warn( "%c本次请求存在慢SQL查询!", "color:red; background:yellow;font-weight:bold" );
		}
		console.groupCollapsed( "本次请求详情 %c" + g_title, "color:blue" );
		if ( data._ECHO_STR_ )
		{
			console.info( data._ECHO_STR_ );
		}
		if ( data._DEBUG_STR_ )
		{
			console.debug( data._DEBUG_STR_ );
		}
		console.groupEnd();
	}
	else
	{
		console.groupEnd();
		console.groupEnd();
		console.groupEnd();
		first_debug_pr( data, g_title, false );
	}
}

/**
 * json decode
 */
function json_decode ( str )
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

/**
 * 调试函数
 */
function first_debug_pr( data, g_title, is_open )
{
	if ( !window.console )
	{
		return;
	}
	if ( g_title )
	{
		if ( !is_open )
		{
			console.groupCollapsed( g_title );
		}
		else
		{
			console.group( g_title );
		}
		print_r( data );
		console.groupEnd();
	}
	else
	{
		print_r( data );
	}
}

/**
 * 类似php的print_r函数
 */
function print_r( obj )
{
	var FIRST_DEBUG_STR = [];
	function first_print( data, pre_fix  )
	{
		if ( 'object' != typeof data )
		{
			FIRST_DEBUG_STR.push( data );
		}
		else
		{
			FIRST_DEBUG_STR.push( "Array (\n" );
			var end_str = pre_fix + ')';
			pre_fix += "    ";
			if ( null != data && data.constructor == 'Array' )
			{
				for ( i = 0; i < data.length; ++i )
				{
					FIRST_DEBUG_STR.push( pre_fix + '['+ i +'] => ' );
					first_print( data[ i ], pre_fix );
					FIRST_DEBUG_STR.push( "\n" );
				}
			}
			else
			{
				for ( var p in data )
				{
					FIRST_DEBUG_STR.push( pre_fix + '['+ p +'] => ' );
					first_print( data[ p ], pre_fix );
					FIRST_DEBUG_STR.push( "\n" );
				}
			}
			FIRST_DEBUG_STR.push( end_str );
		}
	}
	first_print( obj, '' );
	console.info( FIRST_DEBUG_STR.join( "" ) );
	FIRST_DEBUG_STR = [];
}

var is_php_func_going = false;
function set_ajax_post( btn, url, arg )
{
	if ( null == arg )
	{
		arg = {};
	}
	url = 'index.php?c=tool&' + url;
	$('#'+ btn).click(function( e_dom ){
		if( is_php_func_going ) return;
		is_php_func_going = true;
		var do_str = e_dom.target.innerHTML;;
		$('#result').html( '<span class="green">正在 <span class="red">'+ do_str +'</span> 请稍等.....' );
		arg.code = $('#run_code').val();
		$.ajax( {
			type:"POST",
			url:url,
			data:arg,
			success:ajax_success,
			dataType:'text'
		} );
	});
}

function ajax_success( data )
{
	is_php_func_going = false;
	try
	{
		data = jQuery.parseJSON( data );
	}
	catch( err )
	{
		$('#result').html( '<div style="padding:0 2%;background-color:yellow;color:red"><pre>' + data + '</pre></div>' );
		return;
	}
	var htmlstr = '';
	if( data._ECHO_STR_ || data._DEBUG_ERR_ )
	{
		if ( data.ERR_CODE > 0 && data.ERR_CODE < 10000 )
		{
			htmlstr += '<div style="padding:0 2%;background-color:#EBFBE6;color:red"><pre>' + data.ERR_MSG + '</pre></div>';
		}
		if ( data._DEBUG_ERR_ && data._DEBUG_ERR_.length > 0 )
		{
			htmlstr += '<div style="padding:0 2%;background-color:yellow;color:red"><pre>' + data._DEBUG_ERR_.join( "\n" ) + '</pre></div>';
		}
		if( data._ECHO_STR_ && data._ECHO_STR_.length > 0 )
		{
			htmlstr += '<div style="padding:0 2%;background-color:#EBFBE6"><pre>' + data._ECHO_STR_ + '</pre></div>';
		}
	}
	$('#result').empty();
	$('#result').html( htmlstr );
	first_group( data, '' );
}