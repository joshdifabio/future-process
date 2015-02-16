<?php
namespace FutureProcess;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class FutureStream
{
    private $waitFn;
    private $promise;
    private $resource;
    
    public function __construct($waitFn, $resourcePromise)
    {
        $this->waitFn = $waitFn;
        $resource = &$this->resource;
        $that = $this;
        $this->promise = $resourcePromise->then(function ($_resource) use (&$resource, $that) {
            $resource = $_resource;
            return $that;
        });
    }
    
    /**
     * @return string
     */
    public function getContents()
    {
        $this->wait();
        
        if ($this->resource) {
            return stream_get_contents($this->resource);
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
        call_user_func($this->waitFn, $timeout, $this);
        
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
}
