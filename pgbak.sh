#! /bin/sh
#config begin
meta_host="127.0.0.1"
meta_user="freeswitch"
meta_databse="freeswitch"
meta_password="1qaz2wsx?"
meta_port="5432"
back_path="/home/zhanghua/pgbak/"
back_count=20
#config end
echo $(date +%Y-%m-%d\ %H:%M:%S)" pg_dump begin"
#备份数据库文件
date=$(date +%Y-%m-%d)
PGPASSWORD=${meta_password} pg_dump  -Ft -h ${meta_host} -U ${meta_user} -d ${meta_databse} > ${back_path}fs_${date}.bak
echo $(date +%Y-%m-%d\ %H:%M:%S)" pg_dump end"
scp -P 8208 ${back_path}fs_${date}.bak zhanghua@bak.bwing.com.cn:/d/posche_db/
#只保留最新的back_count个文件，删除旧的
save_file=`ls -lrt $back_path | tail -$back_count | awk '{print $NF}'`
cd $back_path && ls | grep -v "$save_file" | xargs rm -rf
echo $(date +%Y-%m-%d\ %H:%M:%S)" task end"