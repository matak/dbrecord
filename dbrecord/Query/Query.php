<?php

namespace dbrecord;

/**
 * Query
 * 
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 * 
 * 
 * 
 *
 * setWithTables / killer feature teto tridy, jak funguje?
 *
 * foreach(dibi::getConnection()->select("*, salesPrice as mix1, salesPrice + purchasePrice as mix2")->from(":solis:products")->where('id = %i', 1)->execute()->setWithTables(true) as $k => $row) {
 * 	dump($k);
 * 	dump($row);
 * }
 * foreach(dibi::getConnection()->select("*, salesPrice as mix1, salesPrice + purchasePrice as mix2")->from(":solis:products")->as('x')->where('id = %i', 1)->execute()->setWithTables(true) as $k => $row) {
 * 	dump($k);
 * 	dump($row);
 * }
 *
 * problem setWithTables
 *
 * SELECT pl.name, pr.name FROM (
 *
 * 	SELECT pl.name, pr.name FROM `solis__products` as p
 * 	JOIN solis__products_locales as pl ON (pl.idProduct = p.id AND pl.idStore = 1)
 * 	JOIN solis__producers as pr ON (pr.id= p.idProducer)
 *
 * 	) as t
 *
 * pokud bude více poddotazů, chybí nám alias u sloupečků jinak není možné se na ně dostat
 *
 * resenim by mohlo být aliasovat sluopecky stejným zněním jen místo tečky dát podtržítka
 *
 * 
 * 
 * dibi SQL builder via fluent interfaces. EXPERIMENTAL!
 *
 * @author     David Grudl
 * @package    dibi
 *
 * @property-read string $command
 * @property-read DibiConnection $connection
 * @property-read DibiResultIterator $iterator
 * @method DibiFluent select($field)
 * @method DibiFluent distinct()
 * @method DibiFluent from($table)
 * @method DibiFluent where($cond)
 * @method DibiFluent groupBy($field)
 * @method DibiFluent having($cond)
 * @method DibiFluent orderBy($field)
 * @method DibiFluent limit(int $limit)
 * @method DibiFluent offset(int $offset)
 */
class Query extends \DibiObject implements IDataSource
{

	const ALIAS_SEPARATOR = '__';

	const REMOVE = FALSE;

	/** @var array */
	public static $masks = array(
		'SELECT' => array('SELECT', 'DISTINCT', 'FROM', 'WHERE', 'GROUP BY',
			'HAVING', 'ORDER BY', 'LIMIT', 'OFFSET'),
		'UPDATE' => array('UPDATE', 'SET', 'WHERE', 'ORDER BY', 'LIMIT'),
		'INSERT' => array('INSERT', 'INTO', 'VALUES', 'SELECT'),
		'DELETE' => array('DELETE', 'FROM', 'USING', 'WHERE', 'ORDER BY', 'LIMIT'),
	);

	/** @var array  default modifiers for arrays */
	public static $modifiers = array(
		'SELECT' => '%n',
		'FROM' => '%n',
		'IN' => '%in',
		'VALUES' => '%l',
		'SET' => '%a',
		'WHERE' => '%and',
		'HAVING' => '%and',
		'ORDER BY' => '%by',
		'GROUP BY' => '%by',
	);

	/** @var array  clauses separators */
	public static $separators = array(
		'SELECT' => ',',
		'FROM' => ',',
		'WHERE' => 'AND',
		'GROUP BY' => ',',
		'HAVING' => 'AND',
		'ORDER BY' => ',',
		'LIMIT' => FALSE,
		'OFFSET' => FALSE,
		'SET' => ',',
		'VALUES' => ',',
		'INTO' => FALSE,
	);

	/** @var array  clauses */
	public static $clauseSwitches = array(
		'JOIN' => 'FROM',
		'INNER JOIN' => 'FROM',
		'LEFT JOIN' => 'FROM',
		'RIGHT JOIN' => 'FROM',
	);

	/** @var DibiConnection */
	private $connection;

