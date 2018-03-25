<?php
namespace HoneySens\app\models;

use HoneySens\app\controllers;

class EntityUpdateService {

    /**
     * Used to calculate an array of entities that have been changed since the given timestamp.
     *
     * @param $em EntityManager instance
     * @param $beanstalk Beanstalk instance
     * @param $config ConfigParser instance
     * @param $ts UNIX timestamp
     * @param $attributes array An optional array of attributes that is passed on to all affected controller methods
     * @return array
     */
	public function getUpdatedEntities($em, $beanstalk, $config, $ts, $attributes = array()) {
		$result = array();
		$timestamp = new \DateTime('@' . $ts);
		$timestamp->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		$rsm = new \Doctrine\ORM\Query\ResultSetMapping();
		$rsm->addScalarResult('table_name', 'name');
		$query = $em->createNativeQuery('SELECT table_name FROM last_updates WHERE timestamp >= ?', $rsm);
		$query->setParameter(1, $timestamp, "datetime");
		$lastUpdates = $query->getResult();
		foreach($lastUpdates as $table) {
			switch($table['name']) {
				case 'configs':
					$controller = new controllers\Sensorconfigs($em, $beanstalk, $config);
					$result['configs'] = $controller->get($attributes);
					$result['defaultconfig'] = $controller->get(array('id' => 1));
					break;
				case 'images':
					$controller = new controllers\Sensorimages($em, $beanstalk, $config);
					$result['images'] = $controller->get($attributes);
					break;
				case 'sensors':
					$controller = new controllers\Sensors($em, $beanstalk, $config);
					$result['sensors'] = $controller->get($attributes);
					break;
				case 'users':
					$controller = new controllers\Users($em, $beanstalk, $config);
					$result['users'] = $controller->get($attributes);
					break;
				case 'divisions':
					$controller = new controllers\Divisions($em, $beanstalk, $config);
					$result['divisions'] = $controller->get($attributes);
					break;
				case 'contacts':
					$controller = new controllers\Contacts($em, $beanstalk, $config);
					$result['contacts'] = $controller->get($attributes);
					break; 
				case 'settings':
					$controller = new controllers\Settings($em, $beanstalk, $config);
					$result['settings'] = $controller->get($attributes);
					break;
				case 'event_filters':
					$controller = new controllers\Eventfilters($em, $beanstalk, $config);
					$result['event_filters'] = $controller->get($attributes);
					break;
                case 'stats':
                    $controller = new controllers\Stats($em, $beanstalk, $config);
                    $result['stats'] = $controller->get(array(
                        'userID' => $attributes['userID'],
                        'year' => $attributes['stats_year'],
                        'month' => $attributes['stats_month'],
                        'division' => $attributes['stats_division']));
                    break;
			}
		}
		return $result;
	}
}