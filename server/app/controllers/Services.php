<?php
namespace HoneySens\app\controllers;
use FileUpload\PathResolver;
use FileUpload\FileSystem;
use FileUpload\FileUpload;
use FileUpload\File;

use HoneySens\app\models\entities\Service;
use HoneySens\app\models\entities\ServiceRevision;
use HoneySens\app\models\exceptions\BadRequestException;
use HoneySens\app\models\exceptions\NotFoundException;
use HoneySens\app\models\RegistryService;
use Respect\Validation\Rules\HexRgbColor;
use Respect\Validation\Validator as V;

class Services extends RESTResource {

    const CREATE_ERROR_NONE = 0;
    const CREATE_ERROR_INVALID_IMAGE = 1;
    const CREATE_ERROR_INVALID_METADATA = 2;
    const CREATE_ERROR_DUPLICATE = 3;

    static function registerRoutes($app, $em, $beanstalk, $config, $messages) {
        $app->get('/api/services(/:id)/', function($id = null) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Services($em, $beanstalk, $config);
            $criteria = array();
            $criteria['id'] = $id;
            $result = $controller->get($criteria);
            echo json_encode($result);
        });

        $app->get('/api/services/registry', function() use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Services($em, $beanstalk, $config);
            if($controller->getRegistryStatus()) echo json_encode([]);
            else throw new NotFoundException();
        });

        $app->get('/api/services/:id/status', function($id = null) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Services($em, $beanstalk, $config);
            $result = $controller->getStatus($id);
            echo json_encode($result);
        });

        $app->post('/api/services', function() use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Services($em, $beanstalk, $config);
            $serviceData = $controller->create($_FILES['service']);
            echo json_encode($serviceData);
        });

        $app->put('/api/services/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Services($em, $beanstalk, $config);
            $request = $app->request()->getBody();
            V::json()->check($request);
            $serviceData = json_decode($request);
            $service = $controller->update($id, $serviceData);
            echo json_encode($service->getState());
        });

        $app->delete('/api/services/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Services($em, $beanstalk, $config);
            $controller->delete($id);
            echo json_encode([]);
        });
    }

    /**
     * Fetches services from the DB by various criteria:
     * - id: return the service with the given id
     * If no criteria are given, all services are returned.
     *
     * @param array $criteria
     * @return array
     */
    public function get($criteria) {
        $this->assureAllowed('get');
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('s')->from('HoneySens\app\models\entities\Service', 's');
        if(V::key('id', V::intVal())->validate($criteria)) {
            $qb->andWhere('s.id = :id')
                ->setParameter('id', $criteria['id']);
            return $qb->getQuery()->getSingleResult()->getState();
        } else {
            $services = array();
            foreach($qb->getQuery()->getResult() as $service) {
                $services[] = $service->getState();
            }
            return $services;
        }
    }

    /**
     * Queries the registry for availability
     *
     * @return bool
     */
    public function getRegistryStatus() {
        $this->assureAllowed('get');
        $registryService = new RegistryService($this->getConfig());
        return $registryService->isAvailable();
    }

    /**
     * Used to query the individual service status from the registry. This basically lists for each revision
     * registered in the db whether there is a matching template registered in the docker service registry.
     *
     * @param $id
     * @throws NotFoundException
     * @return array;
     */
    public function getStatus($id) {
        $this->assureAllowed('get');
        $service = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\Service')->find($id);
        if(!V::objectType()->validate($service)) throw new NotFoundException();
        $registryService = new RegistryService($this->getConfig());
        $tags = $registryService->getTags($service->getRepository());
        V::arrayType()->check($tags);
        $result = array();
        foreach($service->getRevisions() as $revision) {
            $result[$revision->getId()] = in_array($revision->getRevision(), $tags);
        }
        return $result;
    }

    /**
     * Creates and persists a new service (or revision).
     * Binary file data is expected as parameter, chunked uploads are supported.
     *
     * @param string $data
     * @return array
     */
    public function create($data) {
        $this->assureAllowed('create');
        $em = $this->getEntityManager();
        $pathresolver = new PathResolver\Simple(realpath(APPLICATION_PATH . '/../data/upload'));
        $fs = new FileSystem\Simple();
        $fileUpload = new FileUpload($data, $_SERVER);
        $fileUpload->setPathResolver($pathresolver);
        $fileUpload->setFileSystem($fs);
        $fileUpload->addCallback('completed', function(File $file) {
            global $em;
            // Check registry availability
            $registryService = new RegistryService($this->getConfig());
            // Check archive content
            exec('/bin/tar tzf ' . escapeshellarg($file->path), $output);
            if(!$registryService->isAvailable() && !in_array('service.tar', $output) || !in_array('metadata.xml', $output)) {
                $this->removeFile($file);
                throw new BadRequestException(Services::CREATE_ERROR_INVALID_IMAGE);
            }
            // Check metadata
            $output = array();
            exec('/bin/tar xzf ' . escapeshellarg($file->path) . ' metadata.xml -O', $output);
            try {
                $metadata = new \SimpleXMLElement(implode($output));
            } catch(\Exception $e) {
                $this->removeFile($file);
                throw new BadRequestException(Services::CREATE_ERROR_INVALID_METADATA);
            }
            V::objectType()
                ->attribute('name')
                ->attribute('repository')
                ->attribute('description')
                ->attribute('revision')
                ->attribute('revisionDescription')
                ->check($metadata);
            // Check for duplicates
            $service = $em->getRepository('HoneySens\app\models\entities\Service')
                ->findOneBy(array('name' => (string) $metadata->name, 'repository' => (string) $metadata->repository));
            if(V::objectType()->validate($service) && $service->hasRevision((string) $metadata->revision)) {
                $this->removeFile($file);
                throw new BadRequestException(Services::CREATE_ERROR_DUPLICATE);
            }
            // Persistence
            $this->getBeanstalkService()->putServiceRegistryJob(sprintf('%s:%s', (string) $metadata->repository, (string) $metadata->revision), $file->path);
            $serviceRevisionModel = new ServiceRevision();
            $serviceRevisionModel->setRevision((string) $metadata->revision)
                ->setDescription((string) $metadata->revisionDescription);
            $serviceModel = new Service();
            $serviceModel->setName((string) $metadata->name)
                ->setDescription((string) $metadata->description)
                ->setRepository((string) $metadata->repository)
                ->addRevision($serviceRevisionModel)
                ->setDefaultRevision($serviceRevisionModel);
            $em->persist($serviceRevisionModel);
            $em->persist($serviceModel);
            $em->flush();
            $file->service = $serviceModel->getState();
        });
        list($files, $headers) = $fileUpload->processAll();
        foreach($headers as $header => $value) {
            header($header . ': ' . $value);
        }
        // The array with the 'files' key is required by the fileupload plugin used for the frontend
        return array('files' => $files);
    }

    /**
     * Updates an existing service.
     *
     * The following parameters are recognized:
     * - default_revision: A division this service defaults to
     *
     * @param int $id
     * @param \stdClass $data
     * @return Service
     */
    public function update($id, $data) {
        $this->assureAllowed('update');
        // Validation
        V::intVal()->check($id);
        V::objectType()
            ->attribute('default_revision', V::intVal())
            ->check($data);
        // Persistence
        $em = $this->getEntityManager();
        $service = $em->getRepository('HoneySens\app\models\entities\Service')->find($id);
        V::objectType()->check($service);
        $defaultRevision = $em->getRepository('HoneySens\app\models\entities\ServiceRevision')->find($data->default_revision);
        V::objectType()->check($defaultRevision);
        $service->setDefaultRevision($defaultRevision);
        $em->flush();
        return $service;
    }

    public function delete($id) {
        $this->assureAllowed('delete');
        // Validation
        V::intVal()->check($id);
        $em = $this->getEntityManager();
        $service = $em->getRepository('HoneySens\app\models\entities\Service')->find($id);
        V::objectType()->check($service);
        // Remove service from the registry
        $registryService = new RegistryService($this->getConfig());
        $registryService->removeRepository($service->getRepository());
        // Remove default revision
        $defaultRevision = $service->getDefaultRevision();
        if($defaultRevision != null) {
            $em->remove($defaultRevision);
            $service->setDefaultRevision(null);
        }
        // Finally remove service from the db
        $em->remove($service);
        $em->flush();
    }

    /**
     * Attempts to remove an uploaded file if it exists
     *
     * @param \FileUpload\File $file
     */
    private function removeFile($file) {
        if(file_exists($file->path)) exec('rm ' . escapeshellarg($file->path));
    }
}