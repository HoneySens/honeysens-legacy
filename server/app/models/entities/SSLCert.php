<?php
namespace HoneySens\app\models\entities;

/**
 * @Entity
 * @Table(name="certs")
 */
class SSLCert{
	
	/**
	 * @Id
	 * @Column(type="integer")
	 * @GeneratedValue 
	 */
	protected $id;
	
	/**
	 * @OneToOne(targetEntity="HoneySens\app\models\entities\Sensor", mappedBy="cert")
	 */
	protected $sensor;

	/**
	 * @Column(type="text")
	 */
	protected $content;

    /**
     * @Column(type="text")
     */
    protected $privateKey;
	
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
	 * @return SSLCert
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
	 * Set certificate content
	 * 
	 * @param string $content
	 * @return SSLCert
	 */
	public function setContent($content) {
		$this->content = $content;
		return $this;
	}
	
	/**
	 * Get certificate content
	 * 
	 * @return string
	 */
	public function getContent() {
		return $this->content;
	}

    /**
     * Set private key
     *
     * @param string $key
     * @return SSLCert
     */
    public function setKey($key) {
        $this->privateKey = $key;
        return $this;
    }

    /**
     * Get private key
     *
     * @return string
     */
    public function getKey() {
        return $this->privateKey;
    }
	
	/**
	 * Returns the SHA1 certificate fingerprint
	 */
	public function getFingerprint() {
		$cert = $this->getContent();
		$cert = preg_replace('/\-+BEGIN CERTIFICATE\-+/', '', $cert);
		$cert = preg_replace('/\-+END CERTIFICATE\-+/', '', $cert);
		$cert = trim($cert);
		$cert = str_replace(array("\n\r", "\n", "\r"), '', $cert);
		return md5(base64_decode($cert));
	}
	
	public function getState() {
		$sensor = $this->getSensor() == null ? '' : $this->getSensor()->getId();
		return array(
			'id' => $this->getId(),
			'sensor' => $sensor,
			'content' => $this->getContent(),
			'fingerprint' => $this->getFingerprint()
		);
	}
}