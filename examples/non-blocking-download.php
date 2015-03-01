<?php
require_once __DIR__ . '/../vendor/autoload.php';

use FutureProcess\Shell;
use FutureProcess\FutureProcess;
use FutureProcess\FutureResult;

$shell = new Shell;

$url = 'https://raw.githubusercontent.com/joshdifabio/future-process/master/LICENSE';
$process = $shell->startProcess("wget -O - $url");

// this will not block, even if the process is queued
$process->then(function (FutureProcess $process) {
    echo "Downloading file...\n";
});

// this will not block, even if the process is queued
$process->getResult()->then(function (FutureResult $result) {
    echo "File contents:\n{$result->getStreamContents(1)}\n";
});

// this will block until all processes have exited
$shell->wait();
