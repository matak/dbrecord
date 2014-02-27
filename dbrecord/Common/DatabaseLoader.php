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

	/** @var \Nette\DI\Container */
	protected $context;


	/** @var string */
	protected $tempDir;


	/** @var array */
	protected $comments = array();


	/** @var array */
	protected $cache = array();





	public function __construct($context)
	{
		$this->context = $context;
	}





	public function setTempDir($tempDir)
	{
		$this->tempDir = $tempDir;
	}





	public function setComments($comments)
	{
		$this->comments = $comments;
	}





	protected function prepareTables()
	{
		$sql = $this->getConnection();
		$db = $this->getDatabaseInfo();
		foreach ($db->getTables() as $table) {
			$entity = array();
			$className = str_replace("\\\\", "\\", implode(
							'\\', array_map(
									function($value) {
								return ucfirst($value);
							}, explode("_", $table->getName())
							)
			));

			// table comment?
			$status = $sql->query("SHOW TABLE STATUS LIKE %s", $table->getName())->fetch();
			if (isset($status['Comment']) && ($comment = $status['Comment'])) {
				preg_match('/^@Entity\((.*)\)/', $comment, $matches);
				if (isset($matches[1])) {
					$matches = explode(",", $matches[1]);
					foreach ($matches as $value) {
						$v = explode("=", $value);
						$v1 = trim($v[1], '"\'');
						if ($v[0] == "name") {
							$className = $v1;
						}
						else {
							$entity[$v[0]] = $v1;
						}
					}
				}
			}

			// first store all tables
			$this->cache['tables'][$table->getName()] = array(
				'className' => $className,
				'table' => $table,
				'entity' => $entity,
			);
			
			$this->cacheForeignKeys($table);
		}
	}


	protected function cacheForeignKeys($table)			
	{
		// not implemented yet
	}


	public function createClasses()
	{
		$this->prepareTables();
		dd($this->cache);
		// now go through
		foreach ($this->cache['tables'] as $v) {
			$this->buildEntityClass($v['className'], $v['table'], $v['entity']);
		}
	}





	public function buildEntityClass($className, $table, $entity = array())
	{
		$path = $this->tempDir . "/" . str_replace("\\", "/", $className);

		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}

		$fh = fopen($path . ".php", "w");
		fwrite($fh, $this->generateEntityClass($className, $table, $entity));
		fclose($fh);
	}





	public function generateEntityClass($className, $table, $entity = array())
	{
		$namespace = strpos($className, "\\") ? ltrim(dirname($className), "\\") : NULL;

		$ret = '<?php' . "\n\n\n";

		if ($namespace) {
			$ret .= "\n";
			$ret .= "namespace " . $namespace . ";";
			$ret .= "\n";
		}

		$ret .= "\n";
		$ret .= 'use dbrecord\Entity;' . "\n";
		$ret .= "\n";

		$ret .= $this->generateEntityComment($className, $table, $entity);

		$ret .= 'class ' . basename($className) . ' extends Entity' . "\n";
		$ret .= '{' . "\n";
		$ret .= "\n";
		$ret .= '}' . "\n";

		return $ret;
	}





	protected function generateClassDescription($className)
	{
		$ret = "";
		if (!isset($this->comments['description'])) {
			$ret .= " * " . basename($className) . "\n";
		}
		else {
			$ret .= " * " . $this->comments['description'] . "\n";
		}

		$ret .= " *\n";

		$order = array("@author", "@copyright");
		foreach ($order as $v) {
			if (isset($this->comments[$v])) {
				$ret .= " * " . $v . " " . $this->comments[$v] . "\n";
			}
		}

		return $ret;
	}





	protected function generateEntityDescription($table, $entity)
	{
		$ret = "";
		$ret .= " * @Entity(" . $this->serializeArray($entity) . ")\n";
		$ret .= " * @Table(name=\"" . $table->getName() . "\")\n";

		return $ret;
	}





	protected function generateEntityProperties($table)
	{
		$ret = "";

		// find primary keys
		$pks = array();
		foreach ($table->getPrimaryKey()->getColumns() as $column) {
			$pks[] = $column->getName();
		}

		// php nezná datové typy time (d,t) naopak bool (b) nezná mysql, dostupné typy pro anotace jsou string, int, float
		foreach ($table->getColumns() as $column) {
			$ret .= $this->generateEntityProperty($table, $column, $pks);
		}

		return $ret;
	}


	protected function generateEntityAssociations($table)
	{
		$ret = "";

		// not implemented 
		
		return $ret;
	}




	protected function generateEntityComment($className, $table, $entity = array())
	{
		$ret = "/**\n";
		$ret .= $this->generateClassDescription($className);
		$ret .= " *\n";
		$ret .= $this->generateEntityDescription($table, $entity);
		$ret .= " *\n";
		$ret .= $this->generateEntityProperties($table);
		$ret .= " *\n";
		$ret .= $this->generateEntityAssociations($table);
		$ret .= " *\n";
		$ret .= " */\n";

		return $ret;
	}





	protected function generateEntityProperty($table, \DibiColumnInfo $column, $pks)
	{
		$name = $column->getName();
		
		$params = array(
			'type' => $type = $column->getType(),
			'size' => $column->getSize(),
			'nullable' => $column->isNullable() ? "true" : "false",
			'default' => is_null($default = $column->getDefault()) ? "NULL" : $default,
		);

		if (in_array($name, $pks)) {
			$params['primary'] = "true";
			if ($column->isAutoincrement()) {
				$params['autoincrement'] = "true";
			}
		}
		
		$propertyType = NULL;
		switch ($type) {
			case "s":
				$propertyType = "string";
				break;

			case "i":
				$propertyType = "int";
				break;

			case "f":
				$propertyType = "float";
				break;

			case "d":
			case "t":
				$propertyType = "\dbrecord\DateTime"; // dibi s nastavením setTypes() u fetch vždy vrátí int, převádí pomocí fce strtotime datum na int
				break;
		}

		return ' * @property ' . $propertyType . ' $' . $name . "	Column(" . $this->serializeArray($params) . ")\n";
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





	protected function serializeArray($array)
	{
		$serialized = array();
		if (count($array)) {
			foreach ($array as $k => $v) {
				$serialized[] = $k . "=\"" . $v . "\"";
			}
		}

		return implode(",", $serialized);
	}

}
