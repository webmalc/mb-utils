#!/usr/bin/python
from pymongo import MongoClient

c = MongoClient('mongodb://localhost:27017/admin')

for db_name in (n for n in c.database_names()
                if n not in ('local', 'admin', 'test', 'mbh_live')):
    print("drop database {}".format(db_name))
    c.drop_database(db_name)
