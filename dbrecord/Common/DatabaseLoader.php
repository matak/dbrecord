<?php

/**
 * Database loader usefull for creating objects, generator of ORM objects.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

class DatabaseLoader
{

	/** @var string */
	public $tempDir;


	/** @var array */
	public $docComment = array();


	/** @var \Nette\DI\Container */
	public $context;


	/** @var array */
	protected $cache = array();





	public function __construct($context)
	{
		$this->context = $context;
	}





	public function createClasses()
	{
		
	}





	/**
	 * 
	 * @return Connection
	 */
	public function getConnection()
	{
		return $this->context->sql;
	}





	protected function getDatabaseInfo()
	{
		if (!isset($this->cache['databaseInfo'][0])) {
			dd($this->getConnection()->getDatabaseInfo());
			$this->cache['databaseInfo'][0] = $this->getConnection()->getDatabaseInfo();
		}
		return $this->cache['databaseInfo'][0];
	}





	protected function getTable()
	{
		if (!isset($this->cache['databaseInfo']['table'][0])) {
			$db = $this->getDatabaseInfo();
			$this->cache['databaseInfo']['table'][0] = $db->getTable($this->getTableName());
		}
		return $this->cache['databaseInfo']['table'][0];
	}





	protected function getTableColumns()
	{
		if (!isset($this->cache['databaseInfo']['table']['columns'])) {
			$this->cache['databaseInfo']['table']['columns'] = $this->getTable()->getColumns();
		}
		return $this->cache['databaseInfo']['table']['columns'];
	}

}
