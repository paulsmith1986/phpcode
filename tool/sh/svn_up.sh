#!/usr/bin/expect --

if { $argc < 3 } {
    send_user "Usage: $argv0 <path> <username> <passwd>\n"
    exit 1
}

set path [lindex $argv 0]
set user [lindex $argv 1]
set passwd [lindex $argv 2]

set key_init "yes/no)"
set key_confirm "'yes'"
set conflict "Select:*"
set timeout 60

spawn svn up --force $path --username $user --password $passwd
while {1} {
    expect {
        "$key_confirm" {
            send "yes\r"
        }
	"$key_init" {
	    send "yes\r"
	}
	"$conflict" {
	    send "mc\r"
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