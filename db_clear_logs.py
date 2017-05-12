#!/usr/bin/python
import datetime

from pymongo import MongoClient

c = MongoClient(
    'mongodb://admin:v9rJfFQWI4oGTYu8WqsQvzGor@localhost:27017/admin')
date = datetime.datetime.now() - datetime.timedelta(days=120)

for db_name in (n for n in c.database_names() if n not in ('local')):
    db = c[db_name]
    if 'LogEntry' in db.collection_names():
        collection = db.LogEntry
        collection.delete_many({'loggedAt': {'$lt': date}})
