#! /bin/sh
#config begin
source_path="/d/git/porsche-audit/public/uploads"
back_path="/home/zhanghua/pgbak/"
back_count=5
#config end
echo $(date +%Y-%m-%d\ %H:%M:%S)" backup begin"
 
date=$(date +%Y-%m-%d)
tar czf  ${back_path}fs_${date}.bak $source_path
scp -P 8208 ${back_path}fs_${date}.bak zhanghua@bak.bwing.com.cn:/d/posche_file/
echo $(date +%Y-%m-%d\ %H:%M:%S)" backup end"
#只保留最新的back_count个文件，删除旧的
save_file=`ls -lrt $back_path | tail -$back_count | awk '{print $NF}'`
cd $back_path && ls | grep -v "$save_file" | xargs rm -rf
echo $(date +%Y-%m-%d\ %H:%M:%S)" task end"
