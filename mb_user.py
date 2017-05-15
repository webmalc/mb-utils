#!/usr/bin/python
# -*- coding: utf-8 -*-
from pymongo import MongoClient
from termcolor import cprint

user_data = {
    "username":
    "mb",
    "usernameCanonical":
    "mb",
    "email":
    "support@maxi-booking.ru",
    "emailCanonical":
    "support@maxi-booking.ru",
    "enabled":
    True,
    "salt":
    "q5ut7s94hq84ko40ww40ko04wkkwows",
    "password":
    "XJnRok341+B0qMQs5oibW2OZCvWEtESFXZrcGvrA7hwE1Ff+PUm+KlAzAvnTfXk6uzlSY4E+orJ3vtti7DL8YQ==",
    "locked":
    False,
    "expired":
    False,
    "roles": ["ROLE_SUPER_ADMIN"],
    "credentialsExpired":
    False,
    "notifications":
    False,
    "taskNotify":
    False,
    "errors":
    True,
    "reports":
    False,
    "defaultNoticeDoc":
    False,
    "isEnabledWorkShift":
    False
}

c = MongoClient('mongodb://localhost:27017/admin')

for db_name in (n for n in c.database_names()
                if n not in ('local', 'admin', 'test', 'mbh_live')):
    cprint('process database {}'.format(db_name), 'white', 'on_blue')
    db = c[db_name]
    user = db.Users.find_one({'username': 'mb'})
    if not user:
        cprint('user not found. create new user.', 'red')
        result = db.Users.insert_one(user_data)
        if not getattr(result, 'inserted_id', None):
            cprint('error!!! user not created', 'red', attrs=['blink'])

    result = db.Users.update_one({'username': 'mb'}, {'$set': user_data})
    if not getattr(result, 'matched_count', None):
        cprint('error!!! user not updated', 'red', attrs=['blink'])

    cprint('ok', 'green')
