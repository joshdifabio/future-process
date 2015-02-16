<?php
namespace FutureProcess;

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
        $shell = new Shell;
        $command = "{$this->phpExecutablePath} -r \"echo 'Hello World';\"";
        $result = $shell->startProcess($command)->getResult()->wait(2);
        
        $this->assertSame(0, $result->getExitCode(), $result->getStreamContents(2));
        $this->assertSame('Hello World', $result->getStreamContents(1));
    }
    
    /**
     * @expectedException \RuntimeException
     */
    public function testWaitError()
    {
        $shell = new Shell;
        $command = "{$this->phpExecutablePath} -r \"echo 'This will error!'\"";
        $shell->startProcess($command)->getResult(0)->wait(2);
    }
    
    public function testPromiseError()
    {
        $error = null;
        
        $shell = new Shell;
        $command = "{$this->phpExecutablePath} -r \"echo 'This will error!'\"";
        $shell->startProcess($command)->getResult(0)->then(null, function ($_error) use (&$error) {
            $error = $_error;
        });
        
        $shell->run();
        
        $this->assertTrue($error instanceof \RuntimeException);
    }
    
    public function testFailedResultStream()
    {
        $error = null;
        
        $shell = new Shell;
        $command = "{$this->phpExecutablePath} -r \"echo 'This will error!'\"";
        $shell->startProcess($command)->getResult(0)->getStream(1)->then(null, function ($_error) use (&$error) {
            $error = $_error;
        });
        
        $shell->run();
        
        $this->assertTrue($error instanceof \RuntimeException);
    }
    
    public function testExecuteCommandWithTimeout()
    {
        $shell = new Shell;
        $command = $this->phpSleepCommand(0.1);
        
        $startTime = microtime(true);
        try {
            $shell->startProcess($command)->getResult()->wait(0.05);
            $this->fail('Expected TimeoutException was not thrown');
        } catch (TimeoutException $e) {
            $runTime = microtime(true) - $startTime;
            $this->assertGreaterThanOrEqual(0.05, $runTime);
        }
        
        $result = $shell->startProcess($command)->getResult()->wait(0.2);
        $this->assertSame(0, $result->getExitCode());
    }
    
    public function testQueue()
    {
        $shell = new Shell;
        $shell->setProcessLimit(2);
        
        $process1 = $shell->startProcess($this->phpSleepCommand(0.5));
        $process2 = $shell->startProcess($this->phpSleepCommand(0.5));
        $process3 = $shell->startProcess($this->phpSleepCommand(0.5));
        
        usleep(100000);
        
        $this->assertSame(FutureProcess::STATUS_RUNNING, $process1->getStatus());
        $this->assertSame(FutureProcess::STATUS_RUNNING, $process2->getStatus());
        $this->assertSame(FutureProcess::STATUS_QUEUED, $process3->getStatus());
        
        $this->assertSame(FutureProcess::STATUS_RUNNING, $process3->wait(0.5)->getStatus());
    }
    
    public function testGetPid()
    {
        $shell = new Shell;
        
        $process = $shell->startProcess("{$this->phpExecutablePath} -r \"echo getmypid();\"");
        
        $reportedPid = $process->getPid();
        
        $actualPid = (int)stream_get_contents($process->getResult()->getStream(1)->getResource());
        
        $this->assertSame($actualPid, $reportedPid);
    }
    
    private function phpSleepCommand($seconds)
    {
        $microSeconds = $seconds * 1000000;
        
        return "{$this->phpExecutablePath} -r \"usleep($microSeconds);\"";
    }
}
