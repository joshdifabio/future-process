<?php
namespace FutureProcess;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class Pipes
{
    private static $modesByType = array(
        'read' => array('r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'),
        'write' => array('r', 'r+', 'w+', 'a+', 'x+', 'c+'),
    );
    
    private $resources = array();
    private $resourcesByType = array(
        'read' => array(),
        'write' => array(),
    );
    private $buffers = array(
        'read' => array(),
        'write' => array(),
    );
    
    public function __construct(array $descriptorSpec)
    {
        $pipes = array_filter($descriptorSpec, function ($element) {
            return isset($element[0]) && $element[0] === 'pipe';
        });
        
        foreach (self::$modesByType as $type => $modes) {
            $matchedPipes = array_filter($pipes, function ($pipe) use ($modes) {
                return in_array($pipe[1], $modes);
            });
            
            $this->buffers[$type] = array_fill_keys(array_keys($matchedPipes), '');
        }
    }
    
    public function setResources(array $resources)
    {
        foreach (self::$modesByType as $type => $modes) {
            $this->resourcesByType[$type] = array_intersect_key($resources, $this->buffers[$type]);
        }
        
        $this->resources = $resources;
    }
    
    public function getResource($descriptor)
    {
        if (!isset($this->resources[$descriptor])) {
            throw new \RuntimeException('No pipe exists for the specified descriptor.');
        }
        
        return $this->resources[$descriptor];
    }
    
    public function readFromBuffer($descriptor)
    {
        if (!isset($this->buffers['read'][$descriptor])) {
            throw new \RuntimeException('No pipe exists for the specified descriptor.');
        }
        
        if ($readResources = $this->resourcesByType['read']) {
            $this->select($readResources, $writeResources);
            $this->drainProcessOutputBuffers($readResources);
        }
        
        $data = $this->buffers['read'][$descriptor];
        $this->buffers['read'][$descriptor] = '';
        
        return $data;
    }
    
    public function writeToBuffer($descriptor, $data)
    {
        if (!isset($this->buffers['write'][$descriptor])) {
            throw new \RuntimeException('No pipe exists for the specified descriptor.');
        }
        
        $this->buffers['write'][$descriptor] .= $data;
        
        if ($writeResources = $this->resourcesByType['write']) {
            $this->select($readResources, $writeResources);
            $this->drainWriteBuffers($writeResources);
        }
    }
    
    public function drainBuffers()
    {
        $readResources = $this->resourcesByType['read'];
        $writeResources = $this->resourcesByType['write'];
        $this->select($readResources, $writeResources);
        $this->drainWriteBuffers($writeResources);
        $this->drainProcessOutputBuffers($readResources);
    }
    
    public function close()
    {
        if ($readResources = $this->resourcesByType['read']) {
            $this->select($readResources, $writeResources);
            $this->drainProcessOutputBuffers($readResources);
        }
        
        foreach ($this->resources as $descriptor => $resource) {
            fclose($resource);
            unset($this->resources[$descriptor]);
            unset($this->resourcesByType['read'][$descriptor]);
            unset($this->resourcesByType['write'][$descriptor]);
        }
    }
    
    private function drainProcessOutputBuffers(array $resources)
    {
        foreach ($resources as $descriptor => $resource) {
            while ($data = fread($resource, 8192)) {
                $this->buffers['read'][$descriptor] .= $data;
            }
        }
    }
    
    private function drainWriteBuffers(array $resources)
    {
        foreach ($resources as $descriptor => $resource) {
            $descriptor = array_search($resource, $this->resourcesByType);
            while ($this->buffers['write'][$descriptor]) {
                $written = fwrite($resource, $this->buffers['write'][$descriptor], 2 << 18); // write 512k
                if ($written > 0) {
                    $this->buffers['write'][$descriptor] = (string)substr($this->buffers['write'][$descriptor], $written);
                } else {
                    break;
                }
            }
        }
    }
    
    private function select(&$readResources, &$writeResources)
    {
        if (false === stream_select($readResources, $writeResources, $except, 0)) {
            throw new \RuntimeException('An error occurred when polling process pipes.');
        }
        
        // prior PHP 5.4 the array passed to stream_select is modified and
        // lose key association, we have to find back the key
        
        if (count($readResources)) {
            $readResources = array_intersect($this->resources, $readResources);
        }
        
        if (count($writeResources)) {
            $writeResources = array_intersect($this->resources, $writeResources);
        }
    }
}
