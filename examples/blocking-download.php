<?php
require_once __DIR__ . '/../vendor/autoload.php';

use FutureProcess\Shell;

$shell = new Shell;

$url = 'https://raw.githubusercontent.com/joshdifabio/future-process/master/LICENSE';
$process = $shell->startProcess("wget -O - $url");

// this will block until the process starts
$process->wait();
echo "Downloading file...\n";

// this will block until the process exits
echo "File contents:\n{$process->getResult()->readFromBuffer(1)}\n";
