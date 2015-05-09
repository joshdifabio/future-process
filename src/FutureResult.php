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
     * @return string
     */
    public function getOutput($descriptor = 1)
    {
        $this->wait();
        
        return $this->pipes->getData($descriptor);
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
     * @return PromiseInterface
     */
    public function then($onFulfilled = null, $onError = null)
    {
        return $this->promise()->then($onFulfilled, $onError);
    }
}
