#!/usr/bin/php

<?php
require("config.inc.php");
require('client.inc.php');
require('dns.inc.php');

function check($key, $domain, $dns_server, $token, $output = true)
{
    $_res = false;
    $dns_query = new DNSQuery($dns_server, 53, 60, false, false, false);
    $dns_result = $dns_query->Query($key . '.' . $domain, "TXT");
    if (($dns_result === false) || ($dns_query->error != 0)) {
        echo $dns_query->lasterror;
        exit();
    }
    if ($dns_result->count > 0) {
        $_data = array();
        foreach ($dns_result->results as $res) {
            $_data [] = $key . '=' . $res->data;
            if ($res->data == $token)
                $_res = true;
            else
                $_res = false;
        }
        if ($output) {
            echo $dns_server . ": " . PHP_EOL;
            echo implode(PHP_EOL, $_data) . PHP_EOL;
        }
    } else {
        $_res = false;
        if ($output) {
            echo $dns_server . " not found " . $key . PHP_EOL;
        }
    }
    return $_res;
}

$domain = $argv[1];
$token = $argv[2];

Client::getInstance($soap_server_address, $domain, $login, $password);
Client::addRecord($token);
Client::logout();

$key = Client::getRecordKeyName();
$res = false;
echo PHP_EOL.'Token: ' . $token . PHP_EOL;
for ($_i = 1; $_i <= $try; $_i++) {
    echo('Try ' . $_i . '...' . PHP_EOL);
    foreach ($dns_servers as $k => $dns_server) {
        $res = $res ? $res : check($key, $domain, $dns_server, $token, false);
    }
    echo 'Result ' . ($res ? 'true' : 'false') . PHP_EOL;
    if ($res) {
        sleep(60);
        break;
    } else
        sleep($sleeptime);
}
?>
