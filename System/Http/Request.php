<?php

/**
 * SilverEngine  - PHP MVC framework
 *
 * @package   SilverEngine
 * @author    SilverEngine Team
 * @copyright 2015-2017
 * @license   MIT
 * @link      https://github.com/SilverEngine/Framework
 */

namespace Silver\Http;

use Silver\Core\Route;
use Silver\Core\Blueprints\Http\RequestInterface;
use Silver\Core\AppInstanceTrait;

/**
 * Class Request
 *
 * @package Silver\Http
 */
class Request implements RequestInterface
{
    /**
     * this is an array of the available http methods
     *
     * @var array
     */
    private $methods = ['get', 'post', 'put', 'delete', 'patch', 'options'];

    /**
     * requested http uri
     *
     * @var null
     */
    private $uri;

    /**
     * traits
     */
    use AppInstanceTrait;


    /**
     * Request constructor.
     * initialize requeted uri
     */
    public function __construct()
    {
        // load uri from 
        $this->uri = $this->getStrippedUri();
    }

    public function server($key, $default = false)
    {
        if ( isset($_SERVER[$key]) ) {
            return $_SERVER[$key];
        }

        return $default;
    }

    /**
     * Strip uri.
     * @return string
     */
    public function getStrippedUri()
    {
        // /sitename/index.php
        $path = explode('/', trim($this->server('SCRIPT_NAME', '/')));

        // /sitename/portfolio/design
        $uri  = explode('/', trim($this->server('REQUEST_URI'), '/'));

        // strip file.
        foreach ($path as $key => $val) {
            if (isset($uri[$key]) && $val == $uri[$key]) { unset($uri[$key]); } 
            else break;
        }

        // portfolio/design
        $uri = implode('/', $uri);

        // strip $_GET
        $uri = explode('?', $uri);
        return '/' . $uri[0];
    }

    /**
     * return the requested http uri
     *
     * @expect a uri string or null
     * @return string|null
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return mixed|string
     */
    public function method()
    {
        $method = $this->param('_method', $_SERVER['REQUEST_METHOD']);
        $method = strtolower($method);

        if (in_array($method, $this->methods)) {
            return $method;
        }

        return 'GET';
    }

    /**
     * @param bool $key
     *
     * @return array|false
     */
    function header($key = false)
    {
        /**
         *  if the http server is apache
         */
        if (strpos($_SERVER["SERVER_SOFTWARE"], "Apache") !== false) {

            $headers = mapArrayKeys(
                apache_request_headers(), function ($elem) {
                    $elem = str_replace('-', '_', $elem);
                    return strtoupper($elem);
                }
            );

            return (isset($headers[$key])) ? $headers[$key] : $headers;
        }
        /**
         * if the http server is not apache execute this section.
         */
        foreach ($_SERVER as $name => $value) {

            if (substr($name, 0, 5) == 'HTTP_') {

                $name = substr(strtoupper($name), 5);

                $headers[$name] = $value;
            }
        }

        return (isset($headers[$key])) ? $headers[$key] : $headers;
    }

    /**
     * @return mixed
     */
    public function all()
    {
        return $_REQUEST;
    }

    /**
     * @param      $name
     * @param null $default
     * @return mixed
     */
    public function param($name, $default = null)
    {
        return isset($this->all()[$name]) ? $this->all()[$name] : $default;
    }


    /**
     * get custom data from both GET and POST requests
     *
     * @param  $name
     * @param  null $default
     * @return null
     */
    public function input($name, $default = null)
    {
        return isset($this->all()[$name]) ? $this->all()[$name] : $default;
    }

    /**
     * check if is an ajax request
     *
     * @return bool
     */
    public function ajax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    /**
     * get segments from the requested uri
     *
     * @param  $id
     * @return null
     */
    public function segment($id)
    {
        $chunks = explode('/', $this->getUri());

        return isset($chunks[$id]) ? $chunks[$id] : null;
    }

    /**
     * get the ip address of the client
     *
     * @return mixed
     */
    public function ip()
    {
        return $_SERVER["REMOTE_ADDR"];
    }

    /**
     * get the route name from the uri
     *
     * @return string
     */
    public function route()
    {
        return Route::find($this->getUri(), $this->method());
    }
}
