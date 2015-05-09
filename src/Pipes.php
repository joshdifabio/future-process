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
    private $pipes = array();

    public function __construct(LoopInterface $eventLoop)
    {
        $this->eventLoop = $eventLoop;
    }

    public function setResources(array $resources)
    {
        foreach ($resources as $descriptor => $resource) {
            $this->pipes[$descriptor] = new Stream($resource, $this->eventLoop);
        }
    }

    public function getPipe($descriptor)
    {
        if (!isset($this->pipes[$descriptor])) {
            throw new \RuntimeException('No pipe exists for the specified descriptor.');
        }

        return $this->pipes[$descriptor];
    }
}
