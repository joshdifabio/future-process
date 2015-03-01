# Future Process

[![Build Status](https://img.shields.io/travis/joshdifabio/future-process.svg?style=flat-square)](https://travis-ci.org/joshdifabio/future-process) [![Coveralls](https://img.shields.io/coveralls/joshdifabio/future-process.svg?style=flat-square)](https://coveralls.io/r/joshdifabio/future-process) [![Code Quality](https://img.shields.io/scrutinizer/g/joshdifabio/future-process.svg?style=flat-square)](https://scrutinizer-ci.com/g/joshdifabio/future-process/)

## Introduction

This library provides an asynchronous implementation of PHP's `proc_open` with magic queueing of commands.

## Usage

```php
$shell = new \FutureProcess\Shell;

// run a maximum of 5 concurrent processes - additional ones will be queued
$shell->setProcessLimit(5);

// let's download this package's license file from GitHub using wget
$url = 'https://raw.githubusercontent.com/joshdifabio/future-process/master/LICENSE';
$process = $shell->startProcess("wget -O - $url");
```

### Non-blocking

```php
// this will not block, even if the process is queued
$process->then(function ($process) {
    echo "Downloading file...\n";
});

// this will not block, even if the process is queued
$process->getResult()->then(function ($result) {
    echo "File contents:\n{$result->getStreamContents(1)}\n";
});

// this will block until all processes have exited
$shell->wait();
```

### Blocking

```php
// this will block until the process starts
$process->wait();
echo "Downloading file...\n";

// this will block until the process exits
echo "File contents:\n{$process->getResult()->getStreamContents(1)}\n";
```

## License

Future Process is released under the [MIT](https://github.com/joshdifabio/future-process/blob/master/LICENSE) license.
