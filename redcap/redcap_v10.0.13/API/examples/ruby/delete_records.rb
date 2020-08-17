#!/usr/bin/env ruby
require 'json'
require 'curl'
require './settings.rb'
include Settings

data = {
    :token => Settings::API_TOKEN,
    :action => 'delete',
    :content => 'record',
    'records[0]' => '1'
}
ch = Curl::Easy.http_post(
   Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str