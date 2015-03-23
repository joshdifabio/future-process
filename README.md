# Future Process

[![Build Status](https://img.shields.io/travis/joshdifabio/future-process.svg?style=flat-square)](https://travis-ci.org/joshdifabio/future-process) [![Coveralls](https://img.shields.io/coveralls/joshdifabio/future-process.svg?style=flat-square)](https://coveralls.io/r/joshdifabio/future-process) [![Code Quality](https://img.shields.io/scrutinizer/g/joshdifabio/future-process.svg?style=flat-square)](https://scrutinizer-ci.com/g/joshdifabio/future-process/)

## Introduction

Future Process is object-oriented `proc_open` with an asynchronous API and automatic queueing of commands.

## Usage

```php
// we use Shell to start new processes
$shell = new \FutureProcess\Shell;

// run a maximum of 5 concurrent processes - additional ones will be queued
$shell->setProcessLimit(5);

// let's download this package's license file from GitHub using wget
$url = 'https://raw.githubusercontent.com/joshdifabio/future-process/master/LICENSE';
$process = $shell->startProcess("wget -O - $url");
```

### Non-blocking

We can consume the process output using [promises](https://github.com/reactphp/promise).

```php
// this will not block, even if the process is queued
$process->then(function ($process) {
    echo "Downloading file...\n";
});

// this will not block, even if the process is queued
$process->getResult()->then(function ($result) {
    echo "File contents:\n{$result->readFromBuffer(1)}\n";
});

// this will block until all processes have exited
$shell->wait();
```

### Blocking

We can also consume the process output synchronously.

```php
// this will block until the process starts
$process->wait();
echo "Downloading file...\n";

// this will block until the process exits
echo "File contents:\n{$process->getResult()->readFromBuffer(1)}\n";
```

## Installation

Install Future Process using [composer](https://getcomposer.org/).

`composer require joshdifabio/future-process`

## License

Future Process is released under the [MIT](https://github.com/joshdifabio/future-process/blob/master/LICENSE) license.
