<?php
namespace FutureProcess;

class FutureResult
{
    private $process;
    private $futureExitCode;
    private $promise;
    
    public function __construct(FutureProcess $process, FutureValue $futureExitCode)
    {
        $this->process = $process;
        $this->futureExitCode = $futureExitCode;
        
        $that = $this;
        $this->promise = $this->futureExitCode->then(function () use ($that) {
            return $that;
        });
    }
    
    public function getStream($descriptor)
    {
        $this->wait();
        
        return $this->process->getStream($descriptor);
    }
    
    public function getStreamContents($descriptor)
    {
        $this->wait();
        
        if ($stream = $this->process->getStream($descriptor)) {
            return stream_get_contents($stream);
        }
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
    
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->promise->then($onFulfilled, $onError, $onProgress);
    }
}
