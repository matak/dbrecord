<?php

/**
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 * @license    BSD License
 */

namespace dbrecord;

use Nette\Object;

class DbRecordConfig extends Object
{

	/** @var array */
	protected $table;


	/** @var array|Association[]|HasManyAssociation[]|HasOneAssociation[] */
	protected $associations = array();


	/** @var array */
	protected $columns = array();


	/** @var array */
	protected $defaults = array();


	/** @var array */
	protected $primaryColumns = array();


	/** @var bool */
	protected $primaryAutoincrement = false;





	/**
	 * Detects data types and keys from database
	 * @param string $recordClass
	 */
	public function autoDetect($recordClass)
	{
		$db = $recordClass::connection();
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
		}
	}





	/**
	 * Get association by path.
	 *
	 * @param  string $key
	 * @return Association
	 */
	public function getAssociationByPath($path)
	{
		$config = $this;
		foreach ($path as $index => $key) {
			$association = $config->getAssociation($key);
			if (isset($path[$index + 1])) {
				$aclass = $association->getReferenceClass();
				$config = $aclass::config();
			}
		}
		return $association;
	}





	/**
	 * Get association by key.
	 *
	 * @param  string $key
	 * @return Association|HasManyAssociation|HasOneAssociation
	 */
	public function getAssociation($key)
	{
		return $this->associations[$key];
	}





	/**
	 * Get all associations.
	 *
	 * @return array \System\DbRecord\Association
	 */
	public function getAssociations()
	{
		return $this->associations;
	}





	/**
	 * Isset association with key?
	 *
	 * @param  string $key
	 * @return bool
	 */
	public function isAssociation($key)
	{
		return isset($this->associations[$key]);
	}





	/**
	 * Add column
	 * @param string $name
	 * @return DbTableConfig
	 */
	public function addColumn($name, $type, $size, $params = array())
	{
		$this->columns[$name] = array(
			'type' => $type,
			'size' => $size,
			'nullable' => (isset($params['nullable']) ? $params['nullable'] : false),
			'mandatory' => (isset($params['mandatory']) ? $params['mandatory'] : false),
		);
		if (isset($params['default'])) {
			$this->defaults[$name] = $params['default'];
		}
		if (isset($params['primary']) && $params['primary'] == true) {
			$this->primaryColumns[] = $name;
			$this->primaryAutoincrement = isset($params['autoincrement']) ? $params['autoincrement'] : false; // autoincrement muze bzt jen singleprimary
		}

		return $this;
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
	 * Get column schema
	 * @return array
	 */
	public function getColumn($name)
	{
		return $this->columns[$name];
	}





	/**
	 * Get type of column
	 * @return string
	 */
	public function getType($name)
	{
		return $this->columns[$name]['type'];
	}





	/**
	 * Get columns
	 * @return array
	 */
	public function getColumnsKeys()
	{
		return array_keys($this->columns);
	}





	/**
	 * Returns table name.
	 * @return string
	 */
	public function getTable()
	{
		return $this->table;
	}





	/**
	 * Get defaults
	 * @return array
	 */
	public function getDefaults()
	{
		return $this->defaults;
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
	 * Is column mandatory
	 * @param string $name
	 * @return bool
	 */
	public function isMandatory($name)
	{
		return $this->columns[$name]['mandatory'];
	}





	/**
	 * Get primary columns
	 * @return array
	 */
	public function getPrimaryColumns()
	{
		return $this->primaryColumns;
	}





	/**
	 * Get first primary column name
	 * @return string
	 */
	public function getPrimaryColumn()
	{
		return $this->primaryColumns[0];
	}





	public function isSinglePrimary()
	{
		return count($this->primaryColumns) == 1 ? 1 : 0;
	}





	public function isPrimaryAutoincrement()
	{
		return $this->primaryAutoincrement;
	}

}
