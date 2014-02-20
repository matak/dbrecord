<?php
/* 
 * Very simple and dummy implementation of IdentityMap,
 * better to say temporarily solution.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */


namespace System\DbRecord;

use Nette\Object;

class IdentityMap extends Object
{

	private $map = array();

	public function __construct()
	{

	}

	public function isMapped($pk)
	{
		return isset($this->map[$pk]);
	}

	public function find($pk)
	{
		return $this->map[$pk];
	}

	public function map(DbRecord $record)
	{
		return $this->map[$record->getPrimaryKey()] = $record;
	}



}