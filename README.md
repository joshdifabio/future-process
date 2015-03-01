Future Process
==============

[![Build Status](https://img.shields.io/travis/joshdifabio/future-process.svg?style=flat)](https://travis-ci.org/joshdifabio/future-process) [![Coveralls](https://img.shields.io/coveralls/joshdifabio/future-process.svg?style=flat)](https://coveralls.io/r/joshdifabio/future-process) [![Code Quality](https://img.shields.io/scrutinizer/g/joshdifabio/future-process.svg?style=flat)](https://scrutinizer-ci.com/g/joshdifabio/future-process/)

Non-blocking usage
------------------

```php
use FutureProcess\Shell;
use FutureProcess\FutureProcess;
use FutureProcess\FutureResult;

$shell = new Shell

// run a max of 5 concurrent processes - additional ones will be queued
$shell->setProcessLimit(5);

$process = $shell->startProcess('wget -O - https://github.com/joshdifabio/future-process/blob/master/LICENSE');

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
```

Blocking usage
--------------

```php
use FutureProcess\Shell;

$shell = new Shell

// run a max of 5 concurrent processes - additional ones will be queued
$shell->setProcessLimit(5);

$process = $shell->startProcess('wget -O - https://github.com/joshdifabio/future-process/blob/master/LICENSE');

// this will block until the process starts or fails to start
$process->wait();
echo "Downloading file...\n";

// this will block until the process exits
echo "File contents:\n{$result->getStreamContents(1)}\n";
```

License
-------

Future Process is released under the [MIT](https://github.com/joshdifabio/future-process/blob/master/LICENSE) license.
