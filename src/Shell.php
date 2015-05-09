<?php
namespace FutureProcess;

use React\EventLoop\LoopInterface;
use Clue\React\Block\Blocker;
use React\Promise\Deferred;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class Shell
{
    private $eventLoop;
    private $blocker;
    private $processLimit;
    private $activeProcesses;
    private $queue;
    private $canStartProcessFn;
    private $handleQueueFn;
    private $resultObserver;

    public function __construct(LoopInterface $eventLoop, $processLimit = 10)
    {
        $this->eventLoop = $eventLoop;
        $this->blocker = new Blocker($eventLoop);
        $this->setProcessLimit($processLimit);
        $this->activeProcesses = new \SplObjectStorage;
        $this->queue = new \SplQueue;
        $this->canStartProcessFn = $this->createCanStartProcessFn();
        $this->handleQueueFn = $this->createHandleQueueFn();
        $this->resultObserver = function () {};
    }

    /**
     * @return FutureProcess
     */
    public function startProcess($command, array $options = array())
    {
        $handleQueue = $this->handleQueueFn;
        $activeProcesses = $this->activeProcesses;
        $resultObserver = &$this->resultObserver;
        $eventLoop = $this->eventLoop;
        $timer = null;

        $handleQueue();

        $process = $this->createProcess($command, $options);

        $process->then(function () use ($activeProcesses, $process, &$timer, $eventLoop) {
            $activeProcesses->attach($process);
            $timer = $eventLoop->addPeriodicTimer(0.1, array($process, 'getStatus'));
        });

        $onComplete = function () use (&$timer, $activeProcesses, $process, $handleQueue, &$resultObserver) {
            if ($timer) {
                $timer->cancel();
            }
            $activeProcesses->detach($process);
            $handleQueue();
            $resultObserver($process->getResult());
        };
        $process->getResult()->then($onComplete, $onComplete);

        return $process;
    }

    /**
     * @param null|int $processLimit
     */
    public function setProcessLimit($processLimit)
    {
        $this->processLimit = $processLimit;
    }

    /**
     * @param double $timeout OPTIONAL
     * @throws TimeoutException
     */
    public function wait($timeout = null)
    {
        $processes = $this->activeProcesses;
        $queue = $this->queue;
        
        if (!$processes->count() && !$queue->count()) {
            return;
        }

        $deferred = new Deferred;

        $afterResult = function () use ($deferred, $processes, $queue) {
            if (!$processes->count() && !$queue->count()) {
                $deferred->resolve();
            }
        };
        $this->resultObserver = function (FutureResult $result) use ($processes, $queue, $afterResult) {
            if (!$processes->count() && !$queue->count()) {
                // don't resolve yet as $result->then() might start other processes when it resolves
                $result->then($afterResult, $afterResult);
            }
        };

        $this->blocker->awaitOne($deferred->promise(), $timeout);
    }

    private function createProcess($command, array $options)
    {
        $canStartProcessFn = $this->canStartProcessFn;
        if (!$canStartProcessFn()) {
            $queueSlot = new Deferred;
            $process = new FutureProcess($this->eventLoop, $this->blocker, $command, $options, $queueSlot);
            $this->queue->enqueue($queueSlot);
        } else {
            $process = new FutureProcess($this->eventLoop, $this->blocker, $command, $options);
        }
        
        return $process;
    }

    private function createCanStartProcessFn()
    {
        $activeProcesses = $this->activeProcesses;
        $processLimit = &$this->processLimit;
        
        return function () use ($activeProcesses, &$processLimit) {
            return (!$processLimit || $activeProcesses->count() < $processLimit);
        };
    }

    private function createHandleQueueFn()
    {
        $queue = $this->queue;
        $canStartProcessFn = $this->canStartProcessFn;
        
        return function () use ($queue, $canStartProcessFn) {
            while ($queue->count() && $canStartProcessFn()) {
                $queue->dequeue()->resolve();
            }
        };
    }
}
