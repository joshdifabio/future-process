<?php
namespace FutureProcess;

use Clue\React\Block\Blocker;
use React\Promise\PromiseInterface;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class FutureResult
{
    private $blocker;
    private $futureExitCode;
    private $pipes;
    private $promise;
    
    public function __construct(Blocker $blocker, PromiseInterface $futureExitCode, Pipes $pipes)
    {
        $this->blocker = $blocker;
        $this->futureExitCode = $futureExitCode;
        $this->pipes = $pipes;
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
        return $this->blocker->awaitOne($this->futureExitCode);
    }
    
    /**
     * Wait for the process to end
     * 
     * @param double $timeout OPTIONAL
     * @return static
     */
    public function wait($timeout = null)
    {
        $this->blocker->awaitOne($this->futureExitCode, $timeout);
        
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
