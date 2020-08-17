#!/usr/bin/env Rscript
source('config.R')
library(RCurl)
result <- postForm(
    api_url,
    token=api_token,
    action='delete',
    content='record',
    'records[0]'='1'
)
print(result)