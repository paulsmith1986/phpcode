#!/bin/sh
if [ $# != 3 ]; then
    echo "usage: $0 <path> <usser> <passwd>\n"
    exit 1
fi

path_name="$1"
user="$2"
passwd="$3"

cd "$path_name"
svn ci -m "Auto commit by tool" --username "$user" --password "$passwd"
exit 0