#!/bin/bash

set -e

cd $HOME/Bots/MySqlBestandschecker
echo Bestandscheck

env nice ./runMySqlBestandschecker.sh
