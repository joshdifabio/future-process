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
    const STATUS_DETACHED = 3;
    const STATUS_UNKNOWN  = 4;
    
    private $promise;
    private $futureExitCode;
    private $queueSlot;
    private $status;
    private $resource;
    private $pid;
    private $streamResources;
    private $streams = array();
    private $result;
    
    public function __construct(
        array $options,
        FutureValue $futureExitCode,
        FutureValue $queueSlot = null
    ) {
        $this->futureExitCode = $futureExitCode;
        $startFn = $this->getStartFn();
        
        if ($this->queueSlot = $queueSlot) {
            $this->status = self::STATUS_QUEUED;
            $that = $this;
            $this->promise = $queueSlot->then(function () use ($startFn, $options, $that) {
                $startFn($options);
                return $that;
            });
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
     * @return FutureStream
     */
    public function getStream($descriptor)
    {
        if (!isset($this->streams[$descriptor])) {
            $streamResources = &$this->streamResources;
            $getResourceFn = function () use (&$streamResources, $descriptor) {
                if (isset($streamResources[$descriptor])) {
                    return $streamResources[$descriptor];
                }
            };
            
            $that = $this;
            $this->streams[$descriptor] = new FutureStream(
                function ($timeout = null) use ($that, $getResourceFn) {
                    $that->wait($timeout);
                    return $getResourceFn();
                },
                $this->then($getResourceFn)
            );
        }
        
        return $this->streams[$descriptor];
    }
    
    /**
     * @param bool $refresh OPTIONAL
     * @return int One of the status constants defined in this class
     */
    public function getStatus($refresh = true)
    {
        if ($refresh && $this->status === self::STATUS_RUNNING) {
            if (false === $status = proc_get_status($this->resource)) {
                $this->doExit(self::STATUS_UNKNOWN);
            } else {
                if (is_null($this->pid)) {
                    $this->pid = $status['pid'];
                }
                
                if (!$status['running']) {
                    $exitCode = (-1 == $status['exitcode'] ? null : $status['exitcode']);
                    $this->doExit(self::STATUS_EXITED, $exitCode);
                }
            }
        }
        
        return $this->status;
    }
    
    /**
     * @return FutureResult
     */
    public function getResult()
    {
        if (is_null($this->result)) {
            $this->result = new FutureResult($this, $this->futureExitCode);
        }
        
        return $this->result;
    }
    
    public function detach()
    {
        if ($this->status === self::STATUS_RUNNING) {
            $this->doExit(self::STATUS_DETACHED);
        }
    }
    
    /**
     * @param int $signal
     */
    public function kill($signal = 15)
    {
        if ($this->status === self::STATUS_RUNNING) {
            proc_terminate($this->resource, $signal);
        }
    }
    
    /**
     * Wait for the process to start
     * 
     * @param double $timeout OPTIONAL
     * @return static
     */
    public function wait($timeout = null)
    {
        if ($this->status === self::STATUS_QUEUED) {
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
    
    private function getStartFn()
    {
        $resource = &$this->resource;
        $streamResources = &$this->streamResources;
        $status = &$this->status;
        $that = $this;
        
        return function (array $options) use (&$resource, &$streamResources, &$status, $that) {
            $options[0] = 'exec ' . $options[0];
            
            if (!isset($options[1])) {
                $options[1] = array(
                    0 => array('pipe', 'r'),
                    1 => array('pipe', 'w'),
                    2 => array('pipe', 'w'),
                );
            }
            
            array_splice($options, 2, 0, array(&$streamResources));
            $resource = call_user_func_array('proc_open', $options);
            $status = FutureProcess::STATUS_RUNNING;
            $that->getStatus(true);
        };
    }
    
    private function doExit($status, $exitCode = null)
    {
        $this->status = $status;
        $this->futureExitCode->resolve($exitCode);
    }
}
