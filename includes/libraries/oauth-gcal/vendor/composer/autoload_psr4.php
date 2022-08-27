<?php

namespace Tyche\BKAP;

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'phpseclib3\\' => array($vendorDir . '/phpseclib/phpseclib/phpseclib'),
    'Psr\\Log\\' => array($vendorDir . '/psr/log/Psr/Log'),
    'Psr\\Http\\Message\\' => array($vendorDir . '/psr/http-message/src'),
    'Psr\\Http\\Client\\' => array($vendorDir . '/psr/http-client/src'),
    'Psr\\Cache\\' => array($vendorDir . '/psr/cache/src'),
    'ParagonIE\\ConstantTime\\' => array($vendorDir . '/paragonie/constant_time_encoding/src'),
    'Monolog\\' => array($vendorDir . '/monolog/monolog/src/Monolog'),
    'Google\\Auth\\' => array($vendorDir . '/google/auth/src'),
    'Google\\' => array($vendorDir . '/google/apiclient/src'),
    'Firebase\\JWT\\' => array($vendorDir . '/firebase/php-jwt/src'),
    'BKAPGuzzleHttp\\Psr7\\' => array($vendorDir . '/guzzlehttp/psr7/src'),
    'BKAPGuzzleHttp\\Promise\\' => array($vendorDir . '/guzzlehttp/promises/src'),
    'BKAPGuzzleHttp\\' => array($vendorDir . '/guzzlehttp/guzzle/src'),
);
