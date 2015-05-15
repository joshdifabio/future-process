<?php
namespace FutureProcess;

use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 *
 * @internal
 */
class Process
{
    const STATUS_PENDING  = 0;
    const STATUS_RUNNING  = 1;
    const STATUS_EXITED   = 2;
    const STATUS_ABORTED  = 3;
    const STATUS_ERROR    = 4;

    private static $defaultOptions;

    private $eventLoop;
    private $command;
    private $options;
    private $deferredStart;
    private $deferredExitCode;
    private $status = self::STATUS_PENDING;
    private $resource;
    private $pid;
    private $pipes;

    public function __construct(LoopInterface $eventLoop, $command, array $options)
    {
        $options = $this->prepareOptions($options);

        $this->deferredStart = new Deferred;
        $this->deferredExitCode = new Deferred;
        $this->eventLoop = $eventLoop;
        $this->command = $command;
        $this->options = $options;
        $this->pipes = new Pipes($eventLoop);
    }

    public function start()
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException('Process has already been started');
        }

        $this->resource = proc_open(
            $this->command,
            $this->options['io'],
            $pipeResources,
            $this->options['working_dir'],
            $this->options['environment']
        );

        $this->pipes->setResources($pipeResources);
        $this->status = FutureProcess::STATUS_RUNNING;

        if (false === $procStatus = proc_get_status($this->resource)) {
            throw new \RuntimeException('Failed to start process.');
        }

        $this->pid = $procStatus['pid'];
        $this->initTimer();
        $this->deferredStart->resolve($this);
    }

    /**
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @return Pipes
     */
    public function getPipes()
    {
        return $this->pipes;
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
     * @param \Exception|null $error
     * @param int|null $signal If null is passed, no signal will be sent to the process
     */
    public function abort(\Exception $error = null, $signal = 15)
    {
        if ($this->status === self::STATUS_RUNNING) {
            if (null !== $signal) {
                proc_terminate($this->resource, $signal);
            }
        } elseif ($this->status !== self::STATUS_PENDING) {
            return;
        }

        $this->doExit(self::STATUS_ABORTED, $error);
    }

    public function whenStarted()
    {
        return $this->deferredStart->promise();
    }

    public function whenExited()
    {
        return $this->deferredExitCode->promise();
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

    private function initTimer()
    {
        if (!$this->options['timeout']) {
            return;
        }

        $options = $this->options;
        $that = $this;

        $timer = $this->eventLoop->addTimer($options['timeout'], function () use ($that, $options) {
            $that->abort($options['timeout_error'], $options['timeout_signal']);
        });

        $this->whenExited()->then(array($timer, 'cancel'), array($timer, 'cancel'));
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

    private function doExit($status, $exitCodeOrException)
    {
        $this->status = $status;

        $this->pipes->close();

        if ($exitCodeOrException instanceof \Exception) {
            $this->deferredExitCode->reject($exitCodeOrException);
        } else {
            $this->deferredExitCode->resolve($exitCodeOrException);
        }
    }
}
