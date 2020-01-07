#!/bin/sh

#nohup /usr/local/php/bin/php /root/monitor.php > /dev/null &

nohup /usr/local/php/bin/php /root/msgserver.php > /dev/null &
sleep 2s
nohup /usr/local/php/bin/php /root/eventsocket.php CHANNEL_CREATE >> /root/logs/c.log &
nohup /usr/local/php/bin/php /root/eventsocket.php CHANNEL_HANGUP >> /root/logs/h.log &
nohup /usr/local/php/bin/php /root/eventsocket.php CHANNEL_ANSWER >> /root/logs/a.log &
nohup /usr/local/php/bin/php /root/eventsocket.php CHANNEL_HANGUP_COMPLETE >> /root/logs/hc.log &

nohup /usr/local/php/bin/php /root/pool.php 1 > /dev/null &
nohup /usr/local/php/bin/php /root/pool.php 2 > /dev/null &
nohup /usr/local/php/bin/php /root/pool.php 3 > /dev/null &
nohup /usr/local/php/bin/php /root/pool.php 4 > /dev/null &
nohup /usr/local/php/bin/php /root/pool.php 5 > /dev/null &
nohup /usr/local/php/bin/php /root/pool.php 6 > /dev/null &
nohup /usr/local/php/bin/php /root/pool.php 7 > /dev/null &
nohup /usr/local/php/bin/php /root/pool.php 8 > /dev/null &
#nohup /usr/local/php/bin/php /root/pool.php debug > /dev/null &

nohup java -jar /root/erp.jar event > /dev/null &
nohup java -jar /root/erp.jar result > /dev/null &

