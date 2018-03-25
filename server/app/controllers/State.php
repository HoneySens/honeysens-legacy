<?php
namespace HoneySens\app\controllers;

use HoneySens\app\models\EntityUpdateService;
use Respect\Validation\Validator as V;

class State extends RESTResource {

    static function registerRoutes($app, $em, $beanstalk, $config, $messages) {
        // Returns an array containing full current application state information (e.g. all entities)
        // that is accessible for the given user.
        $app->get('/api/state', function() use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new State($em, $beanstalk, $config);
            // Set $userID to null for global admin users to avoid user-specific filtering
            $userID = $controller->getSessionUserID();
            $ts = $app->request()->get('ts');
            $lastEventId = $app->request()->get('last_id');
            $stateParams = $app->request()->get();
            $stateParams['userID'] = $userID;
            V::optional(V::intVal())->check($ts);
            V::optional(V::oneOf(V::intVal(), V::equals('null')))->check($lastEventId);
            $now = new \DateTime();
            if($ts == null) {
                // Return full state
                $state = $controller->get($userID);
            } else {
                // Return incremental state
                if($lastEventId) {
                    $eventsController = new Events($em, $beanstalk, $config);
                    $events = $eventsController->get(array_merge($stateParams, array('lastID' => $lastEventId)));
                } else {
                    $events = (new Events($em, $beanstalk, $config))->get($stateParams);
                }
                $updateService = new EntityUpdateService();
                $state = $updateService->getUpdatedEntities($em, $beanstalk, $config, $ts, $stateParams);
                $state['events'] = $events;
            }
            $state['timestamp'] = $now->format('U');
            echo json_encode($state);
        });
    }

    // TODO add permission resource
    public function get($userID) {
        $this->assureAllowed('get');
        $em = $this->getEntityManager();
        $beanstalk = $this->getBeanstalkService();
        $config = $this->getConfig();
        $configController = new Sensorconfigs($em, $beanstalk, $config);

        // If an update is required, prioritize that. We can't guarantee that getting all the other data will be successful otherwise.
        try { $system = (new System($em, $beanstalk, $config))->get(); } catch(\Exception $e) { $system = array(); }
        if($system['update']) {
            return array(
                'user' => $_SESSION['user'],
                'images' => array(),
                'configs' => array(),
                'defaultconfig' => array(),
                'sensors' => array(),
                'events' => array(),
                'event_filters' => array(),
                'users' => array(),
                'divisions' => array(),
                'contacts' => array(),
                'services' => array(),
                'settings' => array(),
                'system' => $system,
                'stats' => array()
            );
        }

        try { $images = (new Sensorimages($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $images = array(); }
        try { $configs = $configController->get(array('userID' => $userID)); } catch(\Exception $e) { $configs = array(); }
        try { $defaultconfig = $configController->get(array('id' => 1)); } catch(\Exception $e) { $defaultconfig = array(); }
        try { $sensors = (new Sensors($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $sensors = array(); }
        try { $events = (new Events($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $events = array(); }
        try { $event_filters = (new Eventfilters($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $event_filters = array(); }
        try { $users = (new Users($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $users = array(); }
        try { $divisions = (new Divisions($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $divisions = array(); }
        try { $contacts = (new Contacts($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $contacts = array(); }
        try { $services = (new Services($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $services = array(); }
        try { $settings = (new Settings($em, $beanstalk, $config))->get(); } catch(\Exception $e) { $settings = array(); }
        try { $stats = (new Stats($em, $beanstalk, $config))->get(array('userID' => $userID)); } catch(\Exception $e) { $stats = array(); }

        return array(
            'user' => $_SESSION['user'],
            'images' => $images,
            'configs' => $configs,
            'defaultconfig' => $defaultconfig,
            'sensors' => $sensors,
            'events' => $events,
            'event_filters' => $event_filters,
            'users' => $users,
            'divisions' => $divisions,
            'contacts' => $contacts,
            'services' => $services,
            'settings' => $settings,
            'system' => $system,
            'stats' => $stats
        );
    }
}