	/** @var string */
	private $command;

	/** @var array */
	private $clauses = array();

	/** @var array */
	private $flags = array();

	/** @var array */
	private $cursor;

	/** @var DibiHashMap  normalized clauses */
	private static $normalizer;

	
	/** @var EntityRepository */
	protected $repository;

	/**
	 * Pripojene tabulky. Primarne urceno na hlidani duplicit.
	 *
	 * 'base' => array(
	 * 	'pk' => NULL,
	 * 	'on' => NULL,
	 * 	'association' => NULL,
	 * )
	 *
	 */
	protected $tables = array();

	/** @var array		Used aliases of tables */
	protected $as = array();

	/** @var array		Used selects of tables */
	protected $selects = array();


	/**
	 * 
	 * @param \dbrecord\EntityRepository $repository
	 */
	public function __construct(EntityRepository $repository)
	{
		$this->repository = $repository;
		
		if (self::$normalizer === NULL) {
			self::$normalizer = new \DibiHashMap(array(__CLASS__, '_formatClause'));
		}
		
	}






	public function __call($clause, $args)
	{
		if (strncmp($clause, 'findBy', 6) === 0) { // single record
			$name = substr($clause, 6);

			$parts = array_map('lcfirst', explode('And', $name));

			if (count($parts) !== count($args)) {
				throw new \Nette\InvalidArgumentException("Magic find expects " . count($parts) . " parameters, but " . count($args) . " was given.");
			}

			return $this->findBy(array_combine($parts, $args));
		}

		switch ($clause) {
			// callback na # je docela nebezpecny, napr. regulary mohou obsahovat # atd. tedy pokud nechci aby se prekladal sign # tak pouziju rselect
			case 'rselect':
				if ($args[0] === false) {
					$this->selects = array();
				}
				return $this->__callQueryClause('select', $args);
				break;
				
			// callback na # je docela nebezpecny, napr. regulary mohou obsahovat # atd. tedy pokud nechci aby se prekladal sign # tak pouziju rselect
			case 'rwhere':
				return $this->__callQueryClause('where', $args);
				break;

			case 'select':
				if ($args[0] === false) {
					$this->selects = array();
				}
				else {

					foreach ($args as $k => $arg) {
						$earg = explode(',', $arg);
						$items = array();

						foreach ($earg as $v) {
							$v = trim(preg_replace_callback('~#[a-z0-9_\\.*]+~i', array($this, 'resolveSelect'), $v));
							// vyrad NULL a prazdne hodnoty
							if (!empty($v)) {
								$items[] = $v;
							}
						}

						if (($arg = implode(",", array_filter($items, function ($item) {
									return (bool) $item;
								})))) {
							$args[$k] = $arg;
						}
						else {
							unset($args[$k]);
						}

						#preg_replace_callback('~#[a-z0-9_\\.*]+~i', array($this, 'resolveSelect'), $arg);
					}
				}
				
				if (!count($args)) {
					return $this;
				}
				
				return $this->__callQueryClause('select', $args);
				break;



			case 'leftJoin':
			case 'join':
			case 'from':

				if ($args[0]{0} == '#') {
					$as = $this->getTableAs($this->resolveTable($args[0]));
					return $this->__callQueryClause($clause, (array) $as['table'])
									->as($as['as']);
				}
				else {
					return $this->__callQueryClause($clause, $args);
				}

				break;




			case 'as':
				if (count($args) > 1) {
					throw new \LogicException('WTF? as() can be declare only with one alias! Or you should update DbRecordFluent!');
				}

				$this->as[] = $args[0];

				return $this->__callQueryClause('as', $args);

				break;



			case 'orderBy':
			case 'on':
			case 'where':
				$args = $this->resolve($clause, $args);
				return $this->__callQueryClause($clause, $args);
				break;

			default:
				return $this->__callQueryClause($clause, $args);
				break;
		}
	}



