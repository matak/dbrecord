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





	public function getRepository($className)
	{
		$className = ltrim($className, "\\");

		if (!isset($this->repositories[$className])) {
			$metadata = $this->getMetadata($className);
			$repositoryClass = $metadata->getRepositoryClass();			
			$this->repositories[$className] = new $repositoryClass($this, $metadata);
		}

		return $this->repositories[$className];
	}


	protected function getMetadata($className)
	{
		$className = ltrim($className, "\\");

		$cache = new \Nette\Caching\Cache($this->context->cacheStorage, "dbrecord.metadata");

		$key = $className;

		$metadata = $cache[$key];

		if ($metadata === NULL) {
			$metadata = $this->createMetadata($className);
			$cache->save($key, $metadata);
		}

		return $metadata;
	}
	
	protected function createMetadata($className)
	{
		$metadata = new Metadata\EntityMetadata;
		$metadata->create(Metadata\Annotations::build($className));
		dd($metadata);
		/*$db = $recordClass::connection();
		$table = $db->getDatabaseInfo()->getTable($db->translate($recordClass::table()));

		$this->table = $table->getName();

		$pk = array();
		foreach ($table->getPrimaryKey()->getColumns() as $column) {
			$pk[] = $column->getName();
		}

		foreach ($table->getColumns() as $column) {
			$name = $column->getName();
			$type = $column->getType();
			$nullable = $column->isNullable();

			$params = array();

			$params['nullable'] = $nullable;

			if (in_array($name, $pk)) {
				$params['primary'] = true;
				if ($column->isAutoincrement()) {
					$params['autoincrement'] = true;
				}
			}

			if (!is_null($column->getDefault())) {
				$params['default'] = $column->getDefault();
			}

			if (!$nullable && !$column->isAutoincrement() && $column->getDefault() === NULL) {
				$params['mandatory'] = true;
			}

			$this->addColumn($name, $type, $column->getSize() ? $column->getSize() : NULL, $params);
		}*/		
	}


	public function getConnection()
	{
		return $this->connection;
	}

}
