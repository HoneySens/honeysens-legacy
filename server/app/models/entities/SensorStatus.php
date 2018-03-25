<?php
namespace HoneySens\app\models\entities;

/**
 * @Entity
 * @Table(name="statuslogs")
 */
class SensorStatus {
	
	const STATUS_ERROR = 0;
	const STATUS_RUNNING = 1;
	const STATUS_UPDATE_PHASE1 = 2;
	const STATUS_INSTALL_PHASE1 = 3;
	const STATUS_UPDATEINSTALL_PHASE2 = 4;
	
	/**
	 * @Id
	 * @Column(type="integer")
	 * @GeneratedValue 
	 */
	protected $id;
	
	/**
	 * @ManyToOne(targetEntity="HoneySens\app\models\entities\Sensor", inversedBy="status")
	 */
	protected $sensor;
	
	/**
	 * @Column(type="datetime")
	 */
	protected $timestamp;
	
	/**
	 * @Column(type="integer")
	 */
	protected $status;
	
	/**
	 * @Column(type="string")
	 */
	protected $ip;
	
	/**
	 * @Column(type="integer")
	 */
	protected $freeMem;
	
	/**
	 * @Column(type="string")
	 */
	protected $swVersion;
	
    /**
     * Get id
     *
     * @return integer 
     */
    public function getId() {
        return $this->id;
    }
	
    /**
     * Set sensor
     *
     * @param \HoneySens\app\models\entities\Sensor $sensor
     * @return SensorStatus
     */
    public function setSensor(\HoneySens\app\models\entities\Sensor $sensor = null) {
        $this->sensor = $sensor;
        return $this;
    }

    /**
     * Get sensor
     *
     * @return \HoneySens\app\models\entities\Sensor 
     */
    public function getSensor() {
        return $this->sensor;
    }
	
    /**
     * Set timestamp
     *
     * @param \DateTime $timestamp
     * @return SensorStatus
     */
    public function setTimestamp(\DateTime $timestamp) {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Get timestamp
     *
     * @return \DateTime 
     */
    public function getTimestamp() {
        return $this->timestamp;
    }
    
    /**
     * Set current status
     * 
     * @param integer $status
     * @return \HoneySens\app\models\entities\SensorStatus
     */
    public function setStatus($status) {
    	$this->status = $status;
    	return $this;
    }
    
    /**
     * Get current status
     * 
     * @return integer
     */
    public function getStatus() {
    	return $this->status;
    }
	
	/**
	 * Set ip address
	 * 
	 * @param string $ip
	 * @return \HoneySens\app\models\entities\SensorStatus
	 */
	public function setIP($ip) {
		$this->ip = $ip;
		return $this;
	}
	
	/**
	 * Get ip address
	 * 
	 * @return string
	 */
	public function getIP() {
		return $this->ip;
	}

	/**
	 * Set free memory in MB
	 * 
	 * @param integer $freeMem
	 * @return SensorStatus
	 */	
	public function setFreeMem($freeMem) {
		$this->freeMem = $freeMem;
		return $this;
	}
	
	/**
	 * Get free memory in MB
	 * 
	 * @return integer
	 */
	public function getFreeMem() {
		return $this->freeMem;
	}
	
	/**
	 * Set sensor software version
	 * 
	 * @param string $swVersion
	 * @return SensorStatus
	 */
	public function setSWVersion($swVersion) {
		$this->swVersion = $swVersion;
		return $this;
	}
	
	/**
	 * Get sensor software version
	 */
	public function getSWVersion() {
		return $this->swVersion;
	}
	
	public function getState() { 
		return array(
			'id' => $this->getId(),
			'sensor' => $this->getSensor()->getId(),
			'timestamp' => $this->getTimestamp()->format('U'),
			'status' => $this->getStatus(),
			'ip' => $this->getIP(),
			'free_mem' => $this->getFreeMem(),
			'sw_version' => $this->getSWVersion()
		);
	}
}