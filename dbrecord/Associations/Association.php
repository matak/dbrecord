<?php


/**
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace System\DbRecord;

use Nette\Object;

abstract class Association extends Object implements IObjectContainerToFree
{

	/**#@+ association type */
	const BELONGS_TO = 'belongsTo'; // N:1
	const HAS_ONE = 'hasOne'; // 1:1
	const HAS_MANY = 'hasMany'; // 1:N
	const HAS_AND_BELONGS_TO_MANY = 'hasAndBelongsToMany'; // M:N

	/** @var string */
	public $type;

	/** @var array */
	static public $types = array(
		self::BELONGS_TO,
		self::HAS_ONE,
		self::HAS_MANY,
		self::HAS_AND_BELONGS_TO_MANY
	);

	/** @var string */
	public $referenceClass;

	/** @var string */
	public $localId;

	/** @var string */
	public $foreignId;

	/** @var string */
	public $condition;

	/** @var string */
	public $throughLocalId;

	/** @var string */
	public $throughForeignId;

	/** @var string */
	public $through;

	/**
	 * Association constructor.
	 *
	 * @param string $type  association type constant
	 * @param string $referenceClass  referenced class name
	 */
	public function __construct($type, $referenceClass, $localId, $foreignId, $condition = NULL, $through = NULL, $throughLocalId = NULL, $throughForeignId = NULL) {

		if (in_array($type, self::$types))
			$this->type = $type;
		else
			throw new \Nette\InvalidArgumentException("Unknown association type '$type' given.");

		$this->referenceClass = $referenceClass;

		$this->localId = $localId;

		$this->foreignId = $foreignId;

		$this->condition = $condition;

		$this->throughLocalId = $throughLocalId;

		$this->throughForeignId = $throughForeignId;
		
		$this->through = $through;


	}

	public function getReferenceClass()
	{
		return $this->referenceClass;
	}


	/**
	 *	id, ke kteremu se vazbim.
	 */
	public function getCondition()
	{
		return $this->condition;
	}


	/**
	 * ID of 
	 */
	public function getLocalId()
	{
		return $this->localId;
	}



	/**
	 * ID of foreign target table, which is set at base class as a relation key.
	 */
	public function getForeignId()
	{
		return $this->foreignId;
	}

	/**
	 * ID of through joined table, which is connected to getLocalId()
	 * @return type
	 */
	public function getThroughLocalId()
	{
		return $this->throughLocalId;
	}

	
	/**
	 * ID of through joined table, which is connected to getForeignId()
	 * @return type
	 */
	public function getThroughForeignId()
	{
		return $this->throughForeignId;
	}

	public function getThrough()
	{
		return $this->through;
	}


	public function getType()
	{
		return $this->type;
	}


	/**
	 * Because of memory leaks :(
	 */
	public function free()
	{
		//$fh = fopen("x:\x.log", "a");fwrite($fh, __CLASS__ . "/" .__FUNCTION__ . "\n");fclose($fh);
		
		foreach (array_keys(get_object_vars($this)) as $key) {
			// je to objekt? je typu IObjectContainerToFree? spustime na nej free
			if (is_object($this->$key) && $this->$key instanceof IObjectContainerToFree) {
				// nejdrive prepojime
				$object = $this->$key;
				// pak odpojime
				$this->$key = NULL;
				// a pak zlikvidujeme, jinak by se to mohlo zacyklit, record vola free kolekce a kolekce patri k rekordu takze zase zavola jeho zniceni a tak dokola
				$object->free();
			}
			else {
				$this->$key = NULL;
			}
		}
	}
	
	
}
