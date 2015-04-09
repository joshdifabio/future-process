<?php
namespace FutureProcess;

use React\Promise\PromiseInterface;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class FutureResult
{
    private $pipes;
    private $futureExitCode;
    private $promise;
    
    public function __construct(Pipes $pipes, FutureValue $futureExitCode)
    {
        $this->pipes = $pipes;
        $this->futureExitCode = $futureExitCode;
    }
    
    /**
     * @param int $descriptor
     * @param int|null $length
     * @return string
     */
    public function readFromPipe($descriptor, $length = null)
    {
        $this->wait();
        
        return $this->pipes->readFromBuffer($descriptor, $length);
    }
    
    /**
     * @return null|int
     */
    public function getExitCode()
    {
        return $this->futureExitCode->wait();
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
