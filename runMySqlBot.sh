#!/bin/bash
set -e

export REVO_BOT_START="`date -d "400 minutes ago" "+%Y%m%d%H%M%S" --utc`"
export REVO_BOT_END="`  date -d "180 minutes ago" "+%Y%m%d%H%M%S" --utc`"

echo `date`: Checke von $REVO_BOT_START bis $REVO_BOT_END

cd $HOME/Bots/MySqlCommonsWatcher
env nice php mysqlcommonswatcher.php

cd $HOME/Bots/MySqlBot
env nice php mysqlbot2.php
