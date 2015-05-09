<?php
namespace FutureProcess;

use React\EventLoop\LoopInterface;
use Clue\React\Block\Blocker;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use React\Stream\Stream;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class FutureProcess
{
    const STATUS_QUEUED   = 0;
    const STATUS_RUNNING  = 1;
    const STATUS_EXITED   = 2;
    const STATUS_ABORTED  = 3;
    const STATUS_ERROR    = 4;
    
    private static $defaultOptions;

    private $eventLoop;
    private $blocker;
    private $promise;
    private $options;
    private $futureExitCode;
    private $deferredStart;
    private $status;
    private $resource;
    private $pid;
    private $pipes;
    private $result;
    
    public function __construct(
        LoopInterface $eventLoop,
        Blocker $blocker,
        $command,
        array $options,
        Deferred $deferredStart = null
    ) {
        $options = $this->prepareOptions($options);

        $this->eventLoop = $eventLoop;
        $this->blocker = $blocker;
        $this->options = $options;
        $this->futureExitCode = new Deferred;
        $this->pipes = new Pipes($eventLoop);
        
        $startFn = $this->getStartFn();
        
        if ($this->deferredStart = $deferredStart) {
            $this->status = self::STATUS_QUEUED;
            $that = $this;
            $this->promise = $deferredStart->promise()->then(
                function () use ($startFn, $command, $options, $that) {
                    $startFn($command, $options);
                    return $that;
                },
                function (\Exception $e) use ($that) {
                    $that->abort($e);
                    throw $e;
                }
            );
        } else {
            $startFn($command, $options);
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
     * @return Stream
     */
    public function getPipe($descriptor)
    {
        $this->wait();
            
        return $this->pipes->getPipe($descriptor);
    }
    
    /**
     * @param bool $refresh OPTIONAL
     * @return int One of the status constants defined in this class
     */
    public function getStatus($refresh = true)
    {
        if ($refresh && $this->status === self::STATUS_RUNNING) {
            $this->refreshStatus();
        }
        
        return $this->status;
    }
    
    /**
     * @return FutureResult
     */
    public function getResult()
    {
        if (is_null($this->result)) {
            $this->result = new FutureResult($this->blocker, $this->futureExitCode, $this->pipes);
        }
        
        return $this->result;
    }
    
    /**
     * @param \Exception|null $error
     * @param int|null $signal If null is passed, no signal will be sent to the process
     */
    public function abort(\Exception $error = null, $signal = 15)
    {
        if ($this->status === self::STATUS_RUNNING) {
            if (null !== $signal) {
                proc_terminate($this->resource, $signal);
            }
        } elseif ($this->status === self::STATUS_QUEUED) {
            if ($error) {
                $this->deferredStart->reject($error);
            } else {
                $this->deferredStart->resolve();
            }
        } else {
            return;
        }
        
        $this->doExit(self::STATUS_ABORTED, $error);
    }
    
    /**
     * Wait for the process to start
     * 
     * @param double $timeout OPTIONAL
     * @return static
     */
    public function wait($timeout = null)
    {
        if ($this->deferredStart) {
            $this->blocker->awaitOne($this->deferredStart->promise(), $timeout);
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
     * @return PromiseInterface
     */
    public function then($onFulfilled = null, $onError = null)
    {
        return $this->promise->then($onFulfilled, $onError);
    }
    
    private function refreshStatus()
    {
        if (false === $status = proc_get_status($this->resource)) {
            $this->doExit(self::STATUS_ERROR, new \RuntimeException('An unknown error occurred.'));
        } elseif (!$status['running']) {
            $exitCode = (-1 == $status['exitcode'] ? null : $status['exitcode']);
            $this->doExit(self::STATUS_EXITED, $exitCode);
        }
        
        return $status;
    }
    
    private function getInitTimerFn()
    {
        if (!$this->options['timeout']) {
            return function () {};
        }

        $options = $this->options;
        $eventLoop = $this->eventLoop;
        $that = $this;
        $futureExitCode = $this->futureExitCode;

        return function () use ($options, $eventLoop, $that, $futureExitCode) {
            $timer = $eventLoop->addTimer($options['timeout'], function () use ($that, $options) {
                $that->abort($options['timeout_error'], $options['timeout_signal']);
            });
            $futureExitCode->promise()->then(array($timer, 'cancel'), array($timer, 'cancel'));
        };
    }
    
    private static function prepareOptions(array $options)
    {
        return array_merge(
            self::getDefaultOptions(),
            $options
        );
    }
    
    private static function getDefaultOptions()
    {
        if (is_null(self::$defaultOptions)) {
            self::$defaultOptions = array(
                'io' => array(
                    0 => array('pipe', 'r'),
                    1 => array('pipe', 'w'),
                    2 => array('pipe', 'w'),
                ),
                'working_dir' => null,
                'environment' => null,
                'timeout' => null,
                'timeout_signal' => 15,
                'timeout_error' => new \RuntimeException('The process exceeded its time limit and was aborted.'),
            );
        }
        
        return self::$defaultOptions;
    }
    
    private function getStartFn()
    {
        $procResource = &$this->resource;
        $pipes = $this->pipes;
        $status = &$this->status;
        $pid = &$this->pid;
        $initTimer = $this->getInitTimerFn();

        return function ($command, array $options) use (&$procResource, $pipes, &$status, &$pid, $initTimer) {
            $procResource = proc_open(
                $command,
                $options['io'],
                $pipeResources,
                $options['working_dir'],
                $options['environment']
            );
            $pipes->setResources($pipeResources);
            $status = FutureProcess::STATUS_RUNNING;
            if (false === $procStatus = proc_get_status($procResource)) {
                throw new \RuntimeException('Failed to start process.');
            }
            $pid = $procStatus['pid'];
            $initTimer();
        };
    }
    
    private function doExit($status, $exitCodeOrException)
    {
        $this->status = $status;
        
        $this->pipes->close();
        
        if ($exitCodeOrException instanceof \Exception) {
            $this->futureExitCode->reject($exitCodeOrException);
        } else {
            $this->futureExitCode->resolve($exitCodeOrException);
        }
    }
}
