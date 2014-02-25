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
		$sql = $this->getConnection();
		$db = $this->getDatabaseInfo();
		foreach($db->getTables() as $t) {
			// table comment?
			$status = $sql->query("SHOW TABLE STATUS LIKE %s", $t->getName())->fetch();
			if (isset($status['Comment']) && ($comment = $status['Comment'])) {
				preg_match("/@Entity\((.*)\)/", $comment, $matches);
				d($matches);
			}
		}
		
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
		if (!isset($this->cache['databaseInfo'])) {
			$this->cache['databaseInfo'] = $this->getConnection()->getDatabaseInfo();
		}
		return $this->cache['databaseInfo'];
	}










	protected function getTableColumns()
	{
		if (!isset($this->cache['databaseInfo']['table']['columns'])) {
			$this->cache['databaseInfo']['table']['columns'] = $this->getTable()->getColumns();
		}
		return $this->cache['databaseInfo']['table']['columns'];
	}

}
