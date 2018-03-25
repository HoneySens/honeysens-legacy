<?php
namespace HoneySens\app\controllers;

use HoneySens\app\models\entities\Sensor;
use HoneySens\app\models\entities\Service;
use HoneySens\app\models\entities\ServiceAssignment;
use HoneySens\app\models\entities\SSLCert;
use HoneySens\app\models\exceptions\NotFoundException;
use Respect\Validation\Validator as V;

class Sensors extends RESTResource {

    static function registerRoutes($app, $em, $beanstalk, $config, $messages) {
        $app->get('/api/sensors(/:id)/', function($id = null) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensors($em, $beanstalk, $config);
            $criteria = array();
            $criteria['userID'] = $controller->getSessionUserID();
            $criteria['id'] = $id;
            try {
                $result = $controller->get($criteria);
            } catch(\Exception $e) {
                throw new NotFoundException();
            }
            echo json_encode($result);
        });

        $app->get('/api/sensors/config/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            // TODO This does need authentication via userID!
            $controller = new Sensors($em, $beanstalk, $config);
            $controller->downloadConfig($id);
        });

        $app->post('/api/sensors', function() use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensors($em, $beanstalk, $config);
            $request = $app->request()->getBody();
            V::json()->check($request);
            $sensorData = json_decode($request);
            $sensor = $controller->create($sensorData);
            echo json_encode($sensor->getState());
        });

        $app->put('/api/sensors/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensors($em, $beanstalk, $config);
            $request = $app->request()->getBody();
            V::json()->check($request);
            $sensorData = json_decode($request);
            $sensor = $controller->update($id, $sensorData);
            echo json_encode($sensor->getState());
        });

        $app->delete('/api/sensors/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensors($em, $beanstalk, $config);
            $controller->delete($id);
            echo json_encode([]);
        });
    }

    /**
     * Fetches sensors from the DB by various criteria:
     * - userID: return only sensors that belong to the user with the given id
     * - id: return the sensor with the given id
     * If no criteria are given, all sensors are returned.
     *
     * @param array $criteria
     * @return array
     */
	public function get($criteria) {
		$this->assureAllowed('get');
		$qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('s')->from('HoneySens\app\models\entities\Sensor', 's');
        if(V::key('userID', V::intType())->validate($criteria)) {
            $qb->join('s.division', 'd')
                ->andWhere(':userid MEMBER OF d.users')
                ->setParameter('userid', $criteria['userID']);
        }
        if(V::key('id', V::intVal())->validate($criteria)) {
            $qb->andWhere('s.id = :id')
                ->setParameter('id', $criteria['id']);
            return $qb->getQuery()->getSingleResult()->getState();
        } else {
			$sensors = array();
			foreach($qb->getQuery()->getResult() as $sensor) {
				$sensors[] = $sensor->getState();
			}
			return $sensors;
		}
	}

    /**
     * Creates and persists a new Sensor object.
     * The following parameters are required:
     * - name: Sensor name
     * - location: Informal sensor location description
     * - division: ID of the Division this sensor belongs to
     * - server_endpoint_mode: 0 or 1, how to contact the server
     * - network_ip_mode: 0 or 1, how an IP address is set on the sensor
     * - network_mac_mode: 0 or 1, use the default or a custom MAC address
     * - proxy_mode: 0 or 1, disable or enable HTTPS proxy support
     *
     * Depending on the previous attributes the following ones may also be required:
     * - server_endpoint_host: String that specifies the server name (IP or DNS name)
     * - server_endpoint_port_https: The TCP port the server uses for HTTPS
     * - network_ip_address: IP address in case of static network configuration
     * - network_ip_netmask: Netmask in case of static network configuration
     * - network_ip_gateway: Gateway in case of static network configuration (optional)
     * - network_ip_dns: DNS server to use in case of static network configuration (optional)
     * - network_mac_address: Custom MAC address
     * - proxy_host: Hostname / IP address of a HTTPS proxy to use
     * - proxy_port: The TCP port the proxy server listens on
     * - proxy_user: Required for proxy authentication
     * - proxy_password: Required for proxy authentication
     *
     * The new sensor will use the global default configuration.
     *
     * @param \stdClass $data
     * @return Sensor
     */
	public function create($data) {
		$this->assureAllowed('create');
        // Validation
        V::objectType()
            ->attribute('name', V::alnum('_-.')->length(1, 50))
            ->attribute('location', V::stringType()->length(0, 255))
            ->attribute('division', V::intVal())
            ->attribute('server_endpoint_mode', V::intVal()->between(0, 1))
            ->attribute('network_ip_mode', V::intVal()->between(0, 1))
            ->attribute('network_mac_mode', V::intVal()->between(0, 1))
            ->attribute('proxy_mode', V::intVal()->between(0, 1))
            ->check($data);
        // Persistence
		$em = $this->getEntityManager();
        $division = $em->getRepository('HoneySens\app\models\entities\Division')->find($data->division);
        V::objectType()->check($division);
		$defaultconfig = $em->getRepository('HoneySens\app\models\entities\SensorConfig')->find(1);
        V::objectType()->check($defaultconfig);
		$sensor = new Sensor();
		$sensor->setName($data->name)
			->setLocation($data->location)
            ->setDivision($division)
            ->setServerEndpointMode($data->server_endpoint_mode)
            ->setNetworkIPMode($data->network_ip_mode)
            ->setNetworkMACMode($data->network_mac_mode)
            ->setProxyMode($data->proxy_mode);
        // Validate and persist additional attributes depending on the previous ones
        if($sensor->getServerEndpointMode() == Sensor::SERVER_ENDPOINT_MODE_CUSTOM) {
            V::attribute('server_endpoint_host', V::stringType()->ip())
                ->attribute('server_endpoint_port_https', V::intVal()->between(0, 65535))
                ->check($data);
            $sensor->setServerEndpointHost($data->server_endpoint_host)
                ->setServerEndpointPortHTTPS($data->server_endpoint_port_https);
        }
        if($sensor->getNetworkIPMode() == Sensor::NETWORK_IP_MODE_STATIC) {
            V::attribute('network_ip_address', V::stringType()->ip())
                ->attribute('network_ip_netmask', V::stringType()->ip())
                ->attribute('network_ip_gateway', V::optional(V::stringType()->ip()))
                ->attribute('network_ip_dns', V::optional(V::stringType()->ip()))
                ->check($data);
            $sensor->setNetworkIPAddress($data->network_ip_address)
                ->setNetworkIPNetmask($data->network_ip_netmask)
                ->setNetworkIPGateway($data->network_ip_gateway)
                ->setNetworkIPDNS($data->network_ip_dns);
        }
        if($sensor->getNetworkMACMode() == Sensor::NETWORK_MAC_MODE_CUSTOM) {
            V::attribute('network_mac_address', V::stringType()->macAddress())
                ->check($data);
            $sensor->setNetworkMACAddress($data->network_mac_address);
        }
        if($sensor->getProxyMode() == Sensor::PROXY_MODE_ENABLED) {
            V::attribute('proxy_host', V::stringType())
                ->attribute('proxy_port', V::intVal()->between(0, 65535))
                ->attribute('proxy_user', V::stringType())
                ->check($data);
            $sensor->setProxyHost($data->proxy_host)
                ->setProxyPort($data->proxy_port)
                ->setProxyUser($data->proxy_user);
            // Only update the password if it was provided by the client,
            // otherwise keep the existing one.
            if(V::attribute('proxy_password', V::stringType())->validate($data)) {
                $sensor->setProxyPassword($data->proxy_password);
            }
            // Also reset the password in case no user was provided
            if($data->proxy_user == null) {
                $sensor->setProxyPassword('');
            }
        }
		$defaultconfig->addSensor($sensor);
        $em->persist($sensor);
        // Flush early, because we need the sensor ID for the cert common name
        $em->flush();
        // Create sensor certificate
        $privkey = null;
        $config = array('config' => APPLICATION_PATH . '/../data/CA/openssl.cnf');
        $cacert = 'file://' . APPLICATION_PATH . '/../data/CA/cacert.pem';
        $cakey = array('file://' . APPLICATION_PATH . '/../data/CA/cakey.pem', 'asdf');
        $dn = array(
            'countryName' => 'DE',
            'stateOrProvinceName' => 'Saxony',
            'localityName' => 'Dresden',
            'organizationName' => 'TUD',
            'organizationalUnitName' => 'Faculty of CS',
            'emailAddress' => 'pascal.brueckner@mailbox.tu-dresden.de',
            'commonName' => $sensor->getHostname()
        );
        $privkey = openssl_pkey_new($config);
        $csr = openssl_csr_new($dn, $privkey, $config);
        $usercert = openssl_csr_sign($csr, $cacert, $cakey, 365, $config);
        openssl_x509_export($usercert, $certout);
        openssl_pkey_export($privkey, $pkeyout);
        $cert = new SSLCert();
        $cert->setContent($certout);
        $cert->setKey($pkeyout);
        // TODO Seriously? One of those should be sufficient...
        $sensor->setCert($cert);
        $cert->setSensor($sensor);
        $em->persist($cert);
		// Generate initial config
        $this->getBeanstalkService()->putSensorConfigCreationJob($sensor, $em);
        $sensor->setConfigArchiveStatus(Sensor::CONFIG_ARCHIVE_STATUS_SCHEDULED);
		$em->flush();
		return $sensor;
	}

    /**
     * Updates an existing Sensor object.
     * The following parameters are required:
     * - name: Sensor name
     * - location: Informal sensor location description
     * - division: ID of the Division this sensor belongs to
     * - config: ID of the sensor configuration this sensor shall use (or null to use the global default)
     * - server_endpoint_mode: 0 or 1, how to contact the server
     * - network_ip_mode: 0 or 1, how an IP address is set on the sensor
     * - network_mac_mode: 0 or 1, use the default or a custom MAC address
     * - proxy_mode: 0 or 1, disable or enable HTTPS proxy support
     * - services: array of service assignments that are supposed to run on this sensor
     *
     * Depending on the previous attributes the following ones may also be required:
     * - server_endpoint_host: String that specifies the server name (IP or DNS name)
     * - server_endpoint_port_https: The TCP port the server uses for HTTPS
     * - network_ip_address: IP address in case of static network configuration
     * - network_ip_netmask: Netmask in case of static network configuration
     * - network_ip_gateway: Gateway in case of static network configuration (optional)
     * - network_ip_dNS: DNS server to use in case of static network configuration (optional)
     * - network_mac_address: Custom MAC address
     * - proxy_host: Hostname / IP address of a HTTPS proxy to use
     * - proxy_port: The TCP port the proxy server listens on
     * - proxy_user: Required for proxy authentication
     * - proxy_password: Required for proxy authentication
     *
     * @param int $id
     * @param \stdClass $data
     * @return Sensor
     */
	public function update($id, $data) {
		$this->assureAllowed('update');
        // Validation
        V::intVal()->check($id);
        V::objectType()
            ->attribute('name', V::alnum('_-.')->length(1, 50))
            ->attribute('location', V::stringType()->length(0, 255))
            ->attribute('division', V::intVal())
            ->attribute('config', V::optional(V::intVal()))
            ->attribute('server_endpoint_mode', V::intVal()->between(0, 1))
            ->attribute('network_ip_mode', V::intVal()->between(0, 1))
            ->attribute('network_mac_mode', V::intVal()->between(0, 1))
            ->attribute('proxy_mode', V::intVal()->between(0, 1))
            ->attribute('services', V::arrayVal()->each(V::objectType()
                ->attribute('service', V::intVal())
                ->attribute('revision')
            ))->check($data);
        // Persistence
        $em = $this->getEntityManager();
		$sensor = $em->getRepository('HoneySens\app\models\entities\Sensor')->find($id);
        V::objectType()->check($sensor);
        $sensor->setName($data->name);
        $sensor->setLocation($data->location);
        // TODO Move this sensor's events to the new Division, too
        $division = $em->getRepository('HoneySens\app\models\entities\Division')->find($data->division);
        V::objectType()->check($division);
        $sensor->setDivision($division);
        // TODO config update handling within the frontend
        if(V::intVal()->validate($data->config)) {
            $config = $em->getRepository('HoneySens\app\models\entities\SensorConfig')->find($data->config);
            $config->addSensor($sensor);
        }
        $sensor->setServerEndpointMode($data->server_endpoint_mode);
        if($sensor->getServerEndpointMode() == Sensor::SERVER_ENDPOINT_MODE_CUSTOM) {
            V::attribute('server_endpoint_host', V::stringType()->ip())
                ->attribute('server_endpoint_port_https', V::intVal()->between(0, 65535))
                ->check($data);
            $sensor->setServerEndpointHost($data->server_endpoint_host)
                ->setServerEndpointPortHTTPS($data->server_endpoint_port_https);
        } else {
            $sensor->setServerEndpointHost(null)
                ->setServerEndpointPortHTTPS(null);
        }
        $sensor->setNetworkIPMode($data->network_ip_mode);
        if($sensor->getNetworkIPMode() == Sensor::NETWORK_IP_MODE_STATIC) {
            V::attribute('network_ip_address', V::stringType()->ip())
                ->attribute('network_ip_netmask', V::stringType()->ip())
                ->attribute('network_ip_gateway', V::optional(V::stringType()->ip()))
                ->attribute('network_ip_dns', V::optional(V::stringType()->ip()))
                ->check($data);
            $sensor->setNetworkIPAddress($data->network_ip_address)
                ->setNetworkIPNetmask($data->network_ip_netmask)
                ->setNetworkIPGateway($data->network_ip_gateway)
                ->setNetworkIPDNS($data->network_ip_dns);
        } else {
            $sensor->setNetworkIPAddress(null)
                ->setNetworkIPNetmask(null)
                ->setNetworkIPGateway(null)
                ->setNetworkIPDNS(null);
        }
        $sensor->setNetworkMACMode($data->network_mac_mode);
        if($sensor->getNetworkMACMode() == Sensor::NETWORK_MAC_MODE_CUSTOM) {
            V::attribute('network_mac_address', V::stringType()->macAddress())
                ->check($data);
            $sensor->setNetworkMACAddress($data->network_mac_address);
        } else {
            $sensor->setNetworkMACAddress(null);
        }
        $sensor->setProxyMode($data->proxy_mode);
        if($sensor->getProxyMode() == Sensor::PROXY_MODE_ENABLED) {
            V::attribute('proxy_host', V::stringType())
                ->attribute('proxy_port', V::intVal()->between(0, 65535))
                ->attribute('proxy_user', V::stringType())
                ->check($data);
            $sensor->setProxyHost($data->proxy_host)
                ->setProxyPort($data->proxy_port)
                ->setProxyUser($data->proxy_user);
            // Only change the password if one was explicity submitted
            if(V::attribute('proxy_password', V::stringType())->validate($data)) {
                $sensor->setProxyPassword($data->proxy_password);
            }
            if($data->proxy_user == null) {
                $sensor->setProxyPassword(null);
            }
        } else {
            $sensor->setProxyHost(null)
                ->setProxyPort(null)
                ->setProxyUser(null)
                ->setProxyPassword(null);
        }
        // Service handling, merge with existing data
        $serviceRepository = $em->getRepository('HoneySens\app\models\entities\Service');
        $revisionRepository = $em->getRepository('HoneySens\app\models\entities\ServiceRevision');
        $assignments = $sensor->getServices()->toArray(); // clone the collection into an array so that newly added models won't interfere with the removal process
        // Add/Update of service assignments
        $handledAssignments = array();
        foreach($data->services as $serviceAssignment) {
            $assigned = false;
            // Validate availability of the assignment
            $service = $serviceRepository->find($serviceAssignment->service);
            V::objectType()->check($service);
            $revision = $serviceAssignment->revision == null ? null : $revisionRepository->find($serviceAssignment->revision);
            // TODO Check if revision belongs to service
            // Update existing assignment if the revision changed
            foreach($assignments as $assignment) {
                if($assignment->getService() == $service ) {
                    $assigned = true;
                    $handledAssignments[] = $assignment;
                    if($assignment->getRevision() != $revision) {
                        $assignment->getService()->setRevision($revision);
                    }
                }
            }
            // Add so far unassigned services
            if(!$assigned) {
                $newAssignment = new ServiceAssignment();
                $sensor->addService($newAssignment);
                $service->addAssignment($newAssignment);
                $newAssignment->setRevision($revision);
                $em->persist($newAssignment);
            }
        }
        // Deletion of remaining service assignments
        foreach(array_udiff($assignments, $handledAssignments, function($a, $b) {return $a === $b;}) as $deletionCandidate) {
            $deletionCandidate->getSensor()->removeService($deletionCandidate);
            $deletionCandidate->getService()->removeAssignment($deletionCandidate);
            $deletionCandidate->setRevision(null);
            $em->remove($deletionCandidate);
        }
		$em->flush();
        // Regenerate sensor config
        // TODO only do that on config changes
        $this->getBeanstalkService()->putSensorConfigCreationJob($sensor, $em);
        return $sensor;
	}
	
	public function delete($id) {
		$this->assureAllowed('delete');
        // Validation
        V::intVal()->check($id);
		$em = $this->getEntityManager();
		$sensor = $em->getRepository('HoneySens\app\models\entities\Sensor')->find($id);
        V::objectType()->check($sensor);
        $config = $sensor->getConfiguration();
        $config->removeSensor($sensor);
        // Remove SensorConfiguration if not used anymore
        if(count($config->getSensors()) == 0 && $config->getId() != 1) {
            $em->remove($config);
        }
        // Remove all events that belong to this sensor
        // TODO Consider moving those into some sort of archive
        $events = $em->getRepository('HoneySens\app\models\entities\Event')->findBy(array('sensor' => $sensor));
        foreach($events as $event) {
            $em->remove($event);
        }
        // Remove service association
        // TODO Replace this with an implicit cascade operation
        $sensor->getServices()->clear();
        $em->remove($sensor);
        $em->flush();
	}

    /**
     * Offers a configuration archive as a file download to authenticated clients.
     *
     * @param int $id
     */
	public function downloadConfig($id) {
		$this->assureAllowed('downloadConfig');
		$sensor = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\Sensor')->find($id);
        V::intVal()->check($id);
		$filePath = realpath(APPLICATION_PATH . '/../data/configs/' . $sensor->getHostname() . '.tar.gz');
		$this->offerFile($filePath, basename($filePath));
	}
}