<?php
namespace FutureProcess;

use React\Promise\PromiseInterface;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class FutureStream
{
    private $waitFn;
    private $resourcePromise;
    private $promise;
    private $resource;
    
    public function __construct($waitForResourceFn, PromiseInterface $resourcePromise)
    {
        $this->waitFn = $waitForResourceFn;
        $this->resourcePromise = $resourcePromise;
    }
    
    /**
     * @return string
     */
    public function getContents()
    {
        if ($resource = $this->getResource()) {
            return stream_get_contents($resource);
        }
    }
    
    /**
     * @return resource
     */
    public function getResource()
    {
        $this->wait();
        
        return $this->resource;
    }
    
    /**
     * @param double $timeout OPTIONAL
     * @return static
     */
    public function wait($timeout = null)
    {
        $resource = call_user_func($this->waitFn, $timeout, $this);
        
        if (!$this->resource) {
            $this->resource = $resource;
        }
        
        return $this;
    }
    
    /**
     * @return PromiseInterface
     */
    public function promise()
    {
        if (!$this->promise) {
            $resource = &$this->resource;
            $that = $this;
            $this->promise = $this->resourcePromise->then(
                function ($_resource) use (&$resource, $that) {
                    $resource = $_resource;
                    return $that;
                }
            );
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