	/**
	 * Appends new argument to the clause.
	 * @param  string clause name
	 * @param  array  arguments
	 * @return self
	 */
	public function __callQueryClause($clause, $args)
	{
		$clause = self::$normalizer->$clause;

		// lazy initialization
		if ($this->command === NULL) {
			if (isset(self::$masks[$clause])) {
				$this->clauses = array_fill_keys(self::$masks[$clause], NULL);
			}
			$this->cursor = & $this->clauses[$clause];
			$this->cursor = array();
			$this->command = $clause;
		}

		// auto-switch to a clause
		if (isset(self::$clauseSwitches[$clause])) {
			$this->cursor = & $this->clauses[self::$clauseSwitches[$clause]];
		}

		if (array_key_exists($clause, $this->clauses)) {
			// append to clause
			$this->cursor = & $this->clauses[$clause];

			// TODO: really delete?
			if ($args === array(self::REMOVE)) {
				$this->cursor = NULL;
				return $this;
			}

			if (isset(self::$separators[$clause])) {
				$sep = self::$separators[$clause];
				if ($sep === FALSE) { // means: replace
					$this->cursor = array();

				} elseif (!empty($this->cursor)) {
					$this->cursor[] = $sep;
				}
			}

		} else {
			// append to currect flow
			if ($args === array(self::REMOVE)) {
				return $this;
			}

			$this->cursor[] = $clause;
		}

		if ($this->cursor === NULL) {
			$this->cursor = array();
		}

		// special types or argument
		if (count($args) === 1) {
			$arg = $args[0];
			// TODO: really ignore TRUE?
			if ($arg === TRUE) { // flag
				return $this;

			} elseif (is_string($arg) && preg_match('#^[a-z:_][a-z0-9_.:]*\z#i', $arg)) { // identifier
				$args = array('%n', $arg);

			} elseif (is_array($arg) || ($arg instanceof Traversable && !$arg instanceof self)) { // any array
				if (isset(self::$modifiers[$clause])) {
					$args = array(self::$modifiers[$clause], $arg);

				} elseif (is_string(key($arg))) { // associative array
					$args = array('%a', $arg);
				}
			} // case $arg === FALSE is handled above
		}

		foreach ($args as $arg) {
			if ($arg instanceof self) {
				$arg = "($arg)";
			}
			$this->cursor[] = $arg;
		}

		return $this;
	}




	/**
	 * Switch to a clause.
	 * @param  string clause name
	 * @return self
	 */
	public function clause($clause, $remove = FALSE)
	{
		$this->cursor = & $this->clauses[self::$normalizer->$clause];

		if ($remove) { // deprecated, use removeClause
			trigger_error(__METHOD__ . '(..., TRUE) is deprecated; use removeClause() instead.', E_USER_NOTICE);
			$this->cursor = NULL;

		} elseif ($this->cursor === NULL) {
			$this->cursor = array();
		}

		return $this;
	}



	/**
	 * Removes a clause.
	 * @param  string clause name
	 * @return self
	 */
	public function removeClause($clause)
	{
		$this->clauses[self::$normalizer->$clause] = NULL;
		return $this;
	}

	


	/**
	 * Change a SQL flag.
	 * @param  string  flag name
	 * @param  bool  value
	 * @return self
	 */
	public function setFlag($flag, $value = TRUE)
	{
		$flag = strtoupper($flag);
		if ($value) {
			$this->flags[$flag] = TRUE;
		} else {
			unset($this->flags[$flag]);
		}
		return $this;
	}


	/**
	 * Is a flag set?
	 * @param  string  flag name
	 * @return bool
	 */
	final public function getFlag($flag)
	{
		return isset($this->flags[strtoupper($flag)]);
	}


	/**
	 * Returns SQL command.
	 * @return string
	 */
	final public function getCommand()
	{
		return $this->command;
	}



	

	/********************* executing ****************d*g**/


