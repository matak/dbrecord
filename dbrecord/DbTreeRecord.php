<?php


/**
 * DbRecord
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

abstract class DbTreeRecord extends DbRecord
{


	/** @var string topic index */
	protected static $_topicIndex;



	/** @var string default mapper object */
	const DEFAULT_MAPPER = "\System\DbRecord\DbTreeMapper";

	/** @var string default collection object */
	const DEFAULT_TREE = "\System\DbRecord\AssociatedTreeCollection";


	/**
	 * Get topicIndex / there is not use clasic getter, because of possible colision with the name of database column
	 * @return string
	 */
	public static function topicIndex()
	{
		return static::$_topicIndex;
	}


	/**
	 * Check whether record is root tree record.
	 *
	 * @return bool
	 */
	public function isRoot()
	{
		$mainIndex = static::mainIndex();
		if ($this->idParent === NULL && $this->$mainIndex === "__root__") {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Return path from root. No tree, but basic collection structure is returned.
	 *
	 * In path is not included self instance! Returned is only collection not the loaded data.
	 *
	 * @return DbRecordCollection
	 */
	public function path()
	{
		return $this->fluent('#.*')
				->where('#.sx < %i', $this->sx, ' AND #.dx > %i', $this->dx, ' AND #.idStore = %i', $this->idStore)
				->orderBy('#.sx')
				->collection();

	}

	
	
	/**
	 * Return tree from itself. No tree, but basic collection structure is returned.
	 *
	 * In path is not included self instance! Returned is only collection not the loaded data.
	 *
	 * @return DbRecordCollection
	 */
	public function tree()
	{
		return $this->fluent('#.*')
				->where('#.sx > %i', $this->sx, ' AND #.dx < %i', $this->dx, ' AND #.idStore = %i', $this->idStore)
				->orderBy('#.sx')
				->collection();

	}


}

