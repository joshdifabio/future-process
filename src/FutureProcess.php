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
    private $pipes;
    private $buffers = array(
        'write' => array(),
        'read' => array(),
    );
    private $result;
    
    public function __construct(
        array $options,
        FutureValue $futureExitCode,
        FutureValue $queueSlot = null
    ) {
        $this->futureExitCode = $futureExitCode;
        $startFn = $this->getStartFn();
        $options[1] = $this->prepareDescriptorSpec(isset($options[1]) ? $options[1] : null);
        
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
     * @return null|resource
     */
    public function getPipe($descriptor)
    {
        $this->wait();
            
        return !array_key_exists($descriptor, $this->pipes) ? null : $this->pipes[$descriptor];
    }
    
    /**
     * @param int $descriptor
     * @param string $data
     */
    public function writeToBuffer($descriptor, $data)
    {
        if (!isset($this->buffers['write'][$descriptor])) {
            throw new \RuntimeException('No pipe exists for the specified descriptor.');
        }
        
        $this->buffers['write'][$descriptor] .= $data;
        $this->drainWriteBuffers();
    }
    
    /**
     * @param int $descriptor
     * @return string
     */
    public function readFromBuffer($descriptor)
    {
        $this->wait();
            
        if (!isset($this->buffers['read'][$descriptor])) {
            throw new \RuntimeException('No pipe exists for the specified descriptor.');
        }
        
        $this->fillReadBuffers();
        $data = $this->buffers['read'][$descriptor];
        $this->buffers['read'][$descriptor] = '';
        
        return $data;
    }
    
    /**
     * @param bool $refresh OPTIONAL
     * @return int One of the status constants defined in this class
     */
    public function getStatus($refresh = true)
    {
        if ($refresh && $this->status === self::STATUS_RUNNING) {
            $this->drainWriteBuffers();
            $this->fillReadBuffers();
            
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
        $pipes = &$this->pipes;
        $status = &$this->status;
        $that = $this;
        
        return function (array $options) use (&$resource, &$pipes, &$status, $that) {
            $options[0] = 'exec ' . $options[0];
            array_splice($options, 2, 0, array(&$pipes));
            $resource = call_user_func_array('proc_open', $options);
            $status = FutureProcess::STATUS_RUNNING;
            $that->getStatus(true);
        };
    }
    
    private function doExit($status, $exitCode = null)
    {
        $this->status = $status;
        $this->futureExitCode->resolve($exitCode);
        $this->fillReadBuffers();
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
    }
    
    private function prepareDescriptorSpec(array $spec = null)
    {
        if (null === $spec) {
            $spec = array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w'),
            );
        }
        
        foreach ($spec as $descriptor => $pipe) {
            if (!isset($pipe[0]) || $pipe[0] !== 'pipe') {
                continue;
            }
            
            $mode = $pipe[1];
            if (in_array($mode, array('r', 'r+', 'w+', 'a+', 'x+', 'c+'))) {
                // this pipe is readable on the child processes end & writable on our end
                $this->buffers['write'][$descriptor] = '';
            }
            
            if (in_array($mode, array('r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'))) {
                // this pipe is writable on the child processes end & readable on our end
                $this->buffers['read'][$descriptor] = '';
            }
        }
        
        return $spec;
    }
    
    private function drainWriteBuffers()
    {
        if ($this->status !== self::STATUS_RUNNING || !$this->buffers['write']) {
            return;
        }
        
        $read = null;
        $write = array_intersect_key($this->pipes, $this->buffers['write']);
        $except = null;
        
        if (false === stream_select($read, $write, $except, 0)) {
            throw new \RuntimeException('An error occurred when polling process pipes.');
        }
        
        foreach ($write as $pipe) {
            // prior PHP 5.4 the array passed to stream_select is modified and
            // lose key association, we have to find back the key
            $id = array_search($pipe, $this->pipes);
            while ($this->buffers['write'][$id]) {
                $written = fwrite($pipe, $this->buffers['write'][$id], 2 << 18); // write 512k
                if ($written > 0) {
                    $this->buffers['write'][$id] = (string)substr($this->buffers['write'][$id], $written);
                } else {
                    break;
                }
            }
        }
    }
    
    private function fillReadBuffers()
    {
        if ($this->status !== self::STATUS_RUNNING || !$this->buffers['read']) {
            return;
        }
        
        $read = array_intersect_key($this->pipes, $this->buffers['read']);
        $write = null;
        $except = null;
        
        if (false === stream_select($read, $write, $except, 0)) {
            throw new \RuntimeException('An error occurred when polling process pipes.');
        }
        
        foreach ($read as $pipe) {
            // prior PHP 5.4 the array passed to stream_select is modified and
            // lose key association, we have to find back the key
            $id = array_search($pipe, $this->pipes);
            while ($data = fread($pipe, 8192)) {
                $this->buffers['read'][$id] .= $data;
            }
        }
    }
}
