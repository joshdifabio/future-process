<?php
namespace Joshdifabio\FutureProcess;

use React\Promise\FulfilledPromise;

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
    private $streams;
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
    
    public function getPid()
    {
        $this->wait();
        
        return $this->pid;
    }
    
    public function getStream($descriptor)
    {
        $this->wait();
        
        return !array_key_exists($descriptor, $this->streams) ? null : $this->streams[$descriptor];
    }
    
    public function getStatus($refresh = true)
    {
        if ($refresh && $this->status === self::STATUS_RUNNING) {
            if (false === $status = proc_get_status($this->resource)) {
                $this->doExit(self::STATUS_UNKNOWN);
            } elseif (!$status['running']) {
                $exitCode = (-1 == $status['exitcode'] ? null : $status['exitcode']);
                $this->doExit(self::STATUS_EXITED, $exitCode);
            }
        }
        
        return $this->status;
    }
    
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
        
        return $this;
    }
    
    public function kill($signal = 15)
    {
        if ($this->status === self::STATUS_RUNNING) {
            proc_terminate($this->resource, $signal);
        }
        
        return $this;
    }
    
    /**
     * Wait for the process to start
     */
    public function wait($timeout = null)
    {
        if ($this->status === self::STATUS_QUEUED) {
            $this->queueSlot->wait($timeout);
        }
        
        return $this;
    }
    
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->promise->then($onFulfilled, $onError, $onProgress);
    }
    
    private function getStartFn()
    {
        $resource = &$this->resource;
        $streams = &$this->streams;
        $status = &$this->status;
        
        return function (array $options) use (&$resource, &$streams, &$status) {
            if (!isset($options[1])) {
                $options[1] = array(
                    0 => array('pipe', 'r'),
                    1 => array('pipe', 'w'),
                    2 => array('pipe', 'w'),
                );
            }
            
            array_splice($options, 2, 0, array(&$streams));
            $resource = call_user_func_array('proc_open', $options);
            $status = self::STATUS_RUNNING;
        };
    }
    
    private function doExit($status, $exitCode = null)
    {
        $this->status = $status;
        $this->futureExitCode->resolve($exitCode);
    }
}
