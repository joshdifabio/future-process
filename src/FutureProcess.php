<?php
namespace FutureProcess;

use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class FutureProcess
{
    const STATUS_QUEUED   = 0;
    const STATUS_RUNNING  = 1;
    const STATUS_EXITED   = 2;
    const STATUS_ABORTED  = 3;
    const STATUS_UNKNOWN  = 4;
    
    private static $defaultOptions;
    
    private $promise;
    private $options;
    private $futureExitCode;
    private $queueSlot;
    private $status;
    private $resource;
    private $startTime;
    private $pid;
    private $pipes;
    private $result;
    
    public function __construct(
        $command,
        array $options,
        FutureValue $futureExitCode,
        FutureValue $queueSlot = null
    ) {
        $options = $this->prepareOptions($options);
        
        $this->options = $options;
        $this->futureExitCode = $futureExitCode;
        $this->pipes = new Pipes($options['io']);
        
        $startFn = $this->getStartFn();
        
        if ($this->queueSlot = $queueSlot) {
            $this->status = self::STATUS_QUEUED;
            $that = $this;
            $this->promise = $queueSlot->then(
                function () use ($startFn, $command, $options, $that) {
                    $startFn($command, $options);
                    return $that;
                },
                function (\Exception $e) use ($that) {
                    $that->abort($e);
                    throw $e;
                }
            );
        } else {
            $startFn($command, $options);
            $this->promise = new FulfilledPromise($this);
        }
    }
    
    /**
     * @return int
     */
    public function getPid()
    {
        $this->wait();
        
        return $this->pid;
    }
    
    /**
     * @param int $descriptor
     * @return null|resource
     */
    public function getPipe($descriptor)
    {
        $this->wait();
            
        return $this->pipes->getResource($descriptor);
    }
    
    /**
     * @param int $descriptor
     * @param string $data
     */
    public function writeToBuffer($descriptor, $data)
    {
        $this->pipes->writeToBuffer($descriptor, $data);
    }
    
    /**
     * @param int $descriptor
     * @return string
     */
    public function readFromBuffer($descriptor)
    {
        $this->wait();

        return $this->pipes->readFromBuffer($descriptor);
    }
    
    /**
     * @param bool $refresh OPTIONAL
     * @return int One of the status constants defined in this class
     */
    public function getStatus($refresh = true)
    {
        if ($refresh && $this->status === self::STATUS_RUNNING) {
            $this->refreshStatus();
        }
        
        return $this->status;
    }
    
    /**
     * @return FutureResult
     */
    public function getResult()
    {
        if (is_null($this->result)) {
            $this->result = new FutureResult($this->pipes, $this->futureExitCode);
        }
        
        return $this->result;
    }
    
    /**
     * @param \Exception|null $error
     * @param int|null $signal If null is passed, no signal will be sent to the process
     */
    public function abort(\Exception $error = null, $signal = 15)
    {
        if ($this->status === self::STATUS_RUNNING) {
            if (null !== $signal) {
                proc_terminate($this->resource, $signal);
            }
        } elseif ($this->status === self::STATUS_QUEUED) {
            if ($error) {
                $this->queueSlot->reject($error);
            } else {
                $this->queueSlot->resolve();
            }
        } else {
            return;
        }
        
        $this->doExit(self::STATUS_ABORTED, $error);
    }
    
    /**
     * Wait for the process to start
     * 
     * @param double $timeout OPTIONAL
     * @return static
     */
    public function wait($timeout = null)
    {
        if ($this->queueSlot) {
            $this->queueSlot->wait($timeout);
        }
        
        return $this;
    }
    
    /**
     * @return PromiseInterface
     */
    public function promise()
    {
        return $this->promise;
    }
    
    /**
     * @param callable $onFulfilled
     * @param callable $onError
     * @param callable $onProgress
     * @return PromiseInterface
     */
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->promise->then($onFulfilled, $onError, $onProgress);
    }
    
    private function refreshStatus()
    {
        if (false === $status = proc_get_status($this->resource)) {
            $this->doExit(self::STATUS_UNKNOWN, new \RuntimeException('An unknown error occurred.'));
        } elseif (!$status['running']) {
            $exitCode = (-1 == $status['exitcode'] ? null : $status['exitcode']);
            $this->doExit(self::STATUS_EXITED, $exitCode);
        } elseif (!$this->hasExceededTimeLimit()) {
            $this->pipes->drainBuffers();
        }
        
        return $status;
    }
    
    private function hasExceededTimeLimit()
    {
        if ($this->options['timeout'] && microtime(true) > $this->startTime + $this->options['timeout']) {
            $this->abort(
                $this->options['timeout_error'],
                $this->options['timeout_signal']
            );
            
            return true;
        }
        
        return false;
    }
    
    private static function prepareOptions(array $options)
    {
        return array_merge(
            self::getDefaultOptions(),
            $options
        );
    }
    
    private static function getDefaultOptions()
    {
        if (is_null(self::$defaultOptions)) {
            self::$defaultOptions = array(
                'io' => array(
                    0 => array('pipe', 'r'),
                    1 => array('pipe', 'w'),
                    2 => array('pipe', 'w'),
                ),
                'working_dir' => null,
                'environment' => null,
                'timeout' => null,
                'timeout_signal' => 15,
                'timeout_error' => new \RuntimeException('The process exceeded its time limit and was aborted.'),
            );
        }
        
        return self::$defaultOptions;
    }
    
    private function getStartFn()
    {
        $procResource = &$this->resource;
        $pipes = $this->pipes;
        $status = &$this->status;
        $startTime = &$this->startTime;
        $pid = &$this->pid;
        
        return function ($command, array $options) use (&$procResource, $pipes, &$status, &$startTime, &$pid) {
            $procResource = proc_open(
                "exec $command",
                $options['io'],
                $pipeResources,
                $options['working_dir'],
                $options['environment']
            );
            $pipes->setResources($pipeResources);
            $startTime = microtime(true);
            $status = FutureProcess::STATUS_RUNNING;
            if (false === $procStatus = proc_get_status($procResource)) {
                throw new \RuntimeException('Failed to start process.');
            }
            $pid = $procStatus['pid'];
        };
    }
    
    private function doExit($status, $exitCodeOrException)
    {
        $this->status = $status;
        
        $this->pipes->close();
        
        if ($exitCodeOrException instanceof \Exception) {
            $this->futureExitCode->reject($exitCodeOrException);
        } else {
            $this->futureExitCode->resolve($exitCodeOrException);
        }
    }
}
