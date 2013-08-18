<?php
$GAME_INI = array(
	//是否开启防沉迷
	'is_fcm'				=> 0,
	//服务器开放标志 0:开放 -1:关闭(未知开放时间) 大于当前时间:开放时间
	'server_open'			=> 0,
	//屏蔽字库
	'dirty_word'			=> 'etc/data/dirty_word.txt',
	//cooki加密私钥
	'cookie_key'			=> 'q937hEaBv2uX2Lf5',
	//日志目录
	'log_path'				=> '/data/logs/first_log/php/',
	//时区设置
	'time_zone'				=> 'Asia/Shanghai',
	//fpm主进程id
	'fpm_pid_file'			=> ROOT_PATH .'bin/pid/fpm.pid',
	//平台帐号登录界面
	'login_url'				=> 'http://login.newgame.yile.com',
	//静态文件地址
	'static_url'			=> 'http://static.trunk.newgame.yile.com/'
);

//默认是进程类型
$SERVER_TYPE = SERVER_TYPE_SOCKET;
//请求类型
$REQUEST_TYPE = DEF_WEB_REQUEST;
//返回数据格式
$RESPONSE_TYPE = DEF_OUT_DATA_PRINT;
//调试级别
$DEBUG_LEVEL = DEF_SAVE_ERROR_LOG;
//fpm进程配置
$FPM_CONF = array(
	'max_fpm'		=> 2,			//最大进程个数
	'port'			=> 8000,		//端口号
);

//游戏服务器标志(唯一)
$GLOBALS[ 'game_server_id' ] = 1;