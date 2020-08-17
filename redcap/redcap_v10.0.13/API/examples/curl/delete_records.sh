#!/bin/sh

. ./config

DATA="token=7C3EEBE7B54F68807FF005DD37FE0DFA&action=delete&content=record&records[0]=1"
CURL=`which curl`
$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL
