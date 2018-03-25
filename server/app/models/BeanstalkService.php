<?php
namespace HoneySens\app\models;

use HoneySens\app\controllers\System;
use HoneySens\app\models\exceptions\ForbiddenException;
use \Pheanstalk\Pheanstalk;
use \HoneySens\app\models\entities\Sensor;

class BeanstalkService {
	
	protected $pheanstalkInstance = null;
    protected $appConfig = null;
	
	public function __construct($config) {
        $this->appConfig = $config;
		$this->pheanstalkInstance = new Pheanstalk($config['beanstalkd']['host'], $config['beanstalkd']['port']);
	}
	
	public function isAvailable() {
		return $this->pheanstalkInstance->getConnection()->isServiceListening();
	}
	
	public function putImageConversionJob($image) {
		$jobData = array(
			'fwPath' => realpath(APPLICATION_PATH . '/../data/firmware/') . '/' . $image->getFile(),
			'fwID' => $image->getId(),
			'outPath' => realpath(APPLICATION_PATH . '/../data/firmware/sd') . '/' . $image->getConvertedFile()
		);
		$this->pheanstalkInstance->useTube('honeysens-imgconv')->put(json_encode($jobData));
	}

    /**
     * Schedules a new job that creates a sensor configuration archive.
     */
    public function putSensorConfigCreationJob($sensor, $em) {
        $sensor->setConfigArchiveStatus(Sensor::CONFIG_ARCHIVE_STATUS_SCHEDULED);
        $jobData = $sensor->getState();
        $jobData['cert'] = $sensor->getCert()->getContent();
        $jobData['key'] = $sensor->getCert()->getKey();
        if($sensor->getConfiguration()->getImage()) {
            $jobData['sw_version'] = $sensor->getConfiguration()->getImage()->getVersion();
        } else {
            // If this sensor has a custom configuration without a specific image configured, we have to rely on the default one
            $defaultConfig = $em->getRepository('HoneySens\app\models\entities\SensorConfig')->find(1);
            $jobData['sw_version'] = $defaultConfig->getImage()->getVersion();
        }
        if($sensor->getServerEndpointMode() == Sensor::SERVER_ENDPOINT_MODE_DEFAULT) {
            $jobData['server_endpoint_host'] = $this->appConfig['server']['host'];
            $jobData['server_endpoint_port_https'] = $this->appConfig['server']['portHTTPS'];
        }
        $jobData['server_endpoint_name'] = $this->appConfig['server']['host'];
        $jobData['proxy_password'] = $sensor->getProxyPassword();
        $this->pheanstalkInstance->useTube('honeysens-sensorcfg')->put(json_encode($jobData));
    }

    public function putUpdateJob() {
        $jobData = array('server_version' => System::VERSION);
        if(file_exists(realpath(APPLICATION_PATH . '/../data/') . '/UPDATE')) throw new ForbiddenException();
        $update_marker = fopen(realpath(APPLICATION_PATH . '/../data/') . '/UPDATE', 'w');
        fclose($update_marker);
        $this->pheanstalkInstance->useTube('honeysens-update')->put(json_encode($jobData));
    }

    /**
     * Pushes an docker image archive with the given name to the registry.
     * Name should be the full name (including tag) of the image, e.g. honeysens/cowrie:0.1.0
     * The archive path should be the absolute path to the image archive.
     *
     * @param string $name
     * @param string $archivePath
     */
    public function putServiceRegistryJob($name, $archivePath) {
        $jobData = array('name' => $name, 'archive_path' => $archivePath);
        $this->pheanstalkInstance->useTube('honeysens-service-registry')->put(json_encode($jobData));
    }
}