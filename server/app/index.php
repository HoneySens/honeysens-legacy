<?php

require_once dirname(__FILE__) . '/../app/Bootstrap.php';

initClassLoading();
$config = initConfig();
$app = initSlim($config);
$messages = array();
$em = initDoctrine($config);
initDBSchema($messages, $em);
initDBEventManager($em);
initPHPSecLib();
$beanstalk = initBeanstalk($config);
initRoutes($app, $em, $beanstalk, $config, $messages);
initSession();
$app->run();
