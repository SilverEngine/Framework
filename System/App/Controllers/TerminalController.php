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

namespace System\App\Controllers;

use Silver\Core\Controller;
use Silver\Http\View;
use Silver\Http\Session;
use Silver\Http\Request as Req;
use Silver\Http\Response as Res;
use Silver\Engine\Ghost\Template;
use Silver\Engine\Terminal\Manifest;
use Silver\Helpers\String;
use Silver\Helpers\HTMLElement as Html;
use Silver\Exception;
use Silver\Engine\Terminal\Command;

class TerminalController extends Controller
{
    private function nocache()
    {
        $res = Res::instance();
        $res->setHeader("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
        $res->setHeader("Pragma", "no-cache"); // HTTP/1.0
    }

    public function index()
    {
        return View::make('terminal.index')
            ->withUser(Session::get('terminal.user'));
    }

    public function manifest()
    {
        $this->nocache();

        $m = new Manifest;
        return [
            'commands' => $m->getCommands(),
            'aliases' => $m->getAliases(),
            'command_arguments' => $m->getArguments(),
            'services' => $m->getServices(),
            'messages' => $m->getMessages()
        ];
    }

    public function resource(Req $req)
    {
        $this->nocache();

        try {
            $command = $req->param('command');
            $resource = $req->param('resource');
            $target = Manifest::resource($command, $resource);
            
            // We have /manifest for this purpose
            if (String::endsWith($target, 'manifest.json')) {
                return;
            }

            // Dont have permission for this command
            if (!self::hasPermission($command)) {
                return;
            }

            $ext = pathinfo($target, PATHINFO_EXTENSION);
            if(in_array($ext, ['php', 'js'])) {
                return new Template($target);
            } else {
                return file_get_contents($target);
            }
        } catch (Exception $e) {
            $pos = json_encode($e->getFile() . ':' . $e->getLine());
            $msg = json_encode($e->getMessage());
            return <<<JS
terminal.error($pos);
terminal.error('Message: ' + $msg);
JS;
        }
    }

    public function execute(Req $req, $program, $command)
    {
        try {
            $action = $req->param('action');
            $env = json_decode($req->param('env'));
            $class = '\\Silver\\Engine\\Terminal\\Commands\\' . $program;

            if(!self::hasPermission($program)) {
                throw new Exception("Permission denied.");
            }

            if(!self::commandExists($program, $command)) {
                throw new Exception("Invalid command: $program::$command");
            }

            switch($action) {
            case 'execute':
                $opts = json_decode($req->param('opts'));
                $args = json_decode($req->param('args'));

                $object = new $class($env);
                $object->$command($opts, $args); // Ignore return value
                return $object->_getActions();
            case 'continue':
                $input_values = json_decode($req->param('input_values'));
                $object = Command::_process($input_values, $env);
                return $object->_getActions();
            default:
                return [[
                    'type' => 'output',
                    'content' => (string) Html::make('span', [
                        'style' => 'color: red'
                    ], "Terminal error: unknown action '$action'"),
                    'html' => true,
                ], [
                    'type' => 'output',
                    'content' => "\n",
                    'html' => false
                ]];
            }
        } catch(\Exception $e) {
            return [[
                'type' => 'output',
                'content' => (string) Html::make('span', [
                    'style' => 'color: red'
                ], 'Exception: ' . $e->getMessage()),
                'html' => true,
            ], [
                'type' => 'output',
                'content' => "\n",
                'html' => false
            ]];
        }
    }

    public function service(Req $req, $program, $service)
    {
        $args = json_decode($req->param('args'));
        $class = '\\Silver\\Engine\\Terminal\\Commands\\' . $program;

        if(!self::hasPermission($program)) {
            throw new Exception("Permission denied.");
        }

        if(!self::serviceExists($program, $service)) {
            throw new Exception("Invalid service: $program::$service");
        }


        $result = call_user_func_array([$class, $service], $args);
        return json_encode($result);
    }

    private function hasPermission($program)
    {
        $manifest = Manifest::get($program);

        if (isset($manifest->auth) and $manifest->auth) {
            return !!Session::get('terminal.user', false);
        }
        
        return true;
    }

    private function commandExists($program, $command)
    {
        $manifest = Manifest::get($program);
        return in_array($command, $manifest->provide);
    }

    private function serviceExists($program, $service)
    {
        $manifest = Manifest::get($program);
        return in_array($service, $manifest->services);
    }
}
