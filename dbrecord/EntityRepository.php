<?php

/**
 * Repository/Mapper class by pattern Table Data Gateway.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

class EntityRepository
{

	/** @var EntityManager */
	protected $em;


	/** @var EntityMetadata */
	protected $metadata;






	public function __construct(EntityManager $em, Metadata\EntityMetadata $metadata)
	{
		$this->em = $em;
		$this->metadata = $metadata;
	}









	/**
	 * 
	 * @return \dbrecord\Connection
	 */
	public function getConnection()
	{
		return $this->em->getConnection();
	}









	/**
	 * 
	 * @return Metadata\EntityMetadata
	 */
	public function getMetadata()
	{
		return $this->metadata;
	}

	
	
	/**
	 * 
	 * @return EntityManager
	 */
	public function getEm()
	{
		return $this->getEntityManager();
	}
	
	
	/**
	 * 
	 * @return EntityManager
	 */
	public function getEntityManager()
	{
		return $this->em;
	}
	
	/**
	 * 
	 * @param \dbrecord\Entity $record
	 * @return \dbrecord\Entity
	 * @throws Exception
	 */
	public function save(Entity $record)
	{
		$record->updating();

		if ($record->isRecordDeleted()) {
			throw new Exception("You can't save deleted object.");
		}
		elseif ($record->isRecordNew()) {
			$this->insert($record);
		}
		elseif ($this->isRecordExisting()) {
			$this->update($record);
		}

		return $record;		
	}
	
	
	
	/**
	 * Gets current primary key(s) values formated in string, joined by underscore.
	 * Used to be $record->getPrimaryKey(
	 * 
	 * @return array
	 */
	public function getUniqueValue($record)
	{
		$metadata = $this->getMetadata();
		$values = array();
		foreach ($metadata->getPrimaryColumnsKeys() as $key) {
			$values[] = $record->$key;			
		}

		return implode("_", $values);
	}


	public function createAssociatedObject($name, $record)
	{
		$metadata = $this->getMetadata();
		$association = $metadata->getAssociation($name);
		
		if ($association instanceof HasOneAssociation) {
			return $this->createHasOneAssociatedObject($association, $record);
		}
		elseif ($association instanceof HasManyAssociation) {
			return $this->createHasManyAssociatedObject($association, $record);
		}
	}
	
	
	protected function createHasOneAssociatedObject(HasOneAssociation $association, Entity $record)
	{
		$class = $association->getReferenceClass();
		$key = $association->getLocalId();

		if (!$record->$key) {
			return NULL;
		}
		
		$classRepository = $this->getEntityManager()->getRepository($class);

		if (($reference = $classRepository->find($record->$key))) {
			$reference->belongsToAssociation = new BelongsToAssociation($record, $association->getForeignId(), $association->getLocalId());
			return $reference;
		}
		else {
			return NULL;
		}		
	}
	

	protected function createHasManyAssociatedObject(HasManyAssociation $association, Entity $record)
	{
		$aclass = $association->getAssociatedCollectionClass();
		$association = new $aclass($this, $record);
		return $association; // PHP work-around (Only variable references should be returned by reference)		
	}
	
	
	
	
	public function saveAssociatedObject($name, $record, $value, $construction = array())
	{
		$metadata = $this->getMetadata();
		$association = $metadata->getAssociation($name);
		
		if ($association instanceof HasOneAssociation) {
			return $this->saveHasOneAssociatedObject($association, $record, $value, $construction);
		}
		elseif ($association instanceof HasManyAssociation) {
			return $this->saveHasManyAssociatedObject($association, $record, $value, $construction);
		}
	}
	
	
	protected function saveHasOneAssociatedObject(HasOneAssociation $association, Entity $record, $value, $construction = array())
	{
		if (is_array($value)) {
			$class = $association->getReferenceClass();
			$reference = new $class($value, NULL, $construction);
		}
		elseif (is_object($value)) {
			$reference = $value;
		}
		// pravdepodobne jde o vyrazeni asociace ze hry pomoci NULL, false apod.
		else {
			return $value;
		}

		$localId = $association->getLocalId();
		$foreignId = $association->getForeignId();

		// nastav asociovanemu objektu pk od rodicovskeho recordu / prave byl objekt pripojen tedy mel by nest jeho pk
		if (isset($record->$localId)) {
			$reference->$foreignId = $record->$localId;
		}

		$reference->belongsToAssociation = new BelongsToAssociation($record, $association->getForeignId(), $association->getLocalId());
		return $reference;		
	}
	

	protected function saveHasManyAssociatedObject(HasManyAssociation $association, Entity $record, $value, $construction = array())
	{
		if (is_array($value)) {

			$class = $association->getReferenceClass();

			$objects = array();
			foreach ($value as $v) {
				$objects[] = new $class($v, NULL, $construction);
			}

			// ocekavame ze se jedna o vice objektu a budeme vytvaret AssociatedCollection
			$aclass = $association->getAssociatedCollectionClass();
			$aobject = new $aclass($this, $record);
			$aobject->import($objects);

			// memory leaks - nekde tady je
			$class = $objects = $value = $v = $construction = NULL;

			return $aobject;
		}
		else {

			return $value;
		}		
	}	
	
	/**
	 * Find row by primary key or conditions
	 * @param mixed $conditions
	 * @return Entity
	 */
	public function find($primary = NULL, $conditions = NULL)
	{
		return $this->query($conditions)->find($primary);
	}


	
	


	public function fluent($conditions = NULL)
	{
		return $this->query($conditions);
	}
	

	/**
	 * 
	 * #preg_match('~^\[(?<select>.*)\]\s+(?<query>%\w+)\s+(?<objects>#\w+(?:\s+#\w+)*)$~Ui', $conditions, $match);
	 * #preg_match('~^%(?<query>\w+)~', $conditions, $match);
	 * 
	 * @param type $conditions
	 * @return type
	 */
	public function query($conditions = NULL)
	{			
		if (!$conditions) {
			$query = $this->buildQuery();
		}
		elseif (preg_match('~^%(.*)~', $conditions, $matches)) {
			$query = $this->buildQuery($matches[1]);

		}
		else {
			$query = $this->buildQuery('emptyselect');
			$query->select($conditions);

		}

		return $query;
	}
	
	
	

	/**
	 * Build defined query by name.
	 *
	 * @throws	\InvalidArgumentException	Demand on undefined query.
	 * @param		string $name				Predefined query by object.
	 * @return	DbRecordFluent
	 */
	protected function buildQuery($name = NULL)
	{
		$query = new Query($this);
		
		switch ($name) {
			case "emptyselect":
				$query
					->select(false)
					->from('#');
				break;

			case NULL:
				$query
					->select('#.*')
					->from('#');
				break;

			default:
				throw new \Nette\InvalidArgumentException('Undefined query type '.$name.'!');
				break;

		}
		
		return $query;
	}

	
	
	
	public function createObjectFromValues(array $values)
	{
		$className = $this->getMetadata()->getEntityClass();
		$item = new $className($values);
		$item->setState(Entity::STATE_EXISTING);
		return $item;
	}
	
	
	public function collection(Query $query)
	{
		$collection = new EntityCollection($this);
		$collection->importQuery($query);
		return $collection;		
	}
	
	
}
