<?php

// Experimental, do not use
die;

define('RESURS_BANK_CRON', true);

/** @var string $startPath */
$startPath = isset($argv[1]) ? $argv[1] : null;

/** @var string $resursPath */
$resursPath = isset($argv[2]) ? $argv[2] : null;

$startFile = $startPath . '/wp-blog-header.php';

if (!empty($startPath) && file_exists($startFile) && file_exists($resursPath . '/init.php')) {
    define('WP_USE_THEMES', false);

    /** @noinspection PhpIncludeInspection */
    require_once($startFile);

    /** @noinspection PhpIncludeInspection */
    require_once($resursPath);

    //echo Resursbank_Core::get_payment_methods('', true);
}
