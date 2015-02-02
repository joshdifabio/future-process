<?php
namespace Joshdifabio\ChildProcess;

use Symfony\Component\Process\PhpExecutableFinder;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    private $phpExecutablePath;
    
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        
        $finder = new PhpExecutableFinder;
        $this->phpExecutablePath = $finder->find();
    }
    
    public function testPHPHelloWorld()
    {
        $options = $this->getMockForAbstractClass('Joshdifabio\\ChildProcess\\ProcessOptionsInterface');
        
        $options->expects($this->atLeastOnce())
                ->method('getWorkingDirectory')
                ->will($this->returnValue(null));
        
        $options->expects($this->atLeastOnce())
                ->method('getEnvironment')
                ->will($this->returnValue(new Environment));
        
        $options->expects($this->atLeastOnce())
                ->method('getCommandLine')
                ->will($this->returnValue("{$this->phpExecutablePath} -r \"echo 'Hello World';\""));

        $start = microtime(true);
        $shell = new Shell;
        
        $result = $shell->startProcess($options)->getResult()->wait(2);
        
        $this->assertEquals(0, $result->getExitCode(), stream_get_contents($result->getStream(2)));
        $this->assertEquals('Hello World', stream_get_contents($result->getStream(1)));
    }
    
    public function testExecuteCommandWithTimeout()
    {
        $options = $this->getMockForAbstractClass('Joshdifabio\\ChildProcess\\ProcessOptionsInterface');
        
        $options->expects($this->atLeastOnce())
                ->method('getWorkingDirectory')
                ->will($this->returnValue(null));
        
        $options->expects($this->atLeastOnce())
                ->method('getEnvironment')
                ->will($this->returnValue(new Environment));
        
        $options->expects($this->atLeastOnce())
                ->method('getCommandLine')
                ->will($this->returnValue("{$this->phpExecutablePath} -r \"usleep(100000);\""));

        $shell = new Shell;
        
        $startTime = microtime(true);
        try {
            $shell->startProcess($options)->getResult()->wait(0.05);
            $this->fail('Expected TimeoutException was not thrown');
        } catch (TimeoutException $e) {
            $runTime = microtime(true) - $startTime;
            $this->assertGreaterThanOrEqual(0.05, $runTime);
        }
        
        $result = $shell->startProcess($options)->getResult()->wait(0.2);
        $this->assertEquals(0, $result->getExitCode());
    }
}
