<?php
namespace HoneySens\app\controllers;
use HoneySens\app\models\entities\SensorImage;
use FileUpload\PathResolver;
use FileUpload\FileSystem;
use FileUpload\FileUpload;
use FileUpload\File;
use HoneySens\app\models\exceptions\BadRequestException;
use HoneySens\app\models\exceptions\NotFoundException;
use Respect\Validation\Validator as V;

class Sensorimages extends RESTResource {

    const CREATE_ERROR_NONE = 0;
    const CREATE_ERROR_INVALID_IMAGE = 1;
    const CREATE_ERROR_INVALID_METADATA = 2;
    const CREATE_ERROR_DUPLICATE = 3;

    static function registerRoutes($app, $em, $beanstalk, $config, $messages) {
        $app->get('/api/sensorimages(/:id)/', function($id = null) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorimages($em, $beanstalk, $config);
            $criteria = array();
            $criteria['id'] = $id;
            try {
                $result = $controller->get($criteria);
            } catch(\Exception $e) {
                throw new NotFoundException();
            }
            echo json_encode($result);
        });

        $app->get('/api/sensorimages/download/by-sensor/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorimages($em, $beanstalk, $config);
            try {
                $controller->downloadBySensor($id);
            } catch(\Exception $e) {
                throw new NotFoundException();
            }
        });

        $app->get('/api/sensorimages/download/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorimages($em, $beanstalk, $config);
            try {
                $controller->download($id);
            } catch(\Exception $e) {
                throw new NotFoundException();
            }
        });

        $app->post('/api/sensorimages', function() use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorimages($em, $beanstalk, $config);
            $imageData = $controller->create($_FILES['image']);
            echo json_encode($imageData);
        });

        $app->put('/api/sensorimages/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorimages($em, $beanstalk, $config);
            $request = $app->request()->getBody();
            V::json()->check($request);
            $imageData = json_decode($request);
            $image = $controller->update($id, $imageData);
            echo json_encode($image->getState());
        });

        $app->delete('/api/sensorimages/:id', function($id) use ($app, $em, $beanstalk, $config, $messages) {
            $controller = new Sensorimages($em, $beanstalk, $config);
            $controller->delete($id);
            echo json_encode([]);
        });
    }
	
	/**
     * Fetches SensorImages from the DB by various criteria:
     * - id: return the SensorImage object with the given id
     * If no criteria are given, all SensorImages are returned.
     *
	 * @param array $criteria
	 * @return array
	 */
	public function get($criteria) {
		$this->assureAllowed('get');
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('si')->from('HoneySens\app\models\entities\SensorImage', 'si');
        if(V::key('id', V::intVal())->validate($criteria)) {
            $qb->andWhere('si.id = :id')
                ->setParameter('id', $criteria['id']);
            return $qb->getQuery()->getSingleResult()->getState();
        } else {
            $images = array();
            foreach($qb->getQuery()->getResult() as $image) {
                $images[] = $image->getState();
            }
            return $images;
        }
	}

    /**
     * Creates and persists a new SensorImage object.
     * It expects binary file data as parameter and supports chunked uploads.
     *
     * @param string $fileData
     * @return array
     */
	public function create($fileData) {
		$this->assureAllowed('create');
		$em = $this->getEntityManager();
		$pathresolver = new PathResolver\Simple(realpath(APPLICATION_PATH . '/../data/upload'));
		$fs = new FileSystem\Simple();
		$fileupload = new FileUpload($fileData, $_SERVER);
		$fileupload->setPathResolver($pathresolver);
		$fileupload->setFileSystem($fs);
		$fileupload->addCallback('completed', function(File $file) {
            // Validation
			global $em;
			// Check archive content
			exec('/bin/tar tzf ' . escapeshellarg($file->path), $output);
			if(!in_array('firmware.img', $output) || !in_array('metadata.xml', $output)) {
				if(file_exists($file->path)) exec('rm ' . escapeshellarg($file->path));
				$file->completed = false;
				$file->error = Sensorimages::CREATE_ERROR_INVALID_IMAGE;
                throw new BadRequestException();
			}
			// Check metadata
			$output = array();
			exec('/bin/tar xzf ' . escapeshellarg($file->path) . ' metadata.xml -O', $output);
			try {
				$metadata = new \SimpleXMLElement(implode($output));
			} catch(\Exception $e) {
				if(file_exists($file->path)) exec('rm ' . escapeshellarg($file->path));
				$file->completed = false;
				$file->error = Sensorimages::CREATE_ERROR_INVALID_METADATA;
				throw new BadRequestException();
			}
            V::objectType()
                ->attribute('name')
                ->attribute('version')
                ->attribute('description')
                ->check($metadata);
			// Check for duplicates
            $image = $em->getRepository('HoneySens\app\models\entities\SensorImage')
                ->findOneBy(array('name' => (string) $metadata->name, 'version' => (string) $metadata->version));
            if(V::objectType()->validate($image)) {
                exec('rm ' . $file->path);
                $file->completed = false;
                $file->error = Sensorimages::CREATE_ERROR_DUPLICATE;
                throw new BadRequestException();
            }
			// Persistence
			$fileName = preg_replace('/\s+/', '-', strtolower((string) $metadata->name)) . '-' . preg_replace('/\s+/', '-', strtolower((string) $metadata->version)) . '.tar.gz';  
			exec('mv ' . escapeshellarg($file->path) . ' ' . realpath(APPLICATION_PATH . '/../data/firmware/') . '/' . $fileName);
			$image = new SensorImage();
			$image->setName((string) $metadata->name)
				->setVersion((string) $metadata->version)
				->setDescription((string) $metadata->description)
				->setChangelog('')
				->setFile($fileName);
			$em->persist($image);
			$em->flush();
			$file->image = $image->getState();
		});
		list($files, $headers) = $fileupload->processAll();
		foreach($headers as $header => $value) {
			header($header . ': ' . $value);
		}
		return array('files' => $files);
	}

    /**
     * Updates an existing SensorImage object.
     * Only the conversion status attribute (integer, 0-3) can be changed.
     * This method also triggers new beanstalk jobs if necessary.
     *
     * @param int $id
     * @param stdClass $data
     * @return SensorImage
     */
	public function update($id, $data) {
		$this->assureAllowed('update');
        // Validation
        V::intVal()->check($id);
        V::objectType()
            ->attribute('conversionStatus', V::intVal()->between(0, 3))
            ->check($data);
        // Persistence
		$image = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\SensorImage')->find($id);
        V::objectType()->check($image);
        if($image->getConversionStatus() == $image::CONVERSION_UNDONE && $data->conversionStatus == $image::CONVERSION_SCHEDULED) {
            $image->setConversionStatus($image::CONVERSION_SCHEDULED);
            $this->getEntityManager()->flush();
            $this->getBeanstalkService()->putImageConversionJob($image);
        } elseif($image->getConversionStatus() == $image::CONVERSION_SCHEDULED && $data->conversionStatus == $image::CONVERSION_UNDONE) {
            $image->setConversionStatus($image::CONVERSION_UNDONE);
            $this->getEntityManager()->flush();
        }
	}

	public function delete($id) {
		$this->assureAllowed('delete');
        // Validation
        V::intVal()->check($id);
        // Persistence
		$em = $this->getEntityManager();
		$image = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\SensorImage')->find($id);
        V::objectType()->check($image);
		foreach($this->getEntityManager()->getRepository('HoneySens\app\models\entities\SensorConfig')->findAll() as $config) {
			if($config->getImage() == $image) {
                // Image is in use
                throw new BadRequestException();
			}
		}
		$em->remove($image);
		$firmwarePath = realpath(APPLICATION_PATH . '/../data/firmware/') . '/' . $image->getFile();
		if(file_exists($firmwarePath)) exec('rm ' . $firmwarePath);
		$convertedFirmwarePath = realpath(APPLICATION_PATH . '/../data/firmware/sd/') . '/' . $image->getConvertedFile();
		if(file_exists($convertedFirmwarePath)) exec('rm ' . $convertedFirmwarePath);
		$em->flush();
	}

    /**
     * Triggers the download routine for the given SensorImage id.
     *
     * @param $id
     */
	public function download($id) {
        // TODO This requires authentication!
		$image = $this->getEntityManager()->getRepository('HoneySens\app\models\entities\SensorImage')->find($id);
        V::objectType()->check($image);
        $filePath = realpath(APPLICATION_PATH . '/../data/firmware/' . $image->getFile());
        session_write_close();
        $this->offerFile($filePath, basename($filePath));
	}

    /**
     * Triggers the download routine for the current firmware version of the given Sensor id.
     *
     * @param int $id
     */
    public function downloadBySensor($id) {
        // TODO This requires authentication!
        $em = $this->getEntityManager();
        $sensor = $em->getRepository('HoneySens\app\models\entities\Sensor')->find($id);
        V::objectType()->check($sensor);
        if($sensor->getConfiguration()->getImage()) {
            $fileName = $sensor->getConfiguration()->getImage()->getFile();
        } else {
            $defaultconfig = $em->getRepository('HoneySens\app\models\entities\SensorConfig')->find(1);
            $fileName = $defaultconfig->getImage()->getFile();
        }
        $filePath = realpath(APPLICATION_PATH . '/../data/firmware/' . $fileName);
        $this->offerFile($filePath, basename($filePath));
    }
}