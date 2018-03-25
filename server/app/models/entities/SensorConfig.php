<?php
namespace HoneySens\app\models\entities;

/**
 * @Entity
 * @Table(name="configs")
 */
class SensorConfig {
	
	/**
	 * @Id
	 * @Column(type="integer")
	 * @GeneratedValue 
	 */
	protected $id;
	
	/**
	 * The sensor this config applies to.
	 * Set to NULL to apply the configuration to all sensors that aren't configured specifically.
	 * 
	 * @OneToMany(targetEntity="HoneySens\app\models\entities\Sensor", mappedBy="configuration")
	 */
	protected $sensors;
	
	/**
	 * @ManyToOne(targetEntity="HoneySens\app\models\entities\SensorImage")
	 */
	protected $image;
	
	/**
	 * Recon mode allows the sensor to evaluate all incoming traffic and generate events for any traffic received
	 * on otherwise closed ports.
	 * 
	 * @Column(type="boolean")
	 */
	protected $recon;
	
	/**
	 * Enables or disables the kippo SSH medium-interaction honeypot.
	 * 
	 * @Column(type="boolean")
	 */
	protected $kippoHoneypot;
	
	/**
	 * Enables or disables the dionaea low-interaction honeypot.
	 * 
	 * @Column(type="boolean")
	 */
	protected $dionaeaHoneypot;
	
	/**
	 * Update interval in minutes.
	 * 
	 * @Column(type="integer")
	 */
	protected $updateInterval;
	
    /**
     * Get id
     *
     * @return integer 
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set recon mode
     *
     * @param boolean $state
     * @return SensorConfig
     */
    public function setRecon($state) {
        $this->recon = $state;
        return $this;
    }

    /**
     * Get passive scan mode
     *
     * @return boolean
     */
    public function getRecon() {
        return $this->recon;
    }
	
    /**
     * Enable/disable kippo honeypot
     *
     * @param boolean $state
     * @return SensorConfig
     */
    public function setKippoHoneypot($state) {
        $this->kippoHoneypot = $state;
        return $this;
    }

    /**
     * Get kippo honeypot state
     *
     * @return boolean
     */
    public function getKippoHoneypot() {
        return $this->kippoHoneypot;
    }
	
    /**
     * Enable/disable dionaea honeypot
     *
     * @param boolean $state
     * @return SensorConfig
     */
    public function setDionaeaHoneypot($state) {
        $this->dionaeaHoneypot = $state;
        return $this;
    }

    /**
     * Get dionaea honeypot state
     *
     * @return boolean
     */
    public function getDionaeaHoneypot() {
        return $this->dionaeaHoneypot;
    }

    /**
     * Set updateInterval
     *
     * @param integer $updateInterval
     * @return SensorConfig
     */
    public function setUpdateInterval($updateInterval) {
        $this->updateInterval = $updateInterval;
        return $this;
    }

    /**
     * Get updateInterval
     *
     * @return integer 
     */
    public function getUpdateInterval() {
        return $this->updateInterval;
    }

    /**
     * Add sensor
     *
     * @param \HoneySens\app\models\entities\Sensor $sensor
     * @return SensorConfig
     */
    public function addSensor(\HoneySens\app\models\entities\Sensor $sensor) {
        $this->sensors[] = $sensor;
		$sensor->setConfiguration($this);
        return $this;
    }
	
	/**
	 * Remove sensor
	 * 
	 * @param \HoneySens\app\models\entities\Sensor $sensor
	 * @return SensorConfig
	 */
	public function removeSensor(\HoneySens\app\models\entities\Sensor $sensor) {
		$this->sensors->removeElement($sensor);
		$sensor->setConfiguration(null);
		return $this;
	}
	
	/**
	 * Remove all sensors
	 * 
	 * @return Sensor the sensors that got removed
	 */
	public function removeAllSensors() {
		// clone $this->getSensors() ???
		$sensors = array();
		foreach($this->getSensors() as $sensor) {
			$sensors[] = $sensor;
			$this->removeSensor($sensor);
		}
		return $sensors;
	}

    /**
     * Get sensors
     *
     * @return \HoneySens\app\models\entities\Sensor 
     */
    public function getSensors() {
        return $this->sensors;
    }
	
	/**
	 * Set sensor image
	 * 
	 * @return SensorConfig
	 */
	public function setImage(\HoneySens\app\models\entities\SensorImage $image = null) {
		$this->image = $image;
		return $this;
	}
	
	/**
	 * Get sensor image
	 * 
	 * @return \HoneySens\app\models\entities\SensorImage
	 */
	public function getImage() {
		return $this->image;
	}

	public function getState() {
		$sensors = array();
		foreach($this->getSensors() as $sensor) {
			$sensors[] = $sensor->getId();
		}
		$image = $this->getImage() ? $this->getImage()->getId() : null;
		$imageName = $this->getImage() ? $this->getImage()->getName() . ' ' . $this->getImage()->getVersion() : null;
		return array(
			'id' => $this->getId(),
			'sensors' => $sensors,
			'image' => $image,
			'imageName' => $imageName,
			'interval' => $this->getUpdateInterval(),
			'recon' => $this->getRecon(),
			'kippoHoneypot' => $this->getKippoHoneypot(),
			'dionaeaHoneypot' => $this->getDionaeaHoneypot()
		);
	}
}
