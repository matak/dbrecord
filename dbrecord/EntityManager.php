<?php

/**
 * Base mapper class by pattern Table Data Gateway.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

use Nette,
    dbrecord\Connection;

class EntityManager
{

	/** @var array */
	protected $repositories = array();


	/** @var Nette\DI\Container */
	protected $context;


	/** @var System\DbRecord\Connection */
	protected $connection;





	public function __construct(Nette\DI\Container $container, Connection $connection)
	{
		$this->context = $container;
		$this->connection = $connection;
	}





	public function getDao($className)
	{
		return $this->getRepository($className);
	}




	/**
	 * 
	 * @param string $className
	 * @return EntityRepository
	 */
	public function getRepository($className)
	{
		$className = ltrim($className, "\\");

		if (!isset($this->repositories[$className])) {
			$metadata = $this->createMetadata($className);
			$repositoryClass = $metadata->getRepositoryClass() ?: "\dbrecord\EntityRepository";
			$this->repositories[$className] = new $repositoryClass($this, $metadata);
		}

		return $this->repositories[$className];
	}





	protected function createMetadata($className)
	{
		$className = ltrim($className, "\\");

		$cache = new \Nette\Caching\Cache($this->context->cacheStorage, "dbrecord.metadata");

		$key = $className;

		$metadata = $cache[$key];

		if ($metadata === NULL) {
			$metadata = new Metadata\EntityMetadata(Metadata\Annotations::build($className));
			$cache->save($key, $metadata);
		}

		return $metadata;
	}





	public function getConnection()
	{
		return $this->connection;
	}





	public function getContext()
	{
		return $this->context;
	}





	public function find($className, $pk)
	{
		$this->getRepository($className)->find($pk);
	}

}
