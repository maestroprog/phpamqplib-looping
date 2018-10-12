START=$(date +%s)
php rabbit-proxy.php &
sleep 2
php consumer.php
LEFT=$[START + 120 - $(date +%s)]
echo "waiting ${LEFT} seconds..."
sleep ${LEFT}
kill -9 %1
