#!/usr/bin/php

<?php
require("config.inc.php");
require('client.inc.php');

$domain = $argv[1];
$token = $argv[2];

Client::getInstance($soap_server_address, $domain, $login, $password);
Client::deleteRecordByKeyName(Client::getRecordKeyName(), false);
Client::logout();
?>
