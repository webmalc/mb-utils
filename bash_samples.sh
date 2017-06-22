#!/usr/bin/env bash
#Примеры скриптом для памяти

#Список включенный или выключенных сервисов, в данном случае  интересовали перечисленные
systemctl list-unit-files | grep -E -i -w nginx|fpm|rabbitmq|redis|mongod

#Массовая копия файлов для  файлов с разными названиями
cat amz2 | tee amz{3,4,5,6,7,8,9,10} >/dev/null

#Массовая замена  символов
for i in 14 15 16 17 18 19; do sub=$(echo $i | awk '{print substr($0,2)}'); rpl amz${sub} "amz${i}" amz${i}; done

for i in $(seq 2 10); do rpl "amz$i" "amz$(($i+10))" parameters_amz$i.yml; done;

#Переименование файлов изменяя цифры
for file in *; do old=$(echo $file|tr -dc '0-9');new=$(($old+10));mv $file $(echo $file | sed -e "s/${old}/${new}/g"); done

#Массовый cache clear, не работает в последних вендорах, возможно надо cache:clear --no-warmup и cache:warmup
# http://symfony.com/blog/new-in-symfony-3-3-deprecated-cache-clear-with-warmup
for i in app/config/clients/*; do MB_CLIENT=amz$(echo $i|tr -dc '0-9') bin/console cache:clear; done


