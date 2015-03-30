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
    
    private static $defaultDescriptorSpec = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    
    private $promise;
    private $timeLimit;
    private $timeoutSignal;
    private $futureExitCode;
    private $queueSlot;
    private $status;
    private $resource;
    private $startTime;
    private $pid;
    private $pipes;
    private $result;
    
    public function __construct(
        array $options,
        $timeLimit,
        $timeoutSignal,
        FutureValue $futureExitCode,
        FutureValue $queueSlot = null
    ) {
        $this->timeLimit = $timeLimit;
        $this->timeoutSignal = $timeoutSignal;
        $this->futureExitCode = $futureExitCode;
        
        if (!isset($options[1])) {
            $options[1] = self::$defaultDescriptorSpec;
        }
        
        $this->pipes = new Pipes($options[1]);
        
        $startFn = $this->getStartFn();
        
        if ($this->queueSlot = $queueSlot) {
            $this->status = self::STATUS_QUEUED;
            $that = $this;
            $this->promise = $queueSlot->then(
                function () use ($startFn, $options, $that) {
                    $startFn($options);
                    return $that;
                },
                function (\Exception $e) use ($that) {
                    $that->abort($e);
                    throw $e;
                }
            );
        } else {
            $startFn($options);
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
        if ($this->timeLimit && microtime(true) > $this->startTime + $this->timeLimit) {
            $this->abort(
                new ProcessAbortedException(
                    $this,
                    sprintf('The process exceeded it\'s maximum execution time of %fs and was aborted.', $this->timeLimit)
                ),
                $this->timeoutSignal
            );
            
            return true;
        }
        
        return false;
    }
    
    private function getStartFn()
    {
        $procResource = &$this->resource;
        $pipes = $this->pipes;
        $status = &$this->status;
        $startTime = &$this->startTime;
        $pid = &$this->pid;
        
        return function (array $options) use (&$procResource, $pipes, &$status, &$startTime, &$pid) {
            $options[0] = 'exec ' . $options[0];
            $pipeResources = null;
            array_splice($options, 2, 0, array(&$pipeResources));
            $procResource = call_user_func_array('proc_open', $options);
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
