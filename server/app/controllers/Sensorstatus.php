<?php
namespace HoneySens\app\controllers;

use HoneySens\app\models\entities\Sensor;
use HoneySens\app\models\entities\SensorStatus as SStatus;
use HoneySens\app\models\exceptions\BadRequestException;
use HoneySens\app\models\exceptions\NotFoundException;
use Respect\Validation\Validator as V;

class Sensorstatus extends RESTResource {

    static function registerRoutes($app, $em, $beanstalk, $config, $messages) {
        $app->get('/api/sensorstatus/by-sensor/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorstatus($em, $beanstalk, $config);
            $criteria = array();
            $criteria['userID'] = $controller->getSessionUserID();
            $criteria['sensorID'] = $id;
            try {
                $result = $controller->get($criteria);
            } catch(\Exception $e) {
                throw new NotFoundException();
            }
            echo json_encode($result);
        });

        // Used by sensors to send their status data and receive current configuration
        $app->post('/api/sensorstatus', function() use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorstatus($em, $beanstalk, $config);
            $request = $app->request()->getBody();
            V::json()->check($request);
            $statusData = json_decode($request);
            V::objectType()
                ->attribute('sensor', V::intVal())
                ->check($statusData);
            $status = $controller->create($statusData);
            $config = $controller->getConfig();
            $controller->reduce($statusData->sensor, 10);
            // Collect sensor configuration and send it as response
            $sensorData = $status->getSensor()->getState();
            if($status->getSensor()->getServerEndpointMode() == Sensor::SERVER_ENDPOINT_MODE_DEFAULT) {
                $sensorData['server_endpoint_host'] = $config['server']['host'];
                $sensorData['server_endpoint_port_https'] = $config['server']['portHTTPS'];
            }
            $config = $status->getSensor()->getConfiguration();
            $sensorData['interval'] = $config->getUpdateInterval();
            $sensorData['recon'] = $config->getRecon();
            $sensorData['kippoHoneypot'] = $config->getKippoHoneypot();
            $sensorData['dionaeaHoneypot'] = $config->getDionaeaHoneypot();
            // Replace service assignments with elaborate service data
            $services = array();
            $serviceRepository = $em->getRepository('HoneySens\app\models\entities\Service');
            $serviceRevisionRepository = $em->getRepository('HoneySens\app\models\entities\ServiceRevision');
            foreach($sensorData['services'] as $serviceAssignment) {
                $service = $serviceRepository->find($serviceAssignment['service']);
                $serviceData = array();
                $serviceData['service'] = array('name' => $service->getLabel(), 'image' => sprintf('%s:%s', $service->getRepository(), $service->getDefaultRevision()->getRevision()));
                $serviceData['revision'] = $serviceAssignment['revision'] != null ? $serviceRevisionRepository->find($serviceAssignment['revision'])->getState() : null;
                $services[] = $serviceData;
            }
            $sensorData['services'] = $services;
            // Send proxy passwords exclusively to the sensors (they aren't shown inside of the web interface)
            $sensorData['proxy_password'] = $status->getSensor()->getProxyPassword();
            // Gather firmware versioning information
            if($config->getImage()) {
                $sensorData['sw_version'] = $config->getImage()->getVersion();
            } else {
                $defaultConfig = $em->getRepository('HoneySens\app\models\entities\SensorConfig')->find(1);
                $sensorData['sw_version'] = $defaultConfig->getImage()->getVersion();
            }
            // Unhandled event status data for physical LED indication
            $qb = $controller->getEntityManager()->createQueryBuilder();
            $unhandledEventCount = $qb->select('COUNT(e.id)')
                ->from('HoneySens\app\models\entities\Event', 'e')
                ->join('e.sensor', 's')
                ->andWhere('s.id = :sensor')
                ->andWhere('e.status = :status')
                ->setParameter('sensor', $statusData->sensor)
                ->setParameter('status', 0)
                ->getQuery()->getSingleScalarResult();
            $sensorData['unhandledEvents'] = $unhandledEventCount != 0;
            echo json_encode($sensorData);
        });
    }

    /**
     * Fetches sensor status data from the DB by various criteria:
     * - userID: return only status objects that belong to the user with the given id
     * - sensorID: return status objects that belong to the given sensor
     * If no criteria are given, all status objects are returned.
     *
     * @param array $criteria
     * @return array
     */
	public function get($criteria) {
		$this->assureAllowed('get');
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('ss')->from('HoneySens\app\models\entities\SensorStatus', 'ss')
            ->join('ss.sensor', 's');
        if(V::key('userID', V::intType())->validate($criteria)) {
            $qb->join('s.division', 'd')
                ->andWhere(':userid MEMBER OF d.users')
                ->setParameter('userid', $criteria['userID']);
        }
        if(V::key('sensorID', V::intVal())->validate($criteria)) {
            $qb->andWhere('s.id = :id')
                ->setParameter('id', $criteria['sensorID']);
        }
        $stati = array();
        foreach($qb->getQuery()->getResult() as $status) {
            $stati[] = $status->getState();
        }
        return $stati;
	}

    /**
     * Registers new status data from a sensor.
     * The given data object should have the following attributes:
     * - status: The actual status data as JSON object, encoded in base64
     * - sensor: Sensor id
     * - signature: Base64 encoded signature of the 'status' value
     *
     * The status data JSON object has to consist of the following attributes:
     * - timestamp: UNIX timestamp of the current sensor time
     * - status: Flat that indicates the current sensor status (0 to 4)
     * - ip: IP address of the sensor's primary network interface
     * - free_mem: Free RAM on the sensor
     * - sw_version: Current sensor firmware revision
     *
     * @param stdClass $data
     * @return SStatus
     * @throws BadRequestException
     */
	public function create($data) {
        // Validation
        V::objectType()
            ->attribute('status', V::stringType())
            ->attribute('sensor', V::intVal())
            ->attribute('signature', V::stringType())
            ->check($data);
        $statusDataDecoded = base64_decode($data->status);
        V::json()->check($statusDataDecoded);
		$statusData = json_decode($statusDataDecoded);
        V::objectType()
            ->attribute('timestamp', V::intVal())
            ->attribute('status', V::intVal()->between(0, 4))
            ->attribute('ip', V::stringType())
            ->attribute('free_mem', V::intVal())
            ->attribute('sw_version', V::stringType())
            ->check($statusData);
		$em = $this->getEntityManager();
		$sensor = $em->getRepository('HoneySens\app\models\entities\Sensor')->find($data->sensor);
        V::objectType()->check($sensor);
		// Check timestamp validity: accept timestamps that aren't older than two minutes
		$now = new \DateTime();
		if(($sensor->getLastStatus() != null && $statusData->timestamp < $sensor->getLastStatus()->getTimestamp()->format('U'))
            || $statusData->timestamp < ($now->format('U') - 120)) {
            // TODO Invalid timestamp return value
            throw new BadRequestException();
		}
		// Check sensor cert validity
		$cert = $sensor->getCert();
		$x509 = new \File_X509();
		$x509->loadCA(file_get_contents(APPLICATION_PATH . '/../data/CA/cacert.pem'));
		$x509->loadX509($cert->getContent());
		if(!$x509->validateSignature()) {
            // TODO Invalid sensor cert return value
			throw new BadRequestException();
		}
		// Check signature validity
		if(!openssl_verify(base64_decode($data->status), base64_decode($data->signature), $cert->getContent())) {
            // TODO Invalid signature return value
            throw new BadRequestException();
		}
		// Persistence
		$status = new SStatus();
		$timestamp = new \DateTime('@' . $statusData->timestamp);
		$timestamp->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		$status->setSensor($sensor)
			->setTimestamp($timestamp)
            ->setStatus($statusData->status)
			->setIP($statusData->ip)
			->setFreeMem($statusData->free_mem)
			->setSWVersion($statusData->sw_version);
		$em->persist($status);
		$em->flush();
		return $status;
	}

	/**
	 * Removes the oldest status entries of a particular sensor
	 * 
	 * @param int $sensor_id The id of the sensor to clean up for
	 * @param int $keep The number of entries to keep
	 */
	public function reduce($sensor_id, $keep) {
        // Validation
        V::intVal()->check($sensor_id);
        V::intVal()->check($keep);
        // Persistence
		$em = $this->getEntityManager();
		$statusSorted = array();
		$sensor = $em->getRepository('HoneySens\app\models\entities\Sensor')->find($sensor_id);
        V::objectType()->check($sensor);
		$allStatus = $sensor->getStatus();
		foreach($allStatus as $key => $status) {
			$statusSorted[$key] = $status;
			$timestamps[$key] = $status->getTimestamp();
		}
		if(count($statusSorted) > $keep) {
			array_multisort($timestamps, SORT_DESC, $statusSorted);
			$toRemove = array_slice($statusSorted, $keep);
			foreach($toRemove as $status) {
				$sensor->removeStatus($status);
				$em->remove($status);
			}
			$em->flush();
		}
	}
}
