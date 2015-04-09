# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- Optional $length parameter to readFromPipe() methods to avoid fully draining buffers.

### Changed
- Renamed readFromBuffer() and writeToBuffer() methods to readFromPipe() and writeToPipe() respectively.

## [0.2.0] - 2015-04-06
### Added
- Automatic buffering of child process i/o to prevent child processes becoming blocked by filled output buffers.
- Process timeout functionality.
- Support for PHP 7.0 and HHVM.

### Changed
- Replaced FutureProcess::kill() and detach() with abort().
- Shell::startProcess() args changed to (string $commandLine, array $options = []).
- Moved i/o functionality into new Pipes class to reduce complexity of FutureProcess class.

## 0.1.0 - 2015-03-01
### Added
- Shell class for parallel execution of command lines with automatic queueing of commands.
- FutureProcess and FutureResult classes for mixed asynchronous and synchronous interfaces to child processes.
- Support for PHP 5.3, 5.4, 5.5 and 5.6.

[unreleased]: https://github.com/joshdifabio/future-process/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/joshdifabio/future-process/compare/v0.1.0...v0.2.0