	/**
	 * Generates and executes SQL query.
	 * @param  mixed what to return?
	 * @return DibiResult|int  result set object (if any)
	 * @throws DibiException
	 */
	public function execute($return = NULL)
	{
		$res = $this->query($this->_export());
		return $return === \dibi::IDENTIFIER ? $this->connection->getInsertId() : $res;
	}
	


	/**
	 * Generates, executes SQL query and fetches the single row.
	 * @return DibiRow|FALSE  array on success, FALSE if no next record
	 */
	public function fetch()
	{
		if ($this->command === 'SELECT') {
			return $this->query($this->_export(NULL, array('%lmt', 1)))->fetch();
		} else {
			return $this->query($this->_export())->fetch();
		}
	}


	/**
	 * Like fetch(), but returns only first field.
	 * @return mixed  value on success, FALSE if no next record
	 */
	public function fetchSingle()
	{
		if ($this->command === 'SELECT') {
			return $this->query($this->_export(NULL, array('%lmt', 1)))->fetchSingle();
		} else {
			return $this->query($this->_export())->fetchSingle();
		}
	}


	/**
	 * Fetches all records from table.
	 * @param  int  offset
	 * @param  int  limit
	 * @return array
	 */
	public function fetchAll($offset = NULL, $limit = NULL)
	{
		return $this->query($this->_export(NULL, array('%ofs %lmt', $offset, $limit)))->fetchAll();
	}


	/**
	 * Fetches all records from table and returns associative tree.
	 * @param  string  associative descriptor
	 * @return array
	 */
	public function fetchAssoc($assoc)
	{
		return $this->query($this->_export())->fetchAssoc($assoc);
	}


	/**
	 * Fetches all records from table like $key => $value pairs.
	 * @param  string  associative key
	 * @param  string  value
	 * @return array
	 */
	public function fetchPairs($key = NULL, $value = NULL)
	{
		return $this->query($this->_export())->fetchPairs($key, $value);
	}


	/**
	 * Required by the IteratorAggregate interface.
	 * @param  int  offset
	 * @param  int  limit
	 * @return DibiResultIterator
	 */
	public function getIterator($offset = NULL, $limit = NULL)
	{
		return $this->query($this->_export(NULL, array('%ofs %lmt', $offset, $limit)))->getIterator();
	}


	
	
	
	/**
	 * Alias to find function.
	 * 
	 * @param string|int|float $primary
	 * @return type
	 */
	public function object($primary = NULL)
	{
		return $this->find($primary);
	}





	/**
	 * Fetch single row from database based on defined fluent and facultative primary key.
	 *
	 * @param mixed $primary
	 * @return DbRecord
	 */
	public function find($primary = NULL)
	{
		if ($primary !== NULL) {
			$metadata = $this->repository->getMetadata();
			$pk = $metadata->getPrimaryColumn();
			$this->where('#.' . $pk . ' = %' . $metadata->getType($pk), $primary);
		}
		
		$values = $this->limit(1)->toArray();

		
		/* 		
		//no more exceptions on not founded item in dtb
		if ( !$values ) {
			throw new NotFoundException("Item was not found!");
		} 
		*/

		
		if ($values) {
			return $this->repository->createObjectFromValues($values[0]);
		}
		
		return NULL;
	}

	


	/**
	 * Fetch single row from database based on defined fluent and obligatory conditions defined in array.
	 *
	 * @param mixed $primary
	 * @return DbRecord
	 */
	public function findBy(array $conditions)
	{
		$metadata = $this->repository->getMetadata();

		foreach ($conditions as $key => $value) {
			$this->where('#.' . $key . ' = %' . $metadata->getType($key), $value);
		}

		$values = $this->limit(1)->toArray();

		
		/* 		
		//no more exceptions on not founded item in dtb
		if ( !$values ) {
			throw new NotFoundException("Item was not found!");
		} 
		*/

		
		if ($values) {
			return $this->repository->createObjectFromValues($values[0]);
		}
		
		return NULL;
	}

	
	
	
	
