<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>调式器</title>
<script type="text/javascript" src="static/jquery.js"></script>
<script type="text/javascript" src="static/console.js"></script>
<link rel="stylesheet" href="static/main.css"/>
</head>
<body style="font-size:12px;">
	<div>
		<div style="text-align:center">
		<?php
		foreach ( $TOOL_MENU as $type => $each_menu )
		{
			echo '<a href="javascript:void(0)" id="'. $type .'">'. $each_menu[ 'text' ] ."</a>\n";
		}
		?>
		</div>
		<hr/>
		<div>
			<div>
				<textarea id="run_code"></textarea>
			</div>
			<div style="text-align:center">
				<input type="button" value="执行代码" id="run_code_btn"/>
			</div>
		</div>
		<div id="result">
			<?php if( !empty( $GLOBALS[ 'OUT_DATA' ][ '_ECHO_STR_' ] ) ) echo $GLOBALS[ 'OUT_DATA' ][ '_ECHO_STR_' ];?>
		</div>
	</div>
</body>
<script type="text/javascript">
set_ajax_post( 'run_code_btn', 'a=op&type=run_code' );
<?php
foreach ( $TOOL_MENU as $type => $each_menu )
{
	echo 'set_ajax_post( "'. $type .'", "a=op&type='. $type .'", null );', "\n";
}
?>
</script>
</html>