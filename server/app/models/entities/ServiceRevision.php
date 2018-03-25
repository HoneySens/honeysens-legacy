<?php
namespace HoneySens\app\models\entities;

/**
 * @entity
 * @Table(name="service_revisions")
 */
class ServiceRevision {

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * Revision string of this service, equals the "tag" of this particular docker image.
     *
     * @Column(type="string", nullable=false)
     */
    protected $revision;

    /**
     * Description of this particular revision, mainly used to distinguish it from others.
     *
     * @Column(type="string")
     */
    protected $description;

    /**
     * @ManyToOne(targetEntity="HoneySens\app\models\entities\Service", inversedBy="revisions")
     */
    protected $service;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set the revision string for this particular revision/version.
     *
     * @param string $revision
     * @return $this
     */
    public function setRevision($revision) {
        $this->revision = $revision;
        return $this;
    }

    /**
     * Get the revision string for this instance.
     *
     * @return string
     */
    public function getRevision() {
        return $this->revision;
    }

    /**
     * Set a string that describes this revision, for instance with a version or change history.
     *
     * @param string $description
     * @return $this
     */
    public function setDescription($description) {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the revision description.
     *
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Set the service that belongs to this revision.
     *
     * @param Service|null $service
     * @return $this
     */
    public function setService(Service $service = null) {
        $this->service = $service;
        return $this;
    }

    /**
     * Get the service that belongs to this revision.
     *
     * @return Service
     */
    public function getService() {
        return $this->service;
    }

    public function getState() {
        return array(
            'id' => $this->getId(),
            'revision' => $this->getRevision(),
            'description' => $this->getDescription(),
            'service' => $this->getService()->getId()
        );
    }
}
