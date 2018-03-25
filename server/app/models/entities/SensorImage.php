<?php
namespace HoneySens\app\models\entities;

/**
 * @Entity
 * @Table(name="images")
 */
class SensorImage {
	
	const CONVERSION_UNDONE = 0;
	const CONVERSION_SCHEDULED = 1;
	const CONVERSION_RUNNING = 2;
	const CONVERSION_DONE = 3;
	
	/**
	 * @Id
	 * @Column(type="integer")
	 * @GeneratedValue 
	 */
	protected $id;
	
	/**
	 * The name of this sensor image
	 * 
	 * @Column(type="string")
	 */
	protected $name;
	
	/**
	 * Version string of this image
	 * 
	 * @Column(type="string")
	 */
	protected $version;
	
	/**
	 * A short description of this image
	 * 
	 * @Column(type="string")
	 */
	protected $description;
	
	/**
	 * The long description of changes that occured within this version
	 * 
	 * @Column(type="string")
	 */
	protected $changelog;
	
	/**
	 * Name of the image file on disk
	 * 
	 * @Column(type="string")
	 */
	protected $file;
	
	/**
	 * Status of the conversion process to an SD image
	 * 
	 * @Column(type="integer")
	 */
	protected $conversionStatus = 0;
	
    /**
     * Get id
     *
     * @return integer 
     */
    public function getId() {
        return $this->id;
    }
	
    /**
     * Set name
     *
     * @param string $name
     * @return SensorImage
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }
	
    /**
     * Set version
     *
     * @param string $version
     * @return SensorImage
     */
    public function setVersion($version) {
        $this->version = $version;
        return $this;
    }

    /**
     * Get version
     *
     * @return string
     */
    public function getVersion() {
        return $this->version;
    }
	
    /**
     * Set description
     *
     * @param string $description
     * @return SensorImage
     */
    public function setDescription($description) {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }
	
    /**
     * Set change log
     *
     * @param string $changelog
     * @return SensorImage
     */
    public function setChangelog($changelog) {
        $this->changelog = $changelog;
        return $this;
    }

    /**
     * Get change log
     *
     * @return string
     */
    public function getChangelog() {
        return $this->changelog;
    }
	
    /**
     * Set file name
     *
     * @param string $file
     * @return SensorImage
     */
    public function setFile($file) {
        $this->file = $file;
        return $this;
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFile() {
        return $this->file;
    }
	
	/**
	 * Set conversion status
	 * 
	 * @param integer $conversionStatus
	 * @return SensorImage
	 */
	public function setConversionStatus($conversionStatus) {
		$this->conversionStatus = $conversionStatus;
		return $this;
	}

	/**
	 * Get conversion status
	 * 
	 * @return integer
	 */
	public function getConversionStatus() {
		return $this->conversionStatus;
	}
	
	/**
	 * Returns the name of the converted image file that is
	 * ready to be writen onto a SD card
	 */
	public function getConvertedFile() {
		return preg_replace('/\s+/', '-', strtolower((string) $this->name)) . '-' . preg_replace('/\s+/', '-', strtolower((string) $this->version)) . '.img';
	}
		
	public function getState() {
		return array(
			'id' => $this->getId(),
			'name' => $this->getName(),
			'version' => $this->getVersion(),
			'description' => $this->getDescription(),
			'changelog' => $this->getChangelog(),
			'file' => $this->getFile(),
			'conversionStatus' => $this->getConversionStatus()
		);
	}
}