<?php

/**
 * Metadata class
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord\Metadata;

class EntityMetadata
{

	/** @var string */
	protected $entityClass;

	/** @var string */
	protected $table;


	/** @var string */
	protected $mainIndex;


	/** @var string */
	protected $topicIndex;


	/** @var string */
	protected $repositoryClass;


	/** @var string */
	protected $validatorClass;


	/** @var array */
	protected $associations = array();


	/** @var array */
	protected $columns = array();





	public function __construct($data)
	{
		// check variables
		$properties = array("entityClass", "repositoryClass", "validatorClass", "table", "mainIndex", "topicIndex");
		foreach ($properties as $key) {
			if (isset($data[$key])) {
				$this->$key = $data[$key];
			}
		}

		if (isset($data['columns'])) {
			foreach ($data['columns'] as $name => $column) {
				$this->addColumn(
						$name, 
						$column['type'], 
						array_key_exists("size", $column) ? $column['size'] : NULL, 
						array_key_exists("nullable", $column) ? $column['nullable'] : false, 
						array_key_exists("primary", $column) ? $column['primary'] : false, 
						array_key_exists("autoincrement", $column) ? $column['autoincrement'] : false, 
						array_key_exists("default", $column) ? $column['default'] : NULL
				);
			}
		}

		if (isset($data['associations'])) {
			foreach ($data['associations'] as $name => $a) {
				$this->addAssociation(
						$name, 
						$a['type'], 
						$a['referenceClass'], 
						$a['localId'], 
						$a['foreignId'], 
						array_key_exists("associatedCollectionClass", $a) ? $a['associatedCollectionClass'] : NULL
				);
			}
		}
	}





	/**
	 * Add column
	 * @param string $name
	 * @return DbTableConfig
	 */
	protected function addColumn($name, $type, $size, $nullable = false, $primary = false, $autoincrement = false, $default = NULL)
	{
		$this->columns[$name] = array(
			'type' => $type,
			'size' => $size,
			'nullable' => $nullable,
			'primary' => $primary,
			'autoincrement' => $autoincrement,
			'default' => $default,
		);

		return $this;
	}





	/**
	 * Add column
	 * @param string $name
	 * @return DbTableConfig
	 */
	protected function addAssociation($name, $type, $referenceClass, $localId, $foreignId, $associatedCollectionClass = NULL)
	{
		$params = array(
			'localId' => $localId,
			'foreignId' => $foreignId,
			'associatedCollectionClass' => $associatedCollectionClass,
		);

		if ($type == "hasOne") {
			$association = new \dbrecord\HasOneAssociation($referenceClass, $params);
		}
		elseif ($type == "hasMany") {
			$association = new \dbrecord\HasManyAssociation($referenceClass, $params);
		}

		$this->associations[$name] = $association;

		return $this;
	}



	public function getEntityClass()
	{
		return $this->entityClass;
	}


	public function getRepositoryClass()
	{
		return $this->repositoryClass;
	}





	public function getTable()
	{
		return $this->table;
	}

	
	public function getPrimaryColumnsKeys()
	{
		return array_keys(array_filter($this->columns, function($values) {
												if (!$values['primary']) {
													return false;
												}
												return true;
											}));
	}
	
	

	/**
	 * Get first primary column name
	 * @return string
	 */
	public function getPrimaryColumn()
	{
		$primaryColumns = $this->getPrimaryColumnsKeys();
		return $primaryColumns[0];
	}
	
	
	
	/**
	 * Isset association with key?
	 *
	 * @param  string $key
	 * @return bool
	 */
	public function hasAssociation($key)
	{
		return isset($this->associations[$key]);
	}


	


	/**
	 * Has column
	 * @param string $name
	 * @return bool
	 */
	public function hasColumn($name)
	{
		return isset($this->columns[$name]);
	}





	/**
	 * Get columns schema
	 * @return array
	 */
	public function getColumns()
	{
		return $this->columns;
	}

	

	/**
	 * Get association by key.
	 *
	 * @param  string $key
	 * @return HasManyAssociation|HasOneAssociation
	 */
	public function getAssociation($key)
	{
		return $this->associations[$key];
	}

	
	
	/**
	 * Is column nullable
	 * @param string $name
	 * @return bool
	 */
	public function isNullable($name)
	{
		return $this->columns[$name]['nullable'];
	}




	/**
	 * Is column nullable
	 * @param string $name
	 * @return bool
	 */
	public function isAutoincrement($name)
	{
		return $this->columns[$name]['autoincrement'];
	}




	/**
	 * Returns columns default.
	 * @param string $name
	 * @return bool
	 */
	public function getDefault($name)
	{
		return $this->columns[$name]['default'];
	}


	/**
	 * Is column mandatory.
	 * @param string $name
	 * @return bool
	 */
	public function isMandatory($name)
	{
		if (!$this->isNullable($name) && !$this->isAutoincrement($name) && $this->getDefault($name) === NULL) {
			return true;
		}
		
		return false;
	}



	
	/**
	 * Returns columns type.
	 * @return string
	 */
	public function getType($name)
	{
		return $this->columns[$name]['type'];
	}

	

	
}
