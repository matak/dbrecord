<?php

/**
 * Database loader usefull for creating objects, generator of ORM objects.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

use Nette\Object;

class DbRecordLoader extends Object
{

	/** @var string */
	public $tempDir;


	/** @var array */
	public $docComment = array();
	public $context;





	public function __construct($context)
	{
		$this->context = $context;
	}





	public function createObject($config, $objectType = "DbRecord")
	{
		if ($objectType == "DbRecord") {
			return new DbRecordLoaderObject($this, $config);
		}
		elseif ($objectType == "DbTreeRecord") {
			return new DbRecordLoaderTreeObject($this, $config);
		}
		else {
			throw new \Nette\InvalidArgumentException("invalid object type " . $objectType);
		}
	}

}

/**
 * Manipulation object with basic loader class.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 * @license    BSD License
 *
 * Cunstruction configuration array settings
 * <code>
 * 	$config=array(
 * 		'namespace' => "System\User",
 * 		'class' => "Product",
 * 		'table' => ":prefix:products",
 * 		'DEFAULT_CONFIG' => "ProductConfig",
 * 		'DEFAULT_MAPPER' => "ProductMapper",
 * 		'DEFAULT_VALIDATOR' => "ProductValidator",
 * 		'associations' => array(
 * 			'local' => array( // nutno refaktorovat!!!
 * 				'hasMany' => array('localId' => "IDproducer"),
 * 				'Expedition' => array('localId' => "IDexpedition"),
 * 				"Guaranty" => array('localId' => "IDguaranty"),
 * 				"Margin" => array('localId' => "IDmargin"),
 * 				"Discount" => array('localId' => "IDdiscount")
 * 				),
 * 			'hasMany' => array(
 * 				"ProductImage" => array(),
 * 				"Category" => array(),
 * 				"Supplier" => array(),
 * 				"AlternativeGood" => array(),
 * 				"RelatedGood" => array()
 * 				),
 * 		),
 * 	);
 * </code>
 *
 */
class DbRecordLoaderObject extends Object
{

	/** @var DbRecordLoader */
	public $loader;


	/** @var array */
	public $config;


	/** @var array */
	private $cache = array();
	protected $extends = array(
		'record' => "DbRecord",
		'mapper' => "DbMapper",
	);





	/**
	 * DbRecordLoaderObject constructor.
	 * @param DbRecordLoader $loader
	 * @param array $config
	 *
	 */
	public function __construct(DbRecordLoader $loader, $config)
	{

		$this->loader = $loader;
		$this->config = $config;
	}





	public function getConnection()
	{
		return $this->loader->context->sql;
	}





	private function generateDocComment($replacement = array())
	{
		$commentOrder = array("description", "@author", "@copyright");
		$ret = "/**\n";
		foreach ($commentOrder as $v) {
			if (isset($replacement[$v])) {
				$ret.=" * " . $replacement[$v] . "\n *\n";
			}
			elseif ($v == "description") {
				if (!isset($this->config['docComment']['description'])) {
					$ret.=" * " . $this->config['class'] . "\n *\n";
				}
				else {
					$ret.=" * " . $this->config['docComment']['description'] . "\n *\n";
				}
			}
			elseif (isset($this->config['docComment']) && isset($this->config['docComment'][$v])) {
				$ret.=" * " . $this->config['docComment'][$v] . "\n";
			}
			elseif (isset($this->loader->docComment[$v])) {
				$ret.=" * " . $v . " " . $this->loader->docComment[$v] . "\n";
			}
		}
		$ret.=" *\n";
		// php nezná datové typy time (d,t) naopak bool (b) nezná mysql, dostupné typy pro anotace jsou string, int, float
		foreach ($this->getTableColumns() as $column) {
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
					$type = "int"; // dibi s nastavením setTypes() u fetch vždy vrátí int, převádí pomocí fce strtotime datum na int
					break;
			}
			$ret.=' * @property ' . $type . ' $' . $name . "\n";
		}
		$ret.=" *\n";

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

