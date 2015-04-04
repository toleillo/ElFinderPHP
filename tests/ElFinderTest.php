<?php

use FM\ElFinderPHP\ElFinder;

class ElFinderTest extends \PHPUnit_Framework_TestCase {

    protected $options;

    protected $volumes;

    protected $defaultVolume;

    protected $logger;

    public function setup()
    {
        @session_start();
        parent::setUp();
        $this->options = array(
            'roots' => array(
                array('driver' => 'FM\ElFinderPHP\Driver\ElFinderVolumeDriver')
            )
        );

        $this->defaultVolume = $this->getMockForAbstractClass('FM\ElFinderPHP\Driver\ElFinderVolumeDriver');
        $this->volumes = array($this->defaultVolume);

        $this->logger = $this->getMockBuilder('Monolog\Logger')->setConstructorArgs(array('Logger'))->getMock();
    }

    public function commands()
    {
        return array(
            array(
                'open', 'ls', 'tree', 'parents', 'tmb', 'file', 'size', 'mkdir', 'mkfile', 'rm', 'rename',
                'duplicate', 'paste', 'upload','get', 'put', 'archive' , 'extract', 'search', 'info', 'dim',
                'resize', 'netmount', 'utl', 'callback', 'pixlr')
        );
    }

    public function testLoadedAndSetDefault()
    {
        $ElFinder = new ElFinder($this->options, $this->volumes, $this->logger);
        $ElFinder->setDefault($this->defaultVolume);
        $this->assertTrue($ElFinder->isLoaded());
        $this->assertTrue($ElFinder->setDefault($this->defaultVolume));
    }

    public function testBindAndUnbind()
    {
        $ElFinder = new ElFinder($this->options, $this->volumes, $this->logger);
        $ElFinder->setDefault($this->defaultVolume);
        function foo() {
            return 'bar';
        }
        $this->assertInstanceOf('FM\ElFinderPHP\ElFinder', $ElFinder->bind('test','foo'));
        $this->assertSame(array('test' => array('foo')), $ElFinder->getListeners());
        $this->assertInstanceOf('FM\ElFinderPHP\ElFinder', $ElFinder->unbind('test','foo'));
        $this->assertSame(array('test' => array()), $ElFinder->getListeners());
    }

    public function testCommandArgsList()
    {
        $ElFinder = new ElFinder($this->options, $this->volumes, $this->logger);
        $ElFinder->setDefault($this->defaultVolume);
        $expected = array('target' => false, 'tree' => false, 'init' => false, 'mimes' => false);

        $this->assertEquals($expected, $ElFinder->commandArgsList('open'));
        $this->assertEquals(array(), $ElFinder->commandArgsList('unknown_cmd'));
    }

    /**
     * @dataProvider commands
     * @param $cmd
     */
    public function testCommandExists($cmd)
    {
        $ElFinder = new ElFinder($this->options, $this->volumes, $this->logger);
        $ElFinder->setDefault($this->defaultVolume);
        $this->AssertTrue($ElFinder->commandExists($cmd));
    }

    public function testError()
    {
        $ElFinder = new ElFinder($this->options, $this->volumes, $this->logger);
        $ElFinder->setDefault($this->defaultVolume);
        $this->assertSame(array($ElFinder::ERROR_ACCESS_DENIED),$ElFinder->error($ElFinder::ERROR_ACCESS_DENIED));
        $this->assertSame(array($ElFinder::ERROR_UNKNOWN), $ElFinder->error());
    }


    public function execProvider()
    {
        return array(
            array('open', array('target' => false, 'tree' => false, 'init' => false, 'mimes' => false), array('error' => array(ElFinder::ERROR_OPEN, '#', ElFinder::ERROR_DIR_NOT_FOUND))),
            array('not_open', array('target' => false, 'tree' => false, 'init' => false, 'mimes' => false), array('error' => array(ElFinder::ERROR_UNKNOWN_CMD))),
            array('ls',array('target' => '', 'mimes' => false), array('error' => array(ElFinder::ERROR_OPEN, '#')))
        );
    }

    /**
     * @dataProvider execProvider
     * @param $cmd
     * @param $args
     * @param $expected
     */
    public function testExecFail($cmd, $args, $expected)
    {
//        $this->markTestIncomplete('Require more conditions, or refactor exec()');
        $options = array(
            'roots' => array(
                array(
                    'driver' => 'FM\ElFinderPHP\Driver\ElFinderVolumeDriver',
                    'path' => __DIR__.'/Fixtures'
                )
            )
        );
        $ElFinder = new ElFinder($options, $this->volumes, $this->logger);
        $ElFinder->setDefault($this->defaultVolume);

        $this->assertSame($expected,$ElFinder->exec($cmd,$args));
    }

    public function testRealPath()
    {
        $ElFinder = new ElFinder($this->options, $this->volumes, $this->logger);
        $ElFinder->setDefault($this->defaultVolume);

        $this->assertFalse($ElFinder->realpath(''));

    }


}
