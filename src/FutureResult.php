<?php
namespace FutureProcess;

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
    
    public function getStreamContents($descriptor)
    {
        return $this->getStream($descriptor)->getContents();
    }
    
    public function getExitCode()
    {
        $this->wait();
        
        return $this->futureExitCode->getValue();
    }
    
    /**
     * Wait for the process to end
     */
    public function wait($timeout = null)
    {
        $this->futureExitCode->wait($timeout);
        
        return $this;
    }
    
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
    
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->promise()->then($onFulfilled, $onError, $onProgress);
    }
}
