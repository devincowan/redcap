#!/usr/bin/env perl
use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';
my $data = {
    token => $config{api_token},
    action => 'delete',
    content => 'record',
    'records[0]' => '1'
};
my $ch = LWP::Curl->new();
my $content = $ch->post(
    $config{api_url},
    $data,
    $config{referer}
);
print $content;