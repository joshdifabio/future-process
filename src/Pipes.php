<?php
namespace FutureProcess;

use React\EventLoop\LoopInterface;
use React\Stream\Stream;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class Pipes
{
    private $eventLoop;
    private $collectData;
    private $pipes = array();
    private $data = array();

    public function __construct(LoopInterface $eventLoop, $collectData = true)
    {
        $this->eventLoop = $eventLoop;
        $this->collectData = $collectData;
    }

    public function setResources(array $resources)
    {
        foreach ($resources as $descriptor => $resource) {
            $this->initPipe($descriptor, $resource);
        }
    }

    public function getPipe($descriptor)
    {
        if (!isset($this->pipes[$descriptor])) {
            throw new \RuntimeException('No pipe exists for the specified descriptor.');
        }

        return $this->pipes[$descriptor];
    }

    public function getData($descriptor)
    {
        if (!isset($this->pipes[$descriptor])) {
            throw new \RuntimeException('No pipe exists for the specified descriptor.');
        }

        if (!isset($this->data[$descriptor])) {
            throw new \RuntimeException('Data is not being collected for the specified descriptor.');
        }

        return $this->data[$descriptor];
    }

    public function close()
    {
        foreach ($this->pipes as $pipe) {
            $pipe->close();
        }
    }

    private function initPipe($descriptor, $resource)
    {
        $stream = new Stream($resource, $this->eventLoop);

        if ($this->collectData) {
            $dataArray = &$this->data;
            $dataArray[$descriptor] = '';
            $stream->on('data', function ($data) use (&$dataArray, $descriptor) {
                $dataArray[$descriptor] .= $data;
            });
        }

        $this->pipes[$descriptor] = $stream;
    }
}