	/**
	 * Fetches all single values form table.
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function findAllResults()
	{
		$result = $this->execute();

		$data = array();
		while ($row = $result->fetch()) {
			$data[] = $row[key($row)];
		}

		return $data;
	}
	
	
	
	
	
	
	

	/**
	 * Generates and prints SQL query or it's part.
	 * @param  string clause name
	 * @return bool
	 */
	public function test($clause = NULL)
	{
		return $this->repository->getConnection()->test($this->_export($clause));
	}
	

	

	/**
	 *	Tovarnicka na colekci
	 */
	public function collection()
	{
		return $this->repository->collection($this);
	}	


	/**
	 * 
	 * @return int
	 */
	public function count()
	{
		$query = clone $this;
		
		$query->removeClause('orderBy');
		$query->removeClause('limit');
		$query->removeClause('offset');
		
		$q = (string) $query;
		
		// SELECT neco, neco, (select neco from neco) as neco FROM ...
		// nahrad za SELECT count(*) FROM
		
		$_q = strtolower($q);
		
		// nahrad vse v zavorkach za stejne dlouhe prazdne retezce
		$str = preg_replace_callback("/\(.+\)/", function($matches) {
											return str_repeat(" ", strlen($matches[0]));
										}, $_q);

		// ted uz bude jen jedno prvni " from "
		$q = "SELECT count(*) FROM " . substr($q, strpos($str, " from ") + 6);
		return (int) $this->repository->getConnection()->query($q)->fetchSingle();
	}
	
	
	/**
	 * @return DibiResult
	 */
	private function query($args)
	{
		return $this->repository->getConnection()->query($args);
	}
	
	/********************* exporting ****************d*g**/

	

	/**
	 * Returns SQL query.
	 * @return string
	 */
	final public function __toString()
	{
		try {
			return $this->repository->getConnection()->translate($this->_export());
		} 
		catch (Exception $e) {
			trigger_error($e->getMessage(), E_USER_ERROR);
		}
	}

	
	
	/**
	 * Generates parameters for DibiTranslator.
	 * @param  string clause name
	 * @return array
	 */
	protected function _export($clause = NULL, $args = array())
	{
		
		// prepareExport
		foreach ($this->tables as $tableAs => $as) {
			// dopln primary key u vsech aliasu
			foreach ($as['pk'] as $pk) {
				if (!isset($this->selects[$tableAs][$pk])) {
					$this->selects[$tableAs][$pk] = true;
					$this->select($tableAs . '.`' . $pk . '` as ' . $tableAs . '__' . $pk);
				}
			}

			//TODO ? dopln primary key ke vsem parentum
			// zjisti jestli tento alias byl k fluentu pripojen
			if (!in_array($tableAs, $this->as)) {
				$this->generateJoin($tableAs);
			}
		}
		
		
		
		if ($clause === NULL) {
			$data = $this->clauses;

		} 
		else {
			$clause = self::$normalizer->$clause;
			if (array_key_exists($clause, $this->clauses)) {
				$data = array($clause => $this->clauses[$clause]);
			} else {
				return array();
			}
		}

		foreach ($data as $clause => $statement) {
			if ($statement !== NULL) {
				$args[] = $clause;
				if ($clause === $this->command && $this->flags) {
					$args[] = implode(' ', array_keys($this->flags));
				}
				foreach ($statement as $arg) {
					$args[] = $arg;
				}
			}
		}

		return $args;
	}

	
	

	/**
	 * Format camelCase clause name to UPPER CASE.
	 * @param  string
	 * @return string
	 * @internal
	 */
	public static function _formatClause($s)
	{
		if ($s === 'order' || $s === 'group') {
			$s .= 'By';
			trigger_error("Did you mean '$s'?", E_USER_NOTICE);
		}
		return strtoupper(preg_replace('#[a-z](?=[A-Z])#', '$0 ', $s));
	}


