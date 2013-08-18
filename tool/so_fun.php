<?php
/**以int方式获取参数**/
define( 'GET_TYPE_INT', 1 );
/**以字符串方式获取参数**/
define( 'GET_TYPE_STRING', 2 );
/**字符串参数,并且检查有没有被屏蔽字**/
define( 'GET_TYPE_HEXIE', 3 );
/**字符串参数(用于写库),检查是否可以用作名字**/
define( 'GET_TYPE_NAME', 4 );
/**以数组方式获得参数**/
define( 'GET_TYPE_ARRAY', 5 );

/**AMF请求**/
define( 'DEF_AMF_REQUEST', 1 );
/**GM请求**/
define( 'DEF_GM_REQUEST', 3 );
/**普通网页请求**/
define( 'DEF_WEB_REQUEST', 2 );
/**SWf直接请求**/
define( 'DEF_SWF_REQUEST', 4 );
/**响应HTTP请求**/
define( 'SERVER_TYPE_HTTP', 1 );
/**响应SOCKET请求**/
define( 'SERVER_TYPE_SOCKET', 2 );
/**fpm主进程**/
define( 'SERVER_TYPE_FPM_MAIN', 3 );

/**二进制输出协议**/
define( 'DEF_OUT_DATA_BIN', 4 );
/**AMF格式输出协议**/
define( 'DEF_OUT_DATA_AMF', 3 );
/**JSON格式输出**/
define( 'DEF_OUT_DATA_JSON', 2 );
/**print_r格式输出**/
define( 'DEF_OUT_DATA_PRINT', 1 );

/**打印SQL调试**/
define( 'DEF_PRINT_SQL', 1 );
/**打印Memcached调试**/
define( 'DEF_PRINT_MEMCACHED', 2 );
/**打印IM和Outcallback调试**/
define( 'DEF_PRINT_IM_AND_OUTCALL', 4 );
/**打印错误消息调试**/
define( 'DEF_PRINT_ERROR', 8 );
/**保存错误日志**/
define( 'DEF_SAVE_ERROR_LOG', 16 );
/**开发环境**/
define( 'DEF_DEVELOPMENT', 32 );

/**战斗回调处理类型任务**/
define( 'TASK_TYPE_COMBAT', 1 );

/** 主进程 **/
define( 'FIRST_FPM_MAIN', 2 );
/** 子进程 **/
define( 'FIRST_FPM_SUB', 1 );
/** 进程状态: 空闲 **/
define( 'FIRST_FPM_STATUS_IDLE', 0 );
/** 进程状态: 工作 **/
define( 'FIRST_FPM_STATUS_WORK', 1 );
/** 发给单个用户 **/
define( 'PROTOCOL_TO_ROLE', 0 );
/** 发给某个频道 **/
define( 'PROTOCOL_TO_CHANNEL', 1 );
/** 发给全服 **/
define( 'PROTOCOL_TO_WORLD', 2 );
/** 发给多个用户 **/
define( 'PROTOCOL_TO_ROLE_LIST', 3 );
/** 发给im进程的协议 **/
define( 'PROTOCOL_TO_ADMIN', 4 );

/** first_poll 新来连接 **/
define( 'FIRST_NEW_CONNECTION', 1 );
/** first_poll 连接关闭 **/
define( 'FIRST_SOCKET_CLOSE', 2 );
/** first_poll socket数据到达 **/
define( 'FIRST_SOCKET_DATA', 3 );
/** first_poll 唤醒事件 **/
define( 'FIRST_EVENT_WAKEUP', 5 );
/** first_poll 倒计时结束 **/
define( 'FIRST_TIME_UP', 6 );
/** first_poll 捕捉到信号 **/
define( 'FIRST_SIGNAL', 7 );

/** 信号:终端退出 **/
define( 'FIRST_SIGHUP', 1 );
/** 信号:Ctrl+c 终止信号 **/
define( 'FIRST_SIGINT', 2 );
/** 信号:强制进程退出 **/
define( 'FIRST_SIGKILL', 9 );
/** 信号:管道破裂 **/
define( 'FIRST_SIGPIPE', 13 );
/** 信号:终止进程 **/
define( 'FIRST_SIGTERM', 15 );
/** 信号:自定义_1 **/
define( 'FIRST_SIGUSR1', 10 );
/** 信号:自定义_2 **/
define( 'FIRST_SIGUSR2', 12 );

/**
 * 将字符串反解为数组如 1:2,3:4
 * @param string $str 待反解的字符串
 * @param string $main_split 主分隔字符串
 * @param string $sub_split 次分隔字符串
 * @return array 返回反解好的字符串
 */
function str_to_array( $str, $main_split = ',', $sub_split = ':' )
{
}

