<?php

use Minishlink\WebPush\VAPID;

require 'vendor/autoload.php';
$keys = VAPID::createVapidKeys();
echo 'Public: ' . $keys['publicKey'] . PHP_EOL;
echo 'Private: ' . $keys['privateKey'] . PHP_EOL;
