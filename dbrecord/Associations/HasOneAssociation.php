<?php


/**
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

final class HasOneAssociation extends Association
{

	/**
	 * Association constructor.
	 *
	 * @param string $referenceClass  referenced object name
	 * @param string $localId  localId column name
	 */
	public function __construct($referenceClass, $params = array()) {
		parent::__construct(
				self::HAS_ONE,
				$referenceClass,
				$params['localId'],
				$params['foreignId'],
				isset($params['condition']) ? $params['condition'] : NULL,
				isset($params['through']) ? $params['through'] : NULL,
				isset($params['throughLocalId']) ? $params['throughLocalId'] : NULL,
				isset($params['throughForeignId']) ? $params['throughForeignId'] : NULL
			);
	}

	/**
	 * Retreives referenced object(s).
	 *
	 * @param  DbRecord|DbTreeRecord $record
	 * @return DbRecord|NULL
	 */
	public function retrieveReferenced(DbRecord $record) {
		$class = $this->getReferenceClass();
		$key = $this->getLocalId();

		if (!$record->$key) {
			return NULL;
		}

		if (($reference = $class::find($record->$key))) {
			$reference->belongsToAssociation = new BelongsToAssociation($record, $this->getForeignId(), $this->getLocalId());
			return $reference;

		} else {
			return NULL;
		}

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
			$reference = new $class($value, NULL, $construction);
		} 
		elseif (is_object($value)) {
			$reference = $value;
		}
		// pravdepodobne jde o vyrazeni asociace ze hry pomoci NULL, false apod.
		else {
			return $value;
		}

		$localId = $this->getLocalId();
		$foreignId = $this->getForeignId();

		// nastav asociovanemu objektu pk od rodicovskeho recordu / prave byl objekt pripojen tedy mel by nest jeho pk
		if (isset($record->$localId)) {
			$reference->$foreignId = $record->$localId;
		}

		$reference->belongsToAssociation = new BelongsToAssociation($record, $this->getForeignId(), $this->getLocalId());
		return $reference;

	}

}
