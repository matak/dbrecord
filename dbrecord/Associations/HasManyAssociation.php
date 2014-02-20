<?php


/**
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

final class HasManyAssociation extends Association
{

	/** @var string */
	public $associatedCollectionClass;	

	/**
	 * Association constructor.
	 *
	 * @param string $referenceClass  referenced object name
	 * @param array $params
	 */
	public function __construct($referenceClass, $params = array())
	{
		parent::__construct(
				self::HAS_MANY,
				$referenceClass,
				$params['localId'],
				$params['foreignId'],
				isset($params['condition']) ? $params['condition'] : NULL,
				isset($params['through']) ? $params['through'] : NULL,
				isset($params['throughLocalId']) ? $params['throughLocalId'] : NULL,
				isset($params['throughForeignId']) ? $params['throughForeignId'] : NULL
			);

		$this->associatedCollectionClass = isset($params['associatedCollectionClass']) ? $params['associatedCollectionClass'] : (__NAMESPACE__ . "\AssociatedCollection");
	}

	public function getAssociatedCollectionClass()
	{
		return $this->associatedCollectionClass;
	}



	/**
	 * Retreives referenced object(s).
	 *
	 * @param  DbRecord $record
	 * @return AssociatedCollection|NULL
	 */
	public function retrieveReferenced(DbRecord $record)
	{
		$aclass = $this->getAssociatedCollectionClass();
		$association = new $aclass($this, $record);
		return $association; // PHP work-around (Only variable references should be returned by reference)
	}




	/**
	 * Links created object from array or same value.
	 *
	 * @param  DbRecord $record
	 * @param  mixed $value
	 * @return DbRecord
	 */
	public function saveReferenced(DbRecord $record, $value, $construction = array())
	{

		if (is_array($value)) {

			$class = $this->getReferenceClass();

			$objects = array();
			foreach($value as $v) {
				$objects[] = new $class($v, NULL, $construction);
			}

			// ocekavame ze se jedna o vice objektu a budeme vytvaret AssociatedCollection
			$aclass = $this->getAssociatedCollectionClass();
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

}
