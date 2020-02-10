#!/usr/bin/env bash

echo "Restoring database from backup file."
./singleToMultiTrans.rb ~/ojs/db_backup/ojs_db_dump.sql | egrep -v 'SESSION.SQL_LOG_BIN|GTID_PURGED' | mysql --defaults-extra-file=~/.passwords/ojs_dba_pw.mysql 
