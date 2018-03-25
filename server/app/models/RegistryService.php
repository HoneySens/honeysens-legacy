<?php
namespace HoneySens\app\models;

use HoneySens\app\models\exceptions\NotFoundException;

class RegistryService {

    protected $appConfig = null;

    public function __construct($config) {
        $this->appConfig = $config;
    }

    public function isAvailable() {
        $registryConfig = $this->appConfig['registry'];
        try {
            $request = \Requests::get(sprintf('http://%s:%u/v2/', $registryConfig['host'], $registryConfig['port']), array(), array());
        } catch(\Exception $e)  {
            return false;
        }
        return $request->status_code == 200;
    }

    public function getRepositories() {
        $registryConfig = $this->appConfig['registry'];
        $request = \Requests::get(sprintf('http://%s:%u/v2/_catalog', $registryConfig['host'], $registryConfig['port']), array(), array());
        return json_decode($request->body);
    }

    public function getTags($repository) {
        $registryConfig = $this->appConfig['registry'];
        $request = \Requests::get(sprintf('http://%s:%u/v2/%s/tags/list', $registryConfig['host'], $registryConfig['port'], $repository), array(), array());
        if(!$request->success) throw new NotFoundException();
        return json_decode($request->body)->tags;
    }

    public function removeRepository($repository) {
        if(!$this->isAvailable()) throw new \Exception('Registry offline');
        // TODO
    }
}