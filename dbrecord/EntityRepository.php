<?php

/**
 * Base mapper class by pattern Table Data Gateway.
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


	/** @var IDbRecordMapper */
	protected $mapper;





	public function __construct(EntityManager $em, Metadata\EntityMetadata $metadata)
	{
		$this->em = $em;
		$this->metadata = $metadata;
	}









	public function getConnection()
	{
		return $this->em->getConnection();
	}





	public function getMapper()
	{
		if (!$this->mapper) {
			$mapperClass = $this->getMetadata()->getMapperClass() ?: "\dbrecord\Mappers\DatabaseMapper";
			$this->mapper = new $mapperClass($this);
		}

		return $this->mapper;
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
			$this->getMapper()->insert($record);
		}
		elseif ($this->isRecordExisting()) {
			$this->getMapper()->update($record);
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
		
		
	}
}
