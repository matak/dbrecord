<?php

/**
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

final class BelongsToAssociation extends Association
{

	/** DbRecord */
	private $belongsToRecord;





	/**
	 * Association constructor.
	 *
	 * @param string $record  referenced object
	 * @param string $localId  localId column name
	 * @param string $foreignId  paramId column name
	 */
	public function __construct($record, $localId, $foreignId)
	{
		parent::__construct(self::BELONGS_TO, NULL, $localId, $foreignId);

		$this->belongsToRecord = $record;
	}





	public function getBelongsToRecord()
	{
		return $this->belongsToRecord;
	}





	public function getReferenceClass()
	{
		throw new \Nette\DeprecatedException;
	}

}