	public function __clone()
	{
		// remove references
		foreach ($this->clauses as $clause => $val) {
			$this->clauses[$clause] = & $val;
			unset($val);
		}
		$this->cursor = & $foo;
	}

	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	



	

	
	
	
	
	
	
	
	
	
	
	
	

	
	
	protected function generateJoin($tableAs)
	{
		$as = $this->getTableAs($tableAs);
		// vsechno ma sve misto, az po vytvoreni joinu, znovu zavolame generovani pro parenta, hrozi zacykleni
		if (!in_array($as['parentAs'], $this->as)) {
			$joinAs = $as['parentAs'];
		}

		if ($as['association']->getThrough()) {
			$on = $as['parentAs'] . '.' . $as['association']->getLocalId() . ' = through_' . $as['as'] . '.' . $as['association']->getThroughLocalId();

			$this->leftJoin($as['association']->getThrough())
					->as("through_" . $as['as'])
					->on($on);

			$on = $as['as'] . '.' . $as['association']->getForeignId() . ' = through_' . $as['as'] . '.' . $as['association']->getThroughForeignId();

			$this->join($as['table'])
					->as($as['as'])
					->on($on);
		}
		else {
			if (!$as['association']->getCondition()) {
				$on = $as['as'] . '.' . $as['association']->getForeignId() . ' = ' . $as['parentAs'] . '.' . $as['association']->getLocalId();
			}
			else {
				$on = preg_replace_callback('~#[a-z0-9_\\.*]+~i', array($this, 'resolveSqlSign'), $as['association']->getCondition());
			}

			$this->leftJoin($as['table'])
					->as($as['as'])
					->on($on);
		}


		if (isset($joinAs)) {
			$this->generateJoin($joinAs);
		}
	}






	/**
	 * Ziska result a vytvori asociovane pole, vcetně vsech asociaci v selectu.
	 *
	 * U kazde asociace si řekne o primary key, pokud vysledek leftjoin nebude
	 * mit pk, pak se asociace ani nevytvori, je to jediny zpusob jak poznat,
	 * zda asociace existuje nebo ne.
	 */
	public function toArray()
	{
		// result
		$out = array();

		// uzavreme dotaz, a vygenerujeme pripadne posledni aliasy a join
		$result = $this->execute();

		// definice pouzity tabulek byly naplneny po zavolani execute, nacteme si aktualni pole a vytvorime index container pro kazdy radek
		$tables = $this->tables;
		$tablesindex = array();
		foreach ($tables as $k => $v) {
			$tablesindex[$k] = array();
		}


		// vsechny radky
		foreach ($result as $index => $row) {
			// unikatni hash slozeny z klicu pro dany objekt/asociaci
			$pkhash = $this->pkHash($tables['base'], $row);
			// vytvorime container a index containeru
			// z dtb nam muze vylezt x radku ktere patri do jednoho konkretniho jde o asociace typu leftjoin hlavne
			$pkindex = $this->pkIndex($tablesindex['base'], $out, $pkhash);

			// zakladni base container prirazena reference
			$outrow = & $out[$pkindex];

			// vsechny hodnoty v radku
			foreach ($row as $key => $value) {
				// vzdy je to bud klic nebo klic __ to znaci aliasy
				// pokud je to jen klic pak ho priradme k base tabulce, DbRecord ho bude ignorovat
				$path = explode(self::ALIAS_SEPARATOR, $key);
				// nazev sloupecku si vezmeme
				$column = array_pop($path);

				// pouze jedna hodnota znamena ze jde o vypocitany sloupecek nepatrici zadne tabulce
				// minimlalne jedna musi byt vzdy, mysql nevrati prazdny sloupec bez jmena
				// base musi byt "base" jinak to jde mimo dbrecord a priradime to obecnemu cursoru
				if (count($path) == 1) {
					$outrow[$column] = $value;
					continue;
				}

				// v kazdem pripade jde o asociaci a musime probublavat
				// vyresetuj nastaveni cursoru, vrat se zase k base a konkretnimu radku
				$cursor = & $outrow;

				// nova rada nove primary pro radek a asociaci
				$pks = array();

				// zacni u base, vse se odviji od base
				$keyAssocTable = "base";

				// Odstranit base
				unset($path[0]);

				//	Projit vsechny asociace
				foreach ($path as $index => $assocTable) {
					$keyAssocTable .= self::ALIAS_SEPARATOR . $assocTable;

					if (!array_key_exists($keyAssocTable, $pks)) {
						$pkhash = $this->pkHash($tables[$keyAssocTable], $row);
						$pks[$keyAssocTable] = $pkhash;
					}

					//	Vytvorit kontainer.
					if (!array_key_exists($assocTable, $cursor)) {
						// pri left join nekdy dojde k tomu ze se najoinuje asociace, 
						// tedy dalsi tabulka, ktera nema zadna data a pak by se nemela ani nacist asociace a mela by se oznacit za neexistujici
						if (!$pks[$keyAssocTable]) {
							$cursor[$assocTable] = NULL;
							// uz neni potreba pokracoat tahle asociace je mrtva
							continue;
						}
						else {
							$cursor[$assocTable] = array();
						}
					}

					// mrtva asociace, nema primary key, vynechat
					if (!$pks[$keyAssocTable]) {
						continue;
					}

					// pouze hasMany association ma indeoxvane pole u hasOne neni zadne pole
					if ($tables[$keyAssocTable]['association']->getType() == Association::HAS_ONE) {

						//	Dalsi podasociace?
						if (isset($path[$index + 1])) {

							//	Poznacit kde jsem.
							$cursor = & $cursor[$assocTable];
						}
						//	Priradit hodnotu.
						else {
							//	a jsme u sloupecku
							$cursor[$assocTable][$column] = $value;
						}
					}
					else {
						// primary pro asociaci
						$pkindex = $this->pkIndex($tablesindex[$keyAssocTable], $cursor[$assocTable], $pks[$keyAssocTable]);

						//	Dalsi podasociace?
						if (isset($path[$index + 1])) {

							//	Poznacit kde jsem.
							$cursor = & $cursor[$assocTable][$pkindex];
						}
						//	Priradit hodnotu.
						else {
							// a jsme u sloupecku
							$cursor[$assocTable][$pkindex][$column] = $value;
						}
					}
				}
			}
		}

		return $out;
	}





