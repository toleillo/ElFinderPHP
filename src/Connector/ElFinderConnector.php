<?php

namespace FM\ElFinderPHP\Connector;

use FM\ElFinderPHP\Driver\ElFinderVolumeDriver;
use FM\ElFinderPHP\ElFinder;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;

/**
 * Default ElFinder connector
 *
 * @author Dmitry (dio) Levashov
 **/
class ElFinderConnector {

    /**
     * ElFinder instance
     *
     * @var ElFinder
     **/
    protected $ElFinder;

    /**
     * @var Logger
     * Monolog Logger
     */
    protected $log;

    /**
     * @var ElFinderVolumeDriver
     */
    protected $defaultVolume;

    /**
     * Options
     *
     * @var array
     **/
    protected $options = array();

    /**
     * @var array of ElFinderVolumeDriver
     */
    protected $volumes = array();

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string
     **/
    protected $header = 'Content-Type: application/json';

    /**
     * Constructor
     *
     * @param array $options
     * @param bool $debug
     */
    public function __construct(array $options, $debug = false)
    {
        $this->request = Request::createFromGlobals();
        $this->options = $options;
        if (!isset($this->log)) {
            $this->log = new Logger('ElFinder');
        }

        if ($debug) {
            $this->header = 'Content-Type: text/html; charset=utf-8';
        }
    }

    /**
     * @param $json
     * @return array
     */
    public function run($json = false)
    {
        $this->getElFinder();
        return $json ? json_encode($this->executeCommand($this->request)) : $this->executeCommand($this->request);
    }

    /**
     * @returns ElFinder
     */
    public function getElFinder()
    {
        if(empty($this->volumes)) {
            $this->setVolumes($this->mountVolumes($this->options));
        }

        if (!$this->ElFinder) {
            $ElFinder = new ElFinder(
                $this->options,
                $this->volumes,
                $this->getLogger());
            $this->setElFinder($ElFinder);

            $this->ElFinder->setDefault($this->getDefaultVolume());
        }

        return $this->ElFinder;
    }

    /**
     * @return Logger
     */
    protected function getLogger()
    {
        return $this->log;
    }

    /**
     * @param $ElFinder
     * @return $this
     */
    public function setElFinder($ElFinder)
    {
        $this->ElFinder = $ElFinder;
        return $this;
    }


    /**
     * @param $class
     * @return ElFinderVolumeDriver|boolean
     */
    protected function createVolumeDriver($class)
    {
        return class_exists($class) ? new $class : false;
    }

    /**
     * Mount volumes
     *
     * Instantiate corresponding driver class and
     * add it to the list of volumes.
     *
     * @param $options
     * @return array
     */
    protected function mountVolumes($options)
    {
        $volumes = array();
        foreach ($options['roots'] as $i => $o) {
            $class = (isset($o['driver']) ? $o['driver'] : '');
            if ($volume = $this->createVolumeDriver($class)) {
                try {
                    if ($volume->mount($o)) {
                        $id = $volume->id();
                        $volumes[$id] = $volume;
                        if (!$this->getDefaultVolume() && $volume->isReadable()) {
                            $this->setDefaultVolume($volume);
                        }
                    } else {
                        $this->log->addError('Driver "' . $class . '" : ' . implode(' ', $volume->error()));
                    }
                } catch (\Exception $e) {
                    $this->log->addError('Driver "'.$class.'" : '.$e->getMessage());
                }
            } else {
                $this->log->addError('Driver "'.$class.'" does not exists');
            }
        }

        return array_merge($this->volumes, $volumes);
    }

