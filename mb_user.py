#!/usr/bin/python
# -*- coding: utf-8 -*-
from pymongo import MongoClient

c = MongoClient('mongodb://localhost:27018/admin')

for db_name in (n for n in c.database_names()
                if n not in ('local', 'admin', 'test', 'mbh_live')):
    print("drop database {}".format(db_name))
