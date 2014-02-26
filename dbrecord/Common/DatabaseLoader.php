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
		foreach($db->getTables() as $table) {
			$entity = array();			
			$className = str_replace("\\\\", "\\", implode(
							'\\', 
							array_map(
								function($value) {
									return ucfirst($value);
								}, 
								explode("_", $table->getName())
							)
						));
			
			// table comment?
			$status = $sql->query("SHOW TABLE STATUS LIKE %s", $table->getName())->fetch();
			if (isset($status['Comment']) && ($comment = $status['Comment'])) {				
				preg_match('/^@Entity\((.*)\)/', $comment, $matches);
				if (isset($matches[1])) {
					$matches = explode(",", $matches[1]);
					foreach($matches as $value) {
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
			
			$this->buildEntityClass($className, $table, $entity);			
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
		
		$ret  = '<?php' . "\n\n\n";
		
		if ($namespace) {
			$ret .= "\n";
			$ret .= "namespace " . $namespace . ";";
			$ret .= "\n";
		}
		
		$ret .= "\n";
		$ret .= 'use dbrecord\Entity;' . "\n";
		$ret .= "\n";

		$ret .= $this->generateDocComment($className, $table, $entity);

		$ret .= 'class ' . basename($className) . ' extends Entity' . "\n";
		$ret .= '{' . "\n";
		$ret .= "\n";
		$ret .= '}' . "\n";
		
		return $ret;
	}

	


	protected function generateDocComment($className, $table, $entity = array(), $replacement = array())
	{
		$entitySerialized = array();
		if (count($entity)) {
			foreach($entity as $k => $v) {
				$entitySerialized[] = $k . "=\"" . $v . "\"";
			}
		}
		
		$ret  = "/**\n";
		
		$commentOrder = array("description", "@author", "@copyright");
		foreach ($commentOrder as $v) {
			if (isset($replacement[$v])) {
				$ret .= " * " . $replacement[$v] . "\n *\n";
			}
			elseif ($v == "description") {
				if (!isset($this->docComment['description'])) {
					$ret.=" * " . basename($className) . "\n *\n";
				}
				else {
					$ret.=" * " . $this->docComment['description'] . "\n *\n";
				}
			}
			elseif (isset($this->docComment[$v])) {
				$ret.=" * " . $v . " " . $this->docComment[$v] . "\n";
			}
		}
		
		$ret .= " *\n";
		
		$ret .= " * @Entity(" . implode(",", $entitySerialized) . ")\n";
		$ret .= " * @Table(name=\"" . $table->getName() . "\")\n";		
		
		$ret .= " *\n";
		
		// php nezná datové typy time (d,t) naopak bool (b) nezná mysql, dostupné typy pro anotace jsou string, int, float
		foreach ($table->getColumns() as $column) {
			$type = $column->getType();
			$name = $column->getName();
			switch ($type) {
				case "s":
					$type = "string";
					break;
				
				case "i":
					$type = "int";
					break;
				
				case "f":
					$type = "float";
					break;
				
				case "d":
				case "t":
					$type = "DateTime"; // dibi s nastavením setTypes() u fetch vždy vrátí int, převádí pomocí fce strtotime datum na int
					break;
			}
			
			$ret.=' * @property ' . $type . ' $' . $name . "\n";
		}
		$ret .= " *\n";
/*
		if (isset($this->config['associations'])) {
			$hasOne = $hasMany = array();
			$ret.=" *\n";
			foreach ($this->config['associations'] as $key => $association) {
				if ($association['type'] == "hasOne") {
					$hasOne[] = $key;

					$ret.=' * @property ' . $association['referenceClass'] . ' $' . $key . "\n";
				}
				elseif ($association['type'] == "hasMany") {
					$hasMany[] = $key;

					$ret.=' * @property ' . (isset($association['associatedCollection']) ? $association['associatedCollection'] : "AssociatedCollection") . ' $' . $key . "\n";
				}
			}
			$ret.=" *\n";
			if (count($hasOne)) {
				$ret.=' * @hasOne (' . implode(", ", $hasOne) . ')' . "\n";
			}
			if (count($hasMany)) {
				$ret.=' * @hasMany (' . implode(", ", $hasMany) . ')' . "\n";
			}
			$ret.=" *\n";
		}
*/
		$ret .= " */\n";
		
		return $ret;
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
