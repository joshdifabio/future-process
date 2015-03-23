Future Process
==============

[![Build Status](https://img.shields.io/travis/joshdifabio/future-process.svg?style=flat-square)](https://travis-ci.org/joshdifabio/future-process) [![Coveralls](https://img.shields.io/coveralls/joshdifabio/future-process.svg?style=flat-square)](https://coveralls.io/r/joshdifabio/future-process) [![Code Quality](https://img.shields.io/scrutinizer/g/joshdifabio/future-process.svg?style=flat-square)](https://scrutinizer-ci.com/g/joshdifabio/future-process/)

Non-blocking usage
------------------

```php
use FutureProcess\Shell;
use FutureProcess\FutureProcess;
use FutureProcess\FutureResult;

$shell = new Shell;
// run a max of 5 concurrent processes - additional ones will be queued
$shell->setProcessLimit(5);

$url = 'https://raw.githubusercontent.com/joshdifabio/future-process/master/LICENSE';
$process = $shell->startProcess("wget -O - $url");

// this will not block, even if the process is queued
$process->then(function (FutureProcess $process) {
    echo "Downloading file...\n";
});

// this will not block, even if the process is queued
$process->getResult()->then(function (FutureResult $result) {
    echo "File contents:\n{$result->readFromBuffer(1)}\n";
});

// this will block until all processes have exited
$shell->wait();
```

Blocking usage
--------------

```php
use FutureProcess\Shell;

$shell = new Shell;
// run a max of 5 concurrent processes - additional ones will be queued
$shell->setProcessLimit(5);

$url = 'https://raw.githubusercontent.com/joshdifabio/future-process/master/LICENSE';
$process = $shell->startProcess("wget -O - $url");

// this will block until the process starts
$process->wait();
echo "Downloading file...\n";

// this will block until the process exits
echo "File contents:\n{$process->getResult()->readFromBuffer(1)}\n";
```

License
-------

Future Process is released under the [MIT](https://github.com/joshdifabio/future-process/blob/master/LICENSE) license.