		$ret.=" */\n";
		return $ret;
	}





	private function generateDocCommentNamespace()
	{
		$ret = "";
		if (isset($this->config['namespace'])) {
			$ret.="\n";
			$ret.="namespace " . $this->config['namespace'] . ";";
			$ret.="\n";
		}
		return $ret;
	}





	public function generateMapperClass()
	{
		$commentOrder = array("description", "@author", "@copyright");

		$ret = '<?php' . "\n\n\n";
		$ret.="/**\n";
		$ret.=' * Mapper class of ' . $this->config['class'] . "\n";
		$ret.=" *\n";
		foreach ($commentOrder as $v) {
			if (isset($this->config['docComment']) && isset($this->config['docComment'][$v])) {
				$ret .= " * " . $this->config['docComment'][$v] . "\n";
			}
			elseif (isset($this->loader->docComment[$v])) {
				$ret .= " * " . $v . " " . $this->loader->docComment[$v] . "\n";
			}
		}
		$ret.=" */\n";

		$ret .= $this->generateDocCommentNamespace();

		$ret .= "\n";
		$ret .= 'use System\DbRecord\\' . $this->extends['mapper'] . ';' . "\n";
		$ret .= "\n";

		$ret.='class ' . $this->config['class'] . 'Mapper extends ' . $this->extends['mapper'] . "\n";
		$ret.='{' . "\n";
		$ret.='}' . "\n";
		return $ret;
	}





	private function getDefaultConfig()
	{
		return (isset($this->config['namespace']) ? ($this->config['namespace'] . '\\') : NULL) . $this->config['DEFAULT_CONFIG'];
	}





	private function getDefaultValidator()
	{
		return (isset($this->config['namespace']) ? ($this->config['namespace'] . '\\') : NULL) . $this->config['DEFAULT_VALIDATOR'];
	}





	private function getDefaultMapper()
	{
		return (isset($this->config['namespace']) ? ($this->config['namespace'] . '\\') : NULL) . $this->config['DEFAULT_MAPPER'];
	}





	public function generateRecordClass()
	{
		$ret = '<?php' . "\n\n\n";
		$ret .= $this->generateDocCommentNamespace();

		$ret .= "\n";
		$ret .= 'use System\DbRecord\\' . $this->extends['record'] . ';' . "\n";
		$ret .= "\n";

		$ret .= $this->generateDocComment();

		$ret.='class ' . $this->config['class'] . ' extends ' . (isset($this->config['extends']) ? $this->config['extends'] : $this->extends['record']) . "\n";
		$ret.='{' . "\n";
				
		if (isset($this->config['table'])) {
			$ret.='	protected static $_table = "' . $this->config['table'] . '";' . "\n";			
		}

		if (isset($this->config['mainIndex'])) {
			$ret.='	protected static $_mainIndex = "' . $this->config['mainIndex'] . '";' . "\n";
		}

		if (isset($this->config['topicIndex'])) {
			$ret.='	protected static $_topicIndex = "' . $this->config['topicIndex'] . '";' . "\n";
		}


		if (isset($this->config['DEFAULT_CONFIG'])) {
			$ret.='	const DEFAULT_CONFIG = "' . $this->getDefaultConfig() . '";' . "\n";
		}

		if (isset($this->config['DEFAULT_MAPPER'])) {
			$ret.='	const DEFAULT_MAPPER = "' . $this->getDefaultMapper() . '";' . "\n";
		}

		if (isset($this->config['DEFAULT_VALIDATOR'])) {
			$ret.='	const DEFAULT_VALIDATOR = "' . $this->getDefaultValidator() . '";' . "\n";
		}

		$ret.='}' . "\n";
		return $ret;
	}





	public function generateConfigClass()
	{
		$commentOrder = array("description", "@author", "@copyright");

		$ret = '<?php' . "\n\n\n";

		$ret.="/**\n";
		$ret.=' * Configuration class of ' . $this->config['class'] . "\n";
		$ret.=" *\n";

		foreach ($commentOrder as $v) {
			if (isset($this->config['docComment']) && isset($this->config['docComment'][$v])) {
				$ret.=" * " . $this->config['docComment'][$v] . "\n";
			}
			elseif (isset($this->loader->docComment[$v])) {
				$ret.=" * " . $v . " " . $this->loader->docComment[$v] . "\n";
			}
		}
		$ret.=" */\n";

		$ret .= $this->generateDocCommentNamespace();

		$ret .= "\n";
		$ret .= 'use System\DbRecord\DbRecordConfig;' . "\n";
		$ret .= "\n";

		$ret.='class ' . $this->config['DEFAULT_CONFIG'] . ' extends DbRecordConfig' . "\n";
		$ret.='{' . "\n";
		$ret.='	public function __construct()' . "\n";
		$ret.='	{' . "\n";

		if (isset($this->config['associations'])) {
			foreach ($this->config['associations'] as $key => $association) {

				$params = '\'localId\' => "' . $association['localId'] . '"';
				$params .= ', \'foreignId\' => "' . $association['foreignId'] . '"';

				if (isset($association['associatedCollectionClass'])) {
					$params .= ', \'associatedCollectionClass\' => "' . $association['associatedCollectionClass'] . '"';
				}

				if (isset($association['condition'])) {
					$params .= ', \'condition\' => "' . $association['condition'] . '"';
				}
				if (isset($association['through'])) {
					$params .= ', \'through\' => "' . $association['through'] . '"';
				}
				if (isset($association['throughLocalId'])) {
					$params .= ', \'throughLocalId\' => "' . $association['throughLocalId'] . '"';
				}
				if (isset($association['throughForeignId'])) {
					$params .= ', \'throughForeignId\' => "' . $association['throughForeignId'] . '"';
				}



				if ($association['type'] == "hasOne") {
					$aName = "HasOneAssociation";
				}
				elseif ($association['type'] == "hasMany") {
					$aName = "HasManyAssociation";
				}
				$ret.="\n";
				$ret.='		$this->associations[\'' . $key . '\'] = new \\' . __NAMESPACE__ . '\\' . $aName . '("' . $association['referenceClass'] . '", array(' . $params . '));' . "\n";
			}
		}


		$ret.='	}' . "\n";
		$ret.='}' . "\n";
		return $ret;
	}





	public function generateValidatorClass()
	{
		$commentOrder = array("description", "@author", "@copyright");

		$ret = '<?php' . "\n\n\n";

		$ret.="/**\n";
		$ret.=' * Validation class of ' . $this->config['class'] . "\n";
		$ret.=" *\n";

		foreach ($commentOrder as $v) {
			if (isset($this->config['docComment']) && isset($this->config['docComment'][$v])) {
				$ret.=" * " . $this->config['docComment'][$v] . "\n";
			}
			elseif (isset($this->loader->docComment[$v])) {
				$ret.=" * " . $v . " " . $this->loader->docComment[$v] . "\n";
			}
		}
		$ret.=" */\n";

		$ret .= $this->generateDocCommentNamespace();

		$ret .= "\n";
		$ret .= 'use System\DbRecord\DbValidator;' . "\n";
		$ret .= "\n";

		$ret.='class ' . $this->config['DEFAULT_VALIDATOR'] . ' extends DbValidator' . "\n";
		$ret.='{' . "\n";
		$ret.='	public function __construct($recordClass)' . "\n";
		$ret.='	{' . "\n";
		$ret.='		parent::__construct($recordClass);' . "\n";
		$ret.='	}' . "\n";
		$ret.='}' . "\n";
		return $ret;
	}





	public function buildClasses()
	{
		$this->buildRecordClass();

		if (isset($this->config['DEFAULT_CONFIG']) && $this->config['DEFAULT_CONFIG'] != DbRecord::DEFAULT_CONFIG) {
			$this->buildConfigClass();
		}

		if (isset($this->config['DEFAULT_MAPPER']) && $this->config['DEFAULT_MAPPER'] != DbRecord::DEFAULT_MAPPER) {
			$this->buildMapperClass();
		}

		if (isset($this->config['DEFAULT_VALIDATOR']) && $this->config['DEFAULT_VALIDATOR'] != DbRecord::DEFAULT_VALIDATOR) {
			$this->buildValidatorClass();
		}
	}





	public function buildRecordClass()
	{
		$path = $this->loader->tempDir . str_replace("\\", "/", $this->config['namespace']) . "/";

		$fileRoot = $path . $this->config['class'];		
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		
		$fh = fopen($fileRoot . ".php", "w");
		fwrite($fh, $this->generateRecordClass());
		fclose($fh);
	}





	public function buildConfigClass()
	{
		$path = $this->loader->tempDir . str_replace("\\", "/", $this->config['namespace']) . "/";
		
		$fileRoot = $path . $this->config['class'];
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}

		$fh = fopen($fileRoot . "Config.php", "w");
		fwrite($fh, $this->generateConfigClass());
		fclose($fh);
	}





	public function buildValidatorClass()
	{
		$path = $this->loader->tempDir . str_replace("\\", "/", $this->config['namespace']) . "/";
		
		$fileRoot = $path . $this->config['class'];
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}

		$fh = fopen($fileRoot . "Validator.php", "w");
		fwrite($fh, $this->generateValidatorClass());
		fclose($fh);
	}





	public function buildMapperClass()
	{
		$path = $this->loader->tempDir . str_replace("\\", "/", $this->config['namespace']) . "/";
		
		$fileRoot = $path . $this->config['class'];
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}

		$fh = fopen($fileRoot . "Mapper.php", "w");
		fwrite($fh, $this->generateMapperClass());
		fclose($fh);
	}

	/*	 * ******************* DbRecordLoader private methods ******************* */





	private function getTableName()
	{
		return $this->getConnection()->translate($this->config['table']);
	}





	private function getDatabaseInfo()
	{
		if (!isset($this->cache['databaseInfo'][0])) {
			$this->cache['databaseInfo'][0] = $this->getConnection()->getDatabaseInfo();
		}
		return $this->cache['databaseInfo'][0];
	}





	private function getTable()
	{
		if (!isset($this->cache['databaseInfo']['table'][0])) {
			$db = $this->getDatabaseInfo();
			$this->cache['databaseInfo']['table'][0] = $db->getTable($this->getTableName());
		}
		return $this->cache['databaseInfo']['table'][0];
	}





	private function getTableColumns()
	{
		if (!isset($this->cache['databaseInfo']['table']['columns'])) {
			$this->cache['databaseInfo']['table']['columns'] = $this->getTable()->getColumns();
		}
		return $this->cache['databaseInfo']['table']['columns'];
	}

	/**
	 * Returns metadata for all foreign keys in a table.
	 *
	 * @author Jakub Vrána original code from Adminer
	 * @author Martin Sadový convert to dibi
	 *
	 * @param  string
	 * @return array
	 */
	/* public function getForeignKeys() {
	  if (!isset($this->cache['databaseInfo']['table']['foreignKeys'])) {
	  static $pattern = '(?:[^`]|``)+';
	  static $onActions = array('RESTRICT', 'NO ACTION', 'CASCADE', 'SET NULL', '');

	  $foreignKeys = array();
	  $result = dibi::getConnection()->query("SHOW CREATE TABLE `".$this->getTableName()."`")->fetch(TRUE);

	  if ($result) {
	  preg_match_all("~CONSTRAINT `($pattern)` FOREIGN KEY \\(((?:`$pattern`,? ?)+)\\) REFERENCES `($pattern)`(?:\\.`($pattern)`)? \\(((?:`$pattern`,? ?)+)\\)(?: ON DELETE (" . implode("|", $onActions) . "))?(?: ON UPDATE (" . implode("|", $onActions) . "))?~",
	  $result['Create Table'], $matches, PREG_SET_ORDER);
	  foreach ($matches as $match) {
	  preg_match_all("~`($pattern)`~", $match[2], $source);
	  preg_match_all("~`($pattern)`~", $match[5], $target);
	  $foreignKeys[$match[1]] = array(
	  "name" => $match[1],
	  "local" => $source[1],
	  "table" => strlen($match[4]) ? $match[4] : $match[3],
	  "foreign" => $target[1],
	  "onDelete" => $match[6],
	  "onUpdate" => $match[7],
	  );
	  }
	  }
	  $this->cache['databaseInfo']['table']['foreignKeys']=$foreignKeys;
	  }
	  return $this->cache['databaseInfo']['table']['foreignKeys'];
	  } */
}

class DbRecordLoaderTreeObject extends DbRecordLoaderObject
{

	protected $extends = array(
		'record' => "DbTreeRecord",
		'mapper' => "DbTreeMapper",
	);



}