/**
 * 检查字符串中有没有 ' 字符，如果有，就将字符串addslash，主要用于SQL语句过滤
 * @param string $sql_str 待检查过滤的字符串
 * @return string 过滤后的字符串
 */
function sql_addslash( $sql_str )
{
}

/**
 * 推送数据给客户端（无first_poll支持）
 * @param int $fd 连接id
 * @param int $pack_id 协议id
 * @param array $data 协议数据
 */
function first_im_push( $fd, $pack_id, $data = array() )
{
}

/**
 * 发送协议数据(有first_poll支持)
 * @param int $fd 连接id
 * @param int $pack_id 协议id
 * @param array $data 协议数据
 */
function first_send_pack( $fd, $pack_id, $data = array( ) )
{
}

/**
 * 发送数据(有first_poll支持)
 * @param int $fd 连接id
 * @param string $send_data 发送数据
 */
function first_send_data( $fd, $send_data )
{
}

/**
 * 连接im
 * @param int $server_id 服务器id
 * @param string $host im服务器ip
 * @param int $port im服务器端口
 * @param string $join_key 加入的密钥
 */
function first_im_connect( $server_id, $host, $port, $join_key )
{
}

/**
 * 检测与im是否连接,如果没有连接,尝试重连
 * @param int $fd 连接id
 */
function first_im_ping( $fd )
{
}

/**
 * 计算一场战斗数值
 * @param string $attack 进攻方的战斗数据包
 * @param string $defence 防守方的战斗数据包
 * @param int $max_time 最大时间
 * @param bool $need_total 是否需要统计
 */
function first_fight( $attack, $defence, $max_time, $need_total )
{
}

/**
 * 创建一个接受连接的服务器
 * @param string $host 主机绑定ip
 * @param int $por 主机绑定端口
 */
function first_host( $host, $port )
{
}

/**
 * 获取进程id
 * @return int 进程id
 */
function first_getpid()
{
}

/**
 * fork一个子进程
 */
function first_fork()
{
}

/**
 * 子进程脱离父进程
 */
function first_setsid()
{
}

/**
 * 给指定的进程发送信号
 * @param int $pid 进程id
 * @param int $signal 信号
 */
function first_kill( $pid, $signal = FIRST_SIGTERM )
{
}

/**
 * 创建倒计时
 * @return int 倒计时ID
 */
function first_timer_fd( )
{
}

/**
 * 创建唤醒FD
 * @return int 唤醒FD
 */
function first_event_fd()
{
}

/**
 * 设置倒计时事件
 * @param int $time_fd 时间FD
 * @param int $micro_time 结束时间
 * @return void
 */
function first_set_timeout( $time_fd, $micro_time )
{
}

/**
 * 关闭一个fd
 * @param int $fd 描述符
 */
function first_close_fd( $fd )
{
}

/**
 * 等待事件发生
 * @param int $timeout 超时时间
 * @return array 发生事件的fd数据
 */
function first_poll( $timeout )
{
}

/**
 * 从一个字符串中提取一个完整的字
 * @param string $str 字符串
 * @param int $pos 开始位置
 * @param bool $is_lower 是否将大写转换为小写
 */
function char_from_string( $str, $pos, $is_lower = false )
{
}

/**
 * 过滤字符串
 * @param string $str 原始字符串
 * @param array $arr 过滤规则
 * @return string 过滤后的字符串
 */
function filt_string( $str, $arr )
{
}

/**
 * 和蟹字符判断
 * @param string $str 原字符串
 * @param int $pos 检测位置
 * @param string $hexie_str 脏字串
 */
function hexie_cmp( $str, $pos, $hexie_str )
{
}

/**
 * 连接远程服务器
 * @param string $host 主机ip
 * @param int $port 主机端口
 * @param bool $add_poll 是否需要first_poll支持
 */
function first_socket_fd( $host, $port, $add_poll = true )
{
}

/**
 * 设置信号处理fd
 * @param array $signal_list 要捕获的信号列表
 * @return int 信号fd
 */
function first_signal_fd( $signal_list )
{
}

/**
 * 关闭fd
 * @param int $fd fd
 */
function first_im_close( $fd )
{
}

/**
 * 协议二进制打包
 * @param int $pack_id 协议id
 * @param array $data 数据
 * @return string 二进制字符串
 */
function first_pack( $pack_id, $data )
{
}

/**
 * 协议解包
 * @param string $bin_str 字符串
 * @return array 解包结果
 */
function first_unpack( $bin_str )
{
}

/**
 * msgpack 打包
 * @param string $pack_arr 源数据
 */
function msgpack_pack( $pack_arr )
{
}

/**
 * msgpack 解包
 * @param string $pack_str 打包串
 */
function msgpack_unpack( $pack_str )
{
}