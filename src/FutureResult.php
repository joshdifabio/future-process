<?php
namespace FutureProcess;

use React\Promise\PromiseInterface;

class FutureResult
{
    private $process;
    private $futureExitCode;
    private $promise;
    private $streams = array();
    
    public function __construct(FutureProcess $process, FutureValue $futureExitCode)
    {
        $this->process = $process;
        $this->futureExitCode = $futureExitCode;
    }
    
    /**
     * @param int $descriptor
     * @return FutureStream
     */
    public function getStream($descriptor)
    {
        if (!isset($this->streams[$descriptor])) {
            $process = $this->process;
            $resourcePromise = $this->then(function () use ($process, $descriptor) {
                return $process->getStream($descriptor)->getResource();
            });

            $this->streams[$descriptor] = new FutureStream(array($this, 'wait'), $resourcePromise);
        }
        
        return $this->streams[$descriptor];
    }
    
    /**
     * @param int $descriptor
     * @return null|string
     */
    public function getStreamContents($descriptor)
    {
        return $this->getStream($descriptor)->getContents();
    }
    
    /**
     * @param int $descriptor
     * @return null|int
     */
    public function getExitCode()
    {
        $this->wait();
        
        return $this->futureExitCode->getValue();
    }
    
    /**
     * Wait for the process to end
     * 
     * @param double $timeout OPTIONAL
     * @return static
     */
    public function wait($timeout = null)
    {
        $this->futureExitCode->wait($timeout);
        
        return $this;
    }
    
    /**
     * @return PromiseInterface
     */
    public function promise()
    {
        if (!$this->promise) {
            $that = $this;
            $this->promise = $this->futureExitCode->then(function () use ($that) {
                return $that;
            });
        }
        
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
        return $this->promise()->then($onFulfilled, $onError, $onProgress);
    }
}
