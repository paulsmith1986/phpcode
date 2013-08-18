#!/usr/bin/expect --

if { $argc < 4 } {
    send_user "Usage: $argv0 <url> <path> <username> <passwd>\n"
    exit 1
}

set url [lindex $argv 0]
set path [lindex $argv 1]
set user [lindex $argv 2]
set passwd [lindex $argv 3]

set key_init "yes/no)"
set key_confirm "'yes'"
set timeout 60

spawn svn co $url $path --username $user --password $passwd
while {1} {
    expect {
        "$key_confirm" {
            send "yes\r"
        }
	"$key_init" {
	    send "yes\r"
	}
        eof {
            send_user "eof"
            break;
        }
        timeout {
            puts "timeout\n"
            break
        }
    }
}
exit 0