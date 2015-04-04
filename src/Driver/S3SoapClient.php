<?php

namespace FM\ElFinderPHP\Driver;

use SoapClient;

/**
 * SoapClient extension with Amazon S3 WSDL and request signing support
 *
 * @author Alexey Sukhotin
 **/
class S3SoapClient extends SoapClient {

    private $accesskey = '';
    private $secretkey = '';
    public $client = NULL;


    public function __construct($key = '', $secret = '') {
        $this->accesskey = $key;
        $this->secretkey = $secret;
        parent::__construct('http://s3.amazonaws.com/doc/2006-03-01/AmazonS3.wsdl');
    }


    /**
     * Method call wrapper which adding S3 signature and default arguments to all S3 operations
     *
     * @author Alexey Sukhotin
     **/
    public function __call($method, $arguments) {

        /* Getting list of S3 web service functions which requires signing */
        $funcs = $this->__getFunctions();

        $funcnames  = array();

        foreach ($funcs as $func) {
            preg_match("/\S+\s+([^\)]+)\(/", $func, $m);

            if (isset($m[1])) {
                $funcnames[] = $m[1];
            }
        }

        /* adding signature to arguments */
        if (in_array("{$method}", $funcnames)) {

            if (is_array($arguments[0])) {
                $arguments[0] = array_merge($arguments[0], $this->sign("{$method}"));
            } else {
                $arguments[0] = $this->sign("{$method}");
            }

        }

        /*$fp = fopen('/tmp/s3debug.txt', 'a+');
        fwrite($fp, 'method='."{$method}". ' timestamp='.date('Y-m-d H:i:s').' args='.var_export($arguments,true) . "\n");
        fclose($fp);*/
        return parent::__call($method, $arguments);
    }

    /**
     * Generating signature and timestamp for specified S3 operation
     *
     * @param  string  $operation    S3 operation name
     * @return array
     * @author Alexey Sukhotin
     **/
    protected function sign($operation) {

        $params = array(
            'AWSAccessKeyId' => $this->accesskey,
            'Timestamp' => gmdate('Y-m-d\TH:i:s.000\Z'),
        );

        $sign_str = 'AmazonS3' . $operation . $params['Timestamp'];

        $params['Signature'] = base64_encode(hash_hmac('sha1', $sign_str, $this->secretkey, TRUE));

        return $params;
    }

}