    /**
     * Execute ElFinder command and returns result
     *
     * @param Request $request
     * @return array
     * @author Dmitry (dio) Levashov
     */
    public function executeCommand(Request $request)
    {
        $isPost = $request->isMethod('POST');
        $src    = $request->isMethod('POST') ? $request->request : $request->query;
        if ($isPost && !$src && $rawPostData = @file_get_contents('php://input')) {
            // for support IE XDomainRequest()
            $parts = explode('&', $rawPostData);
            foreach($parts as $part) {
                list($key, $value) = array_pad(explode('=', $part), 2, '');
                $src->set($key, rawurldecode($value));
            }
            $request->request = $src;

        }
        $cmd  = $src->has('cmd') ? $src->get('cmd') : '';
        $args = array();
        if (!function_exists('json_encode')) {
            $error = $this->ElFinder->error(ElFinder::ERROR_CONF, ElFinder::ERROR_CONF_NO_JSON);
            return $this->output(
                array(
                    'error' => '{"error":["'.implode('","', $error).'"]}',
                    'raw' => true)
            );
        }

        if (!$this->ElFinder->isLoaded()) {
            $this->log->addError('Error mounting volumes');
            return $this->output(
                array('error' => $this->ElFinder->error(ElFinder::ERROR_CONF, ElFinder::ERROR_CONF_NO_VOL))
            );
        }

        // telepat_mode: on
        if (!$cmd && $isPost) {
            return $this->output(
                array(
                    'error' => $this->ElFinder->error(ElFinder::ERROR_UPLOAD, ElFinder::ERROR_UPLOAD_TOTAL_SIZE),
                    'header' => 'Content-Type: text/html')
            );
        }
        // telepat_mode: off
        if (!$this->ElFinder->commandExists($cmd)) {
            return $this->output(array('error' => $this->ElFinder->error(ElFinder::ERROR_UNKNOWN_CMD)));
        }

        // collect required arguments to execute command
        foreach ($this->ElFinder->commandArgsList($cmd) as $name => $req) {
            $arg = $name == 'FILES'
                ?  $this->getFiles($request->files)//was $_FILES
                : ($src->has($name) ? $src->get($name) : '');

            if (!is_array($arg)) {
                $arg = trim($arg);
            }
            if ($req && (!isset($arg) || $arg === '')) {
                return $this->output(array('error' => $this->ElFinder->error(ElFinder::ERROR_INV_PARAMS, $cmd)));
            }
            $args[$name] = $arg;
        }
        $args['debug'] = $src->has('debug') ? !!$src->get('debug') : false;

        return $this->output($this->ElFinder->exec($cmd, $this->inputFilter($args)));
    }

    /**
     * Output data to array
     *
     * @param  array
     * @return array
     * @author Dmitry (dio) Levashov
     **/
    protected function output(array $data) {
        $header = isset($data['header']) ? $data['header'] : $this->header;
        unset($data['header']);
        if ($header) {
            $response = new Response();
            if (is_array($header)) {
                foreach ($header as $h) {
                    $header_param = explode(":",$h);
                    $response->headers->set($header_param[0], $header_param[1]);
                }
            } else {
                $header_param = explode(":",$header);
                $response->headers->set($header_param[0], $header_param[1]);
            }
            $response->send();
        }

        if (isset($data['pointer'])) {
            rewind($data['pointer']);
            fpassthru($data['pointer']);
            if (!empty($data['volume'])) {
                $data['volume']->close($data['pointer'], $data['info']['hash']);
            }
            return array();
        } else {
            if (!empty($data['raw']) && !empty($data['error'])) {
                return array('error' => $data['error']);
            } else {
                return $data;
            }
        }

    }

    /**
     * Remove null & strip slashes applies on "magic_quotes_gpc"
     *
     * @param  mixed  $args
     * @return mixed
     * @author Naoki Sawada
     */
    private function inputFilter($args) {
        static $magic_quotes_gpc = NULL;

        if ($magic_quotes_gpc === NULL)
            $magic_quotes_gpc = (version_compare(PHP_VERSION, '5.4', '<') && get_magic_quotes_gpc());

        if (is_array($args)) {
            return array_map(array(& $this, 'inputFilter'), $args);
        }
        $res = str_replace("\0", '', $args);
        $magic_quotes_gpc && ($res = stripslashes($res));
        return $res;
    }

    /**
     * @param $filebag
     * @return array
     * Requires refactoring in Elfinder
     */
    private function getFiles(FileBag $filebag)
    {
        $result = array();
        foreach($filebag as $files) {
            foreach($files as $file) {
                $result['upload']['name'][]     = $file->getClientOriginalName();
                $result['upload']['tmp_name'][] = $file->getRealPath();
                $result['upload']['type'][]     = $file->getMimeType();
                $result['upload']['error'][]    = $file->getError();
            }

        }
        return $result;
    }

    /**
     * @param mixed $defaultVolume
     * @return ElFinderConnector
     */
    public function setDefaultVolume($defaultVolume)
    {
        $this->defaultVolume = $defaultVolume;
    }

    /**
     * @return mixed
     */
    public function getDefaultVolume()
    {
        return $this->defaultVolume;
    }

    /**
     * @return Logger
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @param Logger $log
     * @return Logger
     */
    public function setLog($log)
    {
        $this->log = $log;
        return $this->log;
    }

    /**
     * @param array $volumes
     * @return ElFinderConnector
     */
    public function setVolumes($volumes)
    {
        $this->volumes = $volumes;
        return $this;
    }

    /**
     * @return array
     */
    public function getVolumes()
    {
        return $this->volumes;
    }
}