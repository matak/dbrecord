<?php

/**
 * Base mapper class by pattern Table Data Gateway.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord\Metadata;

class EntityMetadata
{

	/** @var string */
	protected $table;


	/** @var string */
	protected $repositoryClass;


	/** @var string */
	protected $validatorClass;


	/** @var string */
	protected $mapperClass;


	/** @var array */
	protected $associations = array();


	/** @var array */
	protected $columns = array();





	public function __construct($data)
	{
		// check variables
		$properties = array("repositoryClass", "validatorClass", "mapperClass", "table");
		foreach ($properties as $key) {
			if (isset($data[$key])) {
				$this->$key = $data[$key];
			}
		}

		if (isset($data['columns'])) {
			foreach ($data['columns'] as $name => $column) {
				$this->addColumn(
						$name, $column['type'], 
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



	public function getRepositoryClass()
	{
		return $this->repositoryClass;
	}

	public function getTable()
	{
		return $this->table;
	}
	
}
