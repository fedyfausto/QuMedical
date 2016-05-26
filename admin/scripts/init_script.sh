#!/bin/bash

sudo /opt/orientdb/bin/console.sh  $(cat "./init_query.osql");
