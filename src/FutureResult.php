<?php
namespace FutureProcess;

use React\Promise\RejectedPromise;

class FutureResult
{
    private $process;
    private $futureExitCode;
    private $promise;
    private $streams = array();
    private $error;
    
    public function __construct(
        FutureProcess $process,
        FutureValue $futureExitCode,
        array $exitCodeWhitelist,
        $exitCodesAreBlacklist
    ) {
        $this->process = $process;
        $this->futureExitCode = $futureExitCode;
        
        $error = &$this->error;
        $that = $this;
        $this->promise = $this->futureExitCode->then(
            function ($exitCode) use ($exitCodeWhitelist, $exitCodesAreBlacklist, &$error, $that) {
                if ($exitCodeWhitelist) {
                    if ($exitCodesAreBlacklist) {
                        if (in_array($exitCode, $exitCodeWhitelist)) {
                            $error = new \RuntimeException;
                            return new RejectedPromise($error);
                        }
                    } elseif (!in_array($exitCode, $exitCodeWhitelist)) {
                        $error = new \RuntimeException;
                        return new RejectedPromise($error);
                    }
                }

                return $that;
            }
        );
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
        
        if ($this->error) {
            throw $this->error;
        }
        
        return $this;
    }
    
    public function promise()
    {
        return $this->promise;
    }
    
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->promise->then($onFulfilled, $onError, $onProgress);
    }
}
