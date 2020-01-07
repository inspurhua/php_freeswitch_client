#!/bin/bash

MY_CUR_DATE_DIR="/recordings/"$(date '+%Y/%m/%d')
echo $MY_CUR_DATE_DIR

if [ ! -d $MY_CUR_DATE_DIR ];then
    mkdir -p "$MY_CUR_DATE_DIR"
    chmod -R 2777 "$MY_CUR_DATE_DIR"
else
    chmod -R 2777 "$MY_CUR_DATE_DIR"
fi

MY_CUR_DATE_DIR="/recordings/voicemail/"$(date '+%Y/%m/%d')
echo $MY_CUR_DATE_DIR

if [ ! -d $MY_CUR_DATE_DIR ];then
    mkdir -p "$MY_CUR_DATE_DIR"
    chmod -R 2777 "$MY_CUR_DATE_DIR"
else
    chmod -R 2777 "$MY_CUR_DATE_DIR"
fi

sync && echo 1 > /proc/sys/vm/drop_caches
sync && echo 2 > /proc/sys/vm/drop_caches
sync && echo 3 > /proc/sys/vm/drop_caches
