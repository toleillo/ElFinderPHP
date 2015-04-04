<?php

use FM\ElFinderPHP\Connector\ElFinderConnector;
use FM\ElFinderPHP\ElFinder;

class ElFinderConnectorTest extends \PHPUnit_Framework_TestCase {

    protected $elfinder;

    protected $volumes;

    protected $options;

    protected $logger;

    protected $defaultVolume;

    public function setup()
    {
        @session_start();

        $this->options = array(
            'roots' => array(
                array(
                    'driver' => 'FM\ElFinderPHP\Driver\ElFinderVolumeDriver',
                    'path' => __DIR__.'/../../Fixtures'
                )
            )
        );

        $this->defaultVolume = $this->getMockBuilder('FM\ElFinderPHP\Driver\ElFinderVolumeDriver')
                                    ->setMethods(array('stat'))
                                    ->getMockForAbstractClass();

        $this->defaultVolume->expects($this->any())
            ->method('_normpath')
            ->will($this->returnValue(__DIR__));
        $stat =  array(
            'mime' => 'directory',
            'ts' => 1427534812,
            'read' => 1,
            'write' => 1,
            'size' => 0,
            'hash' => 'l1_Lw',
            'volumeid' => 'l1_',
            'name' => 'uploads',
            'locked' => 1,
            'dirs' => 1
        );
        $this->defaultVolume->expects($this->any())
            ->method('stat')
            ->willReturn($stat);
        $this->volumes = array($this->defaultVolume);

//        $this->elfinder = $this->getMockBuilder('FM\ElFinderPHP\ElFinder')->getMock();

        $this->logger = $this->getMockBuilder('Monolog\Logger')->setConstructorArgs(array('Logger'))->getMock();
    }

    private function getRequestMock($cmd = 'unknowncommand')
    {
        $requestMock = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->disableOriginalConstructor()->getMock();
        $requestMock->request  = $this->getMockBuilder('Symfony\Component\HttpFoundation\ParameterBag')->getMock();
        $requestMock->request
            ->expects($this->any())
            ->method('has')
            ->will($this->returnValue('true'));
        $requestMock->request
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValue($cmd));

        $requestMock->expects($this->any())->method('isMethod')->willReturn(true);

        return $requestMock;
    }

    public function testGetElfinder()
    {
        $connector = $this->getMockBuilder('FM\ElFinderPHP\Connector\ElFinderConnector')
            ->setConstructorArgs(array($this->options))
            ->setMethods(array('createVolumeDriver'))
            ->getMock();

        $connector->expects($this->once())
            ->method('createVolumeDriver')
            ->will($this->returnValue($this->defaultVolume));

        $this->assertInstanceOf('FM\ElFinderPHP\ElFinder', $connector->getElFinder());
    }

    public function testGetLog()
    {
        $connector = new ElFinderConnector($this->options);

        $this->assertInstanceOf('Monolog\Logger', $connector->getLog());
    }
    public function testRunJson()
    {
        $connector = $this->getMockBuilder('FM\ElFinderPHP\Connector\ElFinderConnector')
            ->setConstructorArgs(array($this->options))
            ->setMethods(array('createVolumeDriver'))
            ->getMock();

        $connector->expects($this->once())
            ->method('createVolumeDriver')
            ->will($this->returnValue($this->defaultVolume));

        $this->assertJsonStringEqualsJsonString(json_encode(array('error'=>array(ElFinder::ERROR_UNKNOWN_CMD))),$connector->run(true));
    }

    public function testRunWithoutJson()
    {
        $connector = $this->getMockBuilder('FM\ElFinderPHP\Connector\ElFinderConnector')
            ->setConstructorArgs(array($this->options))
            ->setMethods(array('createVolumeDriver'))
            ->getMock();

        $connector->expects($this->once())
            ->method('createVolumeDriver')
            ->will($this->returnValue($this->defaultVolume));
        $this->assertSame(array('error'=>array(ElFinder::ERROR_UNKNOWN_CMD)), $connector->run());
    }

    public function testGetLogger()
    {
        $connector  = new ElFinderConnector($this->options);

        $this->assertInstanceOf('Monolog\Logger', $connector->getLog());
    }

    public function testExecuteUnknownCommandTest()
    {
        $connector = $this->getMockBuilder('FM\ElFinderPHP\Connector\ElFinderConnector')
            ->setConstructorArgs(array($this->options))
            ->setMethods(array('createVolumeDriver'))
            ->getMock();

        $connector->expects($this->once())
            ->method('createVolumeDriver')
            ->will($this->returnValue($this->defaultVolume));

        $connector->getElFinder();

        $expected = array('error'=>array('errUnknownCmd'));

        $this->assertSame($expected, $connector->executeCommand($this->getRequestMock()));
    }

}
