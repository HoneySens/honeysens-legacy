<?php
namespace HoneySens\app\controllers;

use HoneySens\app\models\entities\SensorConfig;
use HoneySens\app\models\entities\User;
use HoneySens\app\models\exceptions\ForbiddenException;
use HoneySens\app\models\exceptions\NotFoundException;
use Respect\Validation\Validator as V;

class Sensorconfigs extends RESTResource {

    static function registerRoutes($app, $em, $beanstalk, $config, $messages) {
        $app->get('/api/sensorconfigs(/:id)/', function($id = null) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorconfigs($em, $beanstalk, $config);
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

        $app->post('/api/sensorconfigs', function() use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorconfigs($em, $beanstalk, $config);
            $request = $app->request()->getBody();
            V::json()->check($request);
            $configData = json_decode($request);
            $config = $controller->create($configData);
            echo json_encode($config->getState());
        });

        $app->put('/api/sensorconfigs/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorconfigs($em, $beanstalk, $config);
            $request = $app->request()->getBody();
            V::json()->check($request);
            $configData = json_decode($request);
            $config = $controller->update($id, $configData);
            echo json_encode($config->getState());
        });

        $app->delete('/api/sensorconfigs/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorconfigs($em, $beanstalk, $config);
            $controller->delete($id);
            echo json_encode([]);
        });
    }

    /**
     * Fetches SensorConfigs from the DB by various criteria:
     * - userID: return only configs that belong to the user with the given id
     * - id: return the config with the given id
     * If no criteria are given, all configs are returned.
     * The SensorConfig with id 1 isn't returned when querying without any criteria, because
     * that id represents the "default" config that is treated special.
     *
     * @param array $criteria
     * @return array
     */
	public function get($criteria) {
		$this->assureAllowed('get');
		$qb = $this->getEntityManager()->createQueryBuilder();
		$qb->select('sc')->from('HoneySens\app\models\entities\SensorConfig', 'sc');
        if(V::key('userID', V::intType())->validate($criteria)) {
            // Ignore the userID for the config with ID 1, which is the global default config
            if(!(V::key('id', V::intVal())->validate($criteria) && $criteria['id'] == 1)) {
                $qb->join('sc.sensors', 's')
                    ->join('s.division', 'd')
                    ->andWhere(':userid MEMBER OF d.users')
                    ->setParameter(':userid', $criteria['userID']);
            }
        }
        if(V::key('id', V::intVal())->validate($criteria)) {
            $qb->andWhere('sc.id = :id')
                ->setParameter('id', $criteria['id']);
            return $qb->getQuery()->getSingleResult()->getState();
        } else {
            // Don't return the default sensor config when listing all configs
            $qb->andWhere('sc.id != 1');
            $configs = array();
            foreach($qb->getQuery()->getResult() as $config) {
                $configs[] = $config->getState();
            }
            return $configs;
        }
	}

    /**
     * Creates and persists a new SensorConfig object.
     * The following parameters are required:
     * - sensor: ID of the sensor this config applies for
     * - interval: Integer specifying the polling interval (1-200 min)
     * - recon: Boolean to activate/deactivate the recon mode
     * - kippoHoneypot: Boolean to activate/deactivate the kippo honeypot
     * - dionaeaHoneypot: Boolean to activate/deactivate the dionaea honeypot
     * - image: ID of a SensorImage/Firmware entity or null to use the system-wide standard
     *
     * @param stdClass $data
     * @return SensorConfig
     */
	public function create($data) {
		$this->assureAllowed('create');
        // Validation
        V::objectType()
            ->attribute('sensor', V::intVal())
            ->attribute('interval', V::intVal()->between(1, 200))
            ->attribute('recon', V::boolVal())
            ->attribute('kippoHoneypot', V::boolVal())
            ->attribute('dionaeaHoneypot', V::boolVal())
            ->attribute('image', V::optional(V::intVal()))
            ->check($data);
        // Persistence
		$sensor = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\Sensor')->find($data->sensor);
        V::objectType()->check($sensor);
		$config = new SensorConfig();
		$config->setUpdateInterval($data->interval)
			->setRecon($data->recon)
			->setKippoHoneypot($data->kippoHoneypot)
			->setDionaeaHoneypot($data->dionaeaHoneypot)
			->addSensor($sensor);
        if(V::intVal()->not(V::equals(0))->validate($data->image)) {
            $image = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\SensorImage')->find($data->image);
            V::objectType()->check($image);
            $config->setImage($image);
        }
		$em = $this->getEntityManager();
		$em->persist($config);
		$em->flush();
		return $config;
	}

    /**
     * Updates an existing SensorConfig object.
     * The following parameteres are required:
     * - interval: Integer specifying the polling interval (1-200 min)
     * - recon: Boolean to activate/deactivate the recon mode
     * - kippoHoneypot: Boolean to activate/deactivate the kippo honeypot
     * - dionaeaHoneypot: Boolean to activate/deactivate the dionaea honeypot
     * - image: ID of a SensorImage/Firmware entity or null to use the system-wide standard
     *
     * @param int $id
     * @param stdClass $data
     * @return SensorConfig
     * @throws ForbiddenException
     * @throws \Exception
     */
	public function update($id, $data) {
		$this->assureAllowed('update');
        // Validation
        V::intVal()->check($id);
        // Only allow admin users to change the system-wide default configuration
        if($id == 1 and $_SESSION['user']['role'] != User::ROLE_ADMIN) throw new ForbiddenException();
        V::objectType()
            ->attribute('interval', V::intVal()->between(1, 200))
            ->attribute('recon', V::boolVal())
            ->attribute('kippoHoneypot', V::boolVal())
            ->attribute('dionaeaHoneypot', V::boolVal())
            ->attribute('image', V::optional(V::intVal()))
            ->check($data);
		$config = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\SensorConfig')->find($id);
        V::objectType()->check($config);
        $config->setUpdateInterval($data->interval);
        $config->setRecon($data->recon);
        $config->setKippoHoneypot($data->kippoHoneypot);
        $config->setDionaeaHoneypot($data->dionaeaHoneypot);
        if(V::intVal()->not(V::equals(0))->validate($data->image)) {
			$image = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\SensorImage')->find($data->image);
            V::objectType()->check($image);
			$config->setImage($image);
		} else {
            // Never reset the default image
            if($id != 1) $config->setImage(null);
        }
		$this->getEntityManager()->flush();
        return $config;
	}
	
	public function delete($id) {
		$this->assureAllowed('delete');
        // Validation
        V::intVal()->not(V::equals(1))->check($id);
        // Persistence
		$em = $this->getEntityManager();
		$repo = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\SensorConfig');
		$config = $repo->find($id);
        V::objectType()->check($config);
		$defconfig = $repo->find(1);
        foreach($config->removeAllSensors() as $sensor) {
            $sensor->setConfiguration($defconfig);
        }
        $em->remove($config);
        $em->flush();
	}
}