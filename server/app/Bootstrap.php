<?php
use \Doctrine\Common\ClassLoader;
use HoneySens\app\models\exceptions;
use \Respect\Validation\Exceptions\ValidationException;

defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__FILE__)));
set_include_path(implode(PATH_SEPARATOR, array(realpath(APPLICATION_PATH . '/lib'), get_include_path())));

function initSlim($appConfig) {
	// Compatibility to config versions < 0.2.0
	try {
		$debug = $appConfig->getBoolean('server', 'debug');
	} catch(\NoiseLabs\ToolKit\ConfigParser\Exception\NoOptionException $e) {
		$debug = true;
	}
	$app = new \Slim\Slim(array('templates.path' => APPLICATION_PATH . '/templates', 'debug' => $debug));
    // Set global error handler that translates exceptions into HTTP status codes
	$app->error(function(\Exception $e) use ($app) {
        switch(true) {
            case $e instanceof exceptions\ForbiddenException:
                $app->response->setStatus(403);
                echo json_encode(array('code' => $e->getCode()));
                break;
            case $e instanceof exceptions\NotFoundException:
                $app->response->setStatus(404);
                echo json_encode(array('code' => $e->getCode()));
                break;
            case $e instanceof exceptions\BadRequestException:
            case $e instanceof ValidationException:
                $app->response->setStatus(400);
                echo json_encode(array('code' => $e->getCode()));
                break;
			default:
				$app->response->setStatus(500);
				echo json_encode(array('error' => $e->getMessage()));
				break;
        }
	});
    // Global route conditions
    \Slim\Route::setDefaultConditions(array('id' => '\d+'));
	return $app;
}

function initClassLoading() {
    require_once('Slim/Slim.php');
    \Slim\Slim::registerAutoloader();
	require_once('Doctrine/Common/ClassLoader.php');
	$doctrineLoader = new ClassLoader('Doctrine');
	$doctrineLoader->register();
	$controllerLoader = new Psr4AutoloaderClass();
	$controllerLoader->register();
	$controllerLoader->addNamespace('HoneySens', APPLICATION_PATH . '/..');
	require_once('PHPMailer/PHPMailerAutoload.php');
	\Requests::register_autoloader();
}

function initConfig() {
	$config = new \NoiseLabs\ToolKit\ConfigParser\ConfigParser();
	$config->read(APPLICATION_PATH . '/../data/config.cfg');
	return $config;
}

function initDoctrine($appConfig) {
	$config = new \Doctrine\ORM\Configuration();
	$cache = new \Doctrine\Common\Cache\ArrayCache();
	$config->setMetadataCacheImpl($cache);
	$config->setQueryCacheImpl($cache);
	$config->setMetadataDriverImpl($config->newDefaultAnnotationDriver(APPLICATION_PATH . '/models/entities'));
	$config->setProxyDir(APPLICATION_PATH . '/../cache');
	$config->setAutoGenerateProxyClasses(true);
	$config->setProxyNamespace('HoneySens\Cache\Proxies');
    $config->addCustomDatetimeFunction('DAY', '\DoctrineExtensions\Query\Mysql\Day');
    $config->addCustomDatetimeFunction('MONTH', '\DoctrineExtensions\Query\Mysql\Month');
    $config->addCustomDatetimeFunction('YEAR', '\DoctrineExtensions\Query\Mysql\Year');
	return \Doctrine\ORM\EntityManager::create($appConfig['database'], $config);
}

function initDBSchema(&$messages, $em) {
    $systemController = new \HoneySens\app\controllers\System($em, null, null);
    $systemController->initDBSchema($messages, $em, true);
}

function initDBEventManager($em) {
	$em->getEventManager()->addEventSubscriber(new \HoneySens\app\models\EntityUpdateSubscriber());
}

function initPHPSecLib() {
	set_include_path(implode(PATH_SEPARATOR, array(realpath(APPLICATION_PATH . '/lib/phpseclib'), get_include_path())));
	require_once('File/X509.php');
	require_once('Math/BigInteger.php');
}

function initBeanstalk($config) {
	return new \HoneySens\app\models\BeanstalkService($config);
}

/**
 * URL route definitions
 *
 * @param $app Slim app object
 * @param $em Doctrine entity manager
 * @param $beanstalk Beanstalk instance
 * @param $config Instance of ConfigParser
 * @param $messages List of events that happened during initialization of the form array( array( 'severity' => 'info|warn', 'msg' => $msg ), ... )
 */
function initRoutes($app, $em, $beanstalk, $config, $messages) {
    // Deliver the web application
	$app->get('/', function() use ($app, $em, $beanstalk, $config, $messages) {
        // Render system messages encountered during initialization
	    if(count($messages) > 0) {
			$infoMsg = '';
			$warnMsg = '';
			foreach($messages as $message) {
				if($message['severity'] == 'info') $infoMsg .= $message['msg'] . '<br />';
				if($message['severity'] == 'warn') $warnMsg .= $message['msg'] . '<br />';
			}
			if($infoMsg) $app->flashNow('info', $infoMsg);
			if($warnMsg) $app->flashNow('warn', $warnMsg);
		}
		$app->render('layout.php');
	});

    // Initialize API
    \HoneySens\app\controllers\Certs::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Contacts::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Divisions::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Eventdetails::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Eventfilters::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Events::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Sensorconfigs::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Sensorimages::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Sensors::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Sensorstatus::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Services::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Sessions::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Settings::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\State::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Stats::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\System::registerRoutes($app, $em, $beanstalk, $config, $messages);
    \HoneySens\app\controllers\Users::registerRoutes($app, $em, $beanstalk, $config, $messages);
}

function initSession() {
	session_cache_limiter(false);
	session_start();
	if(!isset($_SESSION['authenticated']) || !isset($_SESSION['user'])) {
		$guestUser = new \HoneySens\app\models\entities\User();
		$guestUser->setRole(\HoneySens\app\models\entities\User::ROLE_GUEST);
		$_SESSION['authenticated'] = false;
		$_SESSION['user'] = $guestUser->getState();
	}
}

function getResourceInstance($resource, $em, $beanstalk, $config) {
	$class = 'HoneySens\app\controllers\\' . ucfirst($resource);
	if(!class_exists($class)) throw new Exception('Resource ' . $class . ' does not exist.');
	if(!is_subclass_of($class, 'HoneySens\app\controllers\RESTResource')) throw new Exception('Class ' . $class . ' is not a valid resource.');
	return new $class($em, $beanstalk, $config);
}
