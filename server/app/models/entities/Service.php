<?php
namespace HoneySens\app\models\entities;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Abstraction class for dockerized honeypot services.
 *
 * @Entity
 * @Table(name="services")
 */
class Service {

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * Informal title of this service.
     *
     * @Column(type="string", nullable=false)
     */
    protected $name;

    /**
     * General description of this service.
     *
     * @Column(type="string")
     */
    protected $description;

    /**
     * Docker repository that relates to this service, e.g. "honeysens/cowrie"
     *
     * @Column(type="string")
     */
    protected $repository;

    /**
     * References the docker image tags for this service
     *
     * @OneToMany(targetEntity="HoneySens\app\models\entities\ServiceRevision", mappedBy="service", cascade={"remove"})
     */
    protected $revisions;

    /**
     * @OneToOne(targetEntity="HoneySens\app\models\entities\ServiceRevision")
     */
    protected $defaultRevision;

    /**
     * The service assignment that this service is associated with.
     *
     * @OneToMany(targetEntity="HoneySens\app\models\entities\ServiceAssignment", mappedBy="service", cascade={"remove"})
     */
    protected $assignments;

    public function __construct() {
        $this->revisions = new ArrayCollection();
        $this->assignments = new ArrayCollection();
    }

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
     * @return $this
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
     * Returns a label based on the repository name of this service, which may be used on the sensor as service identifier.
     *
     * @return string
     */
    public function getLabel() {
        $nameParts = explode('/', $this->repository);
        return $nameParts[sizeof($nameParts) - 1];
    }

    /**
     * Set the generic description of this service.
     *
     * @param string $description
     * @return $this
     */
    public function setDescription($description) {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description of this service.
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Set the repository location of this service.
     *
     * @param string $repository
     * @return $this
     */
    public function setRepository($repository) {
        $this->repository = $repository;
        return $this;
    }

    /**
     * Get repository location of this service.
     */
    public function getRepository() {
        return $this->repository;
    }

    /**
     * Add a revision to this service.
     *
     * @param ServiceRevision $revision
     * @return $this
     */
    public function addRevision(ServiceRevision $revision) {
        $this->revisions[] = $revision;
        $revision->setService($this);
        return $this;
    }

    /**
     * Remove a revision from this service.
     *
     * @param ServiceRevision $revision
     * @return $this
     */
    public function removeRevision(ServiceRevision $revision) {
        $this->revisions->removeElement($revision);
        $revision->setService(null);
        return $this;
    }

    /**
     * Get all revisions associated with this service.
     *
     * @return ArrayCollection
     */
    public function getRevisions() {
        return $this->revisions;
    }

    /**
     * Returns true, if the given revision is available for this service
     *
     * @param string $revision
     * @return bool
     */
    public function hasRevision($revision) {
        foreach($this->revisions as $r) {
            if($r->getRevision() == $revision) return true;
        }
        return false;
    }

    /**
     * Set the default revision for this service.
     *
     * @param ServiceRevision|null $revision
     * @return $this
     */
    public function setDefaultRevision($revision) {
        $this->defaultRevision = $revision;
        return $this;
    }

    /**
     * Returns the revision that this service defaults to.
     *
     * @return ServiceRevision
     */
    public function getDefaultRevision() {
        return $this->defaultRevision;
    }

    /**
     * Assign this service with a specific sensor, causing it to run there
     *
     * @param ServiceAssignment $assignment
     * @return $this
     */
    public function addAssignment(ServiceAssignment $assignment) {
        $this->assignments[] = $assignment;
        $assignment->setService($this);
        return $this;
    }

    /**
     * Removes the assignment of this service from a specific sensor, causing it to no longer run there
     *
     * @param ServiceAssignment $assignment
     * @return $this
     */
    public function removeAssignment(ServiceAssignment $assignment) {
        $this->assignments->removeElement($assignment);
        $assignment->setService(null);
        return $this;
    }

    /**
     * Get all assignments that belong to this service.
     *
     * @return ArrayCollection
     */
    public function getAssignments() {
        return $this->assignments;
    }

    public function getState() {
        $revisions = array();
        foreach($this->revisions as $revision) {
            $revisions[] = $revision->getState();
        }
        $assignments = array();
        foreach($this->assignments as $assignment) {
            $assignments[] = $assignment->getId();
        }
        return array(
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'repository' => $this->getRepository(),
            'revisions' => $revisions,
            'default_revision' => $this->defaultRevision->getId(),
            'assignments' => $assignments
        );
    }
}