	private function getTableAsByKey($key)
	{
		return substr($key, 0, strrpos($key, self::ALIAS_SEPARATOR));
	}





	public function pkHashByKey($key, $row)
	{
		$tableAs = $this->getTableAsByKey($key);
		return $this->pkHashByTableAs($tableAs, $row);
	}





	public function pkHashByTableAs($tableAs, $row, $noalias = true)
	{
		if (!($table = $this->getTableAs($tableAs))) {
			return NULL;
		}
		return $this->pkHash($table, $row, $noalias);
	}





	private function pkHash($table, $row, $noalias = true)
	{
		// optimalizacni varianta, spojovani stringu bude rychlejsi nez tisickrat provest implode
		if ($noalias) {
			$h = '';
			foreach ($table['pk'] as $pk) {
				$h .= $row[$table['as'] . self::ALIAS_SEPARATOR . $pk];
			}
		}
		// potrebujeme rozdelit klice podle aliasu
		else {
			$h = array();
			foreach ($table['pk'] as $pk) {
				$h[] = $row[$table['as'] . self::ALIAS_SEPARATOR . $pk];
			}
			$h = implode(self::ALIAS_SEPARATOR, $h);
		}
		return $h;
	}





	private function pkIndex(&$index, $out, $hash)
	{
		if (!isset($index[$hash])) {
			$pkindex = $index[$hash] = count($out);
			$out[$pkindex] = array();
		}
		return $index[$hash];
	}





