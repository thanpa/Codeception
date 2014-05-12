<?php

namespace Codeception\Module;

use Codeception\Lib\Connector\Guzzle;
use Codeception\Lib\InnerBrowser;
use Codeception\Lib\Interfaces\MultiSession;
use Codeception\Lib\Interfaces\Remote;
use Codeception\Exception\TestRuntime;
use Codeception\TestCase;
use GuzzleHttp\Client;
use Symfony\Component\BrowserKit\Request;

/**
 * Uses [Goutte](https://github.com/fabpot/Goutte) and [Guzzle](http://guzzlephp.org/) to interact with your application over CURL.
 * Module works over CURL and requires **PHP CURL extension** to be enabled.
 *
 * Use to perform web acceptance tests with non-javascript browser.
 *
 * If test fails stores last shown page in 'output' dir.
 *
 * ## Status
 *
 * * Maintainer: **davert**
 * * Stability: **stable**
 * * Contact: davert.codecept@mailican.com
 * * relies on [Goutte](https://github.com/fabpot/Goutte) and [Guzzle](http://guzzlephp.org/)
 *
 * *Please review the code of non-stable modules and provide patches if you have issues.*
 *
 * ## Configuration
 *
 * * url *required* - start url of your app
 * * curl - curl options
 *
 * ### Example (`acceptance.suite.yml`)
 *
 *     modules:
 *        enabled: [PhpBrowser]
 *        config:
 *           PhpBrowser:
 *              url: 'http://localhost'
 *              curl:
 *                  CURLOPT_RETURNTRANSFER: true
 *
 * ## Public Properties
 *
 * * guzzle - contains [Guzzle](http://guzzlephp.org/) client instance: `\GuzzleHttp\Client`
 *
 * All SSL certification checks are disabled by default.
 * To configure CURL options use `curl` config parameter.
 *
 */
class PhpBrowser extends InnerBrowser implements Remote, MultiSession
{

    protected $requiredFields = array('url');
    protected $config = array('verify' => false, 'expect' => false, 'timeout' => 30, 'curl' => []);
    protected $guzzleConfigFields = ['headers', 'auth', 'proxy', 'verify', 'cert', 'query', 'ssl_key','proxy', 'expect', 'version', 'cookies', 'timeout', 'connect_timeout', ''];

    /**
     * @var \Codeception\Lib\Connector\Guzzle
     */
    public $client;

    /**
     * @var \GuzzleHttp\Client
     */
    public $guzzle;

    public function _initialize()
    {
        $defaults = array_intersect_key($this->config, array_flip($this->guzzleConfigFields));
        $defaults['config']['curl'] = $this->config['curl'];

        foreach ($this->config['curl'] as $key => $val) {
            if (defined($key)) $defaults['config']['curl'][constant($key)] = $val;
        }
        $this->guzzle = new Client(['defaults' => $defaults]);
    }

    public function _before(\Codeception\TestCase $test) {
        $this->_initializeSession();
    }

    public function _getUrl()
    {
        return $this->config['url'];
    }

    public function setHeader($header, $value)
    {
        $this->client->setHeader($header, $value);
    }

    public function amOnSubdomain($subdomain)
    {
        $url = $this->config['url'];
        $url = preg_replace('~(https?:\/\/)(.*\.)(.*\.)~', "$1$3", $url); // removing current subdomain
        $url = preg_replace('~(https?:\/\/)(.*)~', "$1$subdomain.$2", $url); // inserting new
        $this->_reconfigure(array('url' => $url));
    }

    protected function onReconfigure()
    {
        $this->_initializeSession();
    }

    /**
     * Low-level API method.
     * If Codeception commands are not enough, use [Guzzle HTTP Client](http://guzzlephp.org/) methods directly
     *
     * Example:
     *
     * ``` php
     * <?php
     * // from the official Guzzle manual
     * $I->amGoingTo('Sign all requests with OAuth');
     * $I->executeInGuzzle(function (\Guzzle\Http\Client $client) {
     *      $client->addSubscriber(new Guzzle\Plugin\Oauth\OauthPlugin(array(
     *                  'consumer_key'    => '***',
     *                  'consumer_secret' => '***',
     *                  'token'           => '***',
     *                  'token_secret'    => '***'
     *      )));
     * });
     * ?>
     * ```
     *
     * It is not recommended to use this command on a regular basis.
     * If Codeception lacks important Guzzle Client methods, implement them and submit patches.
     *
     * @param callable $function
     */
    public function executeInGuzzle(\Closure $function)
    {
        return $function($this->guzzle);
    }


    public function _getResponseCode()
    {
        return $this->getResponseStatusCode();
    }

    public function _initializeSession()
    {
        $this->client = new Guzzle();
        $this->client->setClient($this->guzzle);
        $this->client->setBaseUri($this->config['url']);
    }

    public function _backupSessionData()
    {
        return [
            'client'    => $this->client,
            'guzzle'    => $this->guzzle,
            'crawler'   => $this->crawler
        ];
    }

    public function _loadSessionData($data)
    {
        foreach ($data as $key => $val) {
            $this->$key = $val;
        }
    }

    public function _closeSession($data)
    {
        unset($data);
    }
}