	private function setTableAs($tableAs, $table, Metadata\EntityMetadata $metadata, $parentTableAs = NULL, $association = NULL)
	{
		$this->tables[$tableAs] = array(
			'table' => $table,
			'as' => $tableAs,
			'metadata' => $metadata,
			'pk' => $metadata->getPrimaryColumnsKeys(),
			'parentAs' => $parentTableAs,
			'association' => $association,
		);

		return $this->tables[$tableAs];
	}





	private function getTableAs($tableAs)
	{

		return isset($this->tables[$tableAs]) ? $this->tables[$tableAs] : NULL;
	}





	private function resolveTable($arg)
	{

		if ($arg == '#') {
			$path = array('base');
		}
		else {
			$path = explode('.', trim($arg, '.#'));
			array_unshift($path, "base");
		}

		foreach ($path as $index => $item) {

			if ($item == 'base') {
				$metadata = $this->repository->getMetadata();
				$table = $metadata->getTable();
				$tableAs = 'base';
				if (!$this->getTableAs($tableAs)) {
					$this->setTableAs($tableAs, $table, $metadata);
				}
			}
			else {
				//	tabulka
				$association = $metadata->getAssociation($item);
				$associationClassName = $association->getReferenceClass();
				$associationMetadata = $this->repository->getEntityManager()->getRepository($associationClassName)->getMetadata();

				$parentTable = $table;
				$parentTableAs = $tableAs;

				$table = $associationMetadata->getTable();
				$tableAs = $tableAs . self::ALIAS_SEPARATOR . $item;

				//	Nevytvaret novy stejny join. Jen, pokud je jina vazba, nebo jiny nazev.
				if (!$this->getTableAs($tableAs)) {
					$this->setTableAs($tableAs, $table, $metadata, $parentTableAs, $association);
				}
			}
		}

		return $tableAs;
	}





	protected function resolveSign($arg)
	{
		$arg = $arg[0];
		if ($last = strrpos($arg, '.')) {
			$tableAs = $this->resolveTable(substr($arg, 0, $last));
			$column = substr($arg, $last + 1);
		}
		else {
			$tableAs = $this->resolveTable('#');
			$column = trim($arg, '.#');
		}

		return array(
			$tableAs,
			$column,
		);
	}





	protected function resolveSelect($arg)
	{
		$rs = $this->resolveSign($arg);

		if ($rs[1] == "*") {
			$ret = array();
			$as = $this->getTableAs($rs[0]);
			foreach ($as['metadata']->getColumns() as $k => $v) {
				if (!isset($this->selects[$rs[0]][$k])) {
					$this->selects[$rs[0]][$k] = true;
					$ret[] = $rs[0] . ".`" . $k . "` as " . $rs[0] . "__" . $k;
				}
			}

			return implode(",", $ret);
		}
		else {
			if (!isset($this->selects[$rs[0]][$rs[1]])) {
				$this->selects[$rs[0]][$rs[1]] = true;
				return $rs[0] . ".`" . $rs[1] . "` as " . $rs[0] . "__" . $rs[1];
			}
			else {
				return '';
			}
		}
	}





	protected function resolveSqlSign($arg)
	{
		$rs = $this->resolveSign($arg);
		return $rs[0] . ".`" . $rs[1] . "`";
	}






	public function resolve($clause, $args)
	{
		switch ($clause) {
			case 'orderBy':
			case 'on':
			case 'where':

				foreach ($args as $k => $arg) {
					if (is_array($arg)) {
						$args[$k] = $this->resolve($clause, $arg);
					}
					else {
						$args[$k] = preg_replace_callback('~#[a-z0-9_\\.*]+~i', array($this, 'resolveSqlSign'), $arg);
					}
				}
				return $args;

				break;
			default:
				throw new \LogicException("Dont know clause " . $clause . "!");
				break;
		}
	}

}

/*
$input = '[sku, idProduct as id, foo, bar] %dotaz1 #Producer #Expedition';
preg_match('~^\[(?<select>.*)\]\s+(?<query>%\w+)\s+(?<objects>#\w+(?:\s+#\w+)*)$~Ui', $input, $match);
var_dump($match);
 * 
 */
