<?php


/**
 * DbRecordFluent
 *
 * setWithTables / killer feature teto tridy, jak funguje?
 *
 * foreach(dibi::getConnection()->select("*, salesPrice as mix1, salesPrice + purchasePrice as mix2")->from(":solis:products")->where('id = %i', 1)->execute()->setWithTables(true) as $k => $row) {
 *	dump($k);
 *	dump($row);
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
 *	SELECT pl.name, pr.name FROM `solis__products` as p
 *	JOIN solis__products_locales as pl ON (pl.idProduct = p.id AND pl.idStore = 1)
 *	JOIN solis__producers as pr ON (pr.id= p.idProducer)
 *
 *	) as t
 *
 * pokud bude více poddotazů, chybí nám alias u sloupečků jinak není možné se na ně dostat
 *
 * resenim by mohlo být aliasovat sluopecky stejným zněním jen místo tečky dát podtržítka
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace System\DbRecord;

class DbRecordFluent extends \DibiFluent
{


	/**
	 *	Oddelovac cesty v asociaci. Souvisi to se zanorenim kolekci v objektu.
	 */
	const ALIAS_SEPARATOR = '__';
	


	protected $recordClass;


	/** @var DbRecordCollection */
	protected $collection;



	/**
	 * Pripojene tabulky. Primarne urceno na hlidani duplicit.
	 *
	 * 'base' => array(
	 *	'pk' => NULL,
	 *	'on' => NULL,
	 *	'association' => NULL,
	 * )
	 *
	 */
	private $tables = array();

	/* Used aliases of tables */
	private $as = array();

	/* user selects */
	private $selects = array();


	/**
	 *
	 */
	public function __construct($recordClass)
	{
		parent::__construct($recordClass::connection());

		$this->recordClass = $recordClass;
	}

	public function getRecordClass()
	{
		return $this->recordClass;
	}

	public function getConfig()
	{
		$rclass = $this->recordClass;
		return $rclass::config();
	}



	/**
	 * Fetch single row from database based on defined fluent and facultative primary key.
	 *
	 * @param mixed $primary
	 * @return DbRecord
	 */
	public function find($primary = NULL)
	{

		$recordClass = $this->recordClass;

		if ($primary !== NULL) {

			$config = $recordClass::config();
			$pk = $config->getPrimaryColumn();
			$this->where('#.' . $pk . ' = %' .$config->getType($pk), $primary);
			
		}

		$result = $this
				->limit(1)
				->toArray();

		/* no more exceptions on not founded item in dtb
		 * if ( !$result ) {
			throw new NotFoundException("Item was not found!");
		}*/

		if ($result) {
			$item = new $recordClass($result[0]);
			$item->setState(DbRecord::STATE_EXISTING);
			return $item;
		}
		else {
			return NULL;
		}

	}
	
	
	


	/**
	 * Fetch single row from database based on defined fluent and obligatory conditions defined in array.
	 *
	 * @param mixed $primary
	 * @return DbRecord
	 */
	public function findBy(array $conditions)
	{

		$recordClass = $this->recordClass;
		$config = $recordClass::config();

		foreach ($conditions as $key => $value) {
			$this->where('#.' . $key . ' = %' .$config->getType($key), $value);
		}

		$result = $this
				->limit(1)
				->toArray();

		if ($result) {
			$item = new $recordClass($result[0]);
			$item->setState(DbRecord::STATE_EXISTING);
			return $item;
		}
		else {
			return NULL;
		}

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
	 *	Kdo odstartoval fluent.
	 */
	public function setCollection($collection)
	{
		$this->collection = $collection;
		
		return $this;
	}



	/**
	 *
	 */
	public function object($primary = NULL)
	{
		return $this->find($primary);
	}



	/**
	 *	Tovarnicka na colekci
	 */
	public function collection()
	{
		//	Existuje vazba na parenta?
		if (!$collection = $this->collection) {
			$class = $this->recordClass;
			$collectionClass = $class::DEFAULT_COLLECTION;
			$collection = new $collectionClass($class);
		}

		$collection->importFluent($this);

		return $collection;

	}

	/**
	 * @return int
	 *
	 *
	 */
	public function count()
	{
		$a = clone $this;
		$a->removeClause('orderBy');
		$a->removeClause('limit');
		$a->removeClause('offset');
		$q = (string)$a;
		
		// SELECT neco, neco, (select neco from neco) as neco FROM ...
		// nahrad za SELECT count(*) FROM
		
		
		$_q = strtolower($q);
		// nahrad vse v zavorkach za stejne dlouhe prazdne retezce
		$str = preg_replace_callback("/\(.+\)/", function($matches) {
											return str_repeat(" ", strlen($matches[0]));
										}, $_q);		
		// ted uz bude jen jedno prvni " from "
		$q = "SELECT count(*) FROM " . substr($q, strpos($str, " from ") + 6);
		return (int)$this->getConnection()->query($q)->fetchSingle();
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
	 * Last call before generating sql
	 */
	protected function _export($clause = NULL, $args = array())
	{
		foreach($this->tables as $tableAs => $as) {
			// dopln primary key u vsech aliasu
			foreach($as['pk'] as $pk) {
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

		return parent::_export($clause, $args);
	}


	/**
	 * Prevede dotaz do strukturovane pole.
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
		foreach($tables as $k => $v) {
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
						} else {
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
		if (! ($table = $this->getTableAs($tableAs)) ) {
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


	private function setTableAs($tableAs, $table, $config, $parentTableAs = NULL, $association = NULL)
	{
		$this->tables[$tableAs] = array(
			'table' => $table,
			'as' => $tableAs,
			'config' => $config,
			'pk' => $config->getPrimaryColumns(),
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
		} else {
			$path = explode('.', trim($arg, '.#'));
			array_unshift($path, "base");
		}

		foreach ($path as $index => $item) {

			if ($item == 'base') {
				$class = $this->recordClass;
				$config = $class::config();
				$table = $class::table();
				$tableAs = 'base';
				if (!$this->getTableAs($tableAs)) {
					$this->setTableAs($tableAs, $table, $config);
				}
			}
			else {
				//	tabulka
				$association = $config->getAssociation($item);
				if (empty($association)) {
					throw new \Nette\InvalidArgumentException("Entity `" . get_class($config) . "` has not association `$item`.");
				}
				$class = $association->getReferenceClass();
				$config = $class::config();

				$parentTable = $table;
				$parentTableAs = $tableAs;

				$table = $class::table();
				$tableAs = $tableAs . self::ALIAS_SEPARATOR . $item;

				//	Nevytvaret novy stejny join. Jen, pokud je jina vazba, nebo jiny nazev.
				if (!$this->getTableAs($tableAs)) {
					$this->setTableAs($tableAs, $table, $config, $parentTableAs, $association);
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

		} else {
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
			foreach($as['config']->getColumns() as $k => $v) {
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
			} else {
				return '';
			}
		}
	}

	protected function resolveSqlSign($arg)
	{
		$rs = $this->resolveSign($arg);
		return $rs[0] . ".`" . $rs[1] . "`";
	}


	/**
	 */
	public function __call($clause, $args)
	{
		if (strncmp($clause, 'findBy', 6) === 0) { // single record
			$method = 'find';
			$name = substr($clause, 6);

			$parts = array_map('lcfirst', explode('And', $name));

			if (count($parts) !== count($args)) {
				throw new \Nette\InvalidArgumentException("Magic find expects " . count($parts) . " parameters, but " . count($args) . " was given.");
			}

			return $this->findBy(array_combine($parts, $args));

		}

		switch($clause) {
			// callback na # je docela nebezpecny, napr. regulary mohou obsahovat # atd. tedy pokud nechci aby se prekladal sign # tak pouziju rselect
			case 'rselect':
					if ($args[0] === false) {
						$this->selects = array();
					}
					return parent::__call('select', $args);
				break;
			// callback na # je docela nebezpecny, napr. regulary mohou obsahovat # atd. tedy pokud nechci aby se prekladal sign # tak pouziju rselect
			case 'rwhere':
					return parent::__call('where', $args);
				break;

			case 'select':
					if ($args[0] === false) {
						$this->selects = array();

					} 
					else {
						
						foreach($args as $k => $arg) {
							$earg = explode(',', $arg);
							$items = array();

							foreach($earg as $v) {
								$v = trim(preg_replace_callback('~#[a-z0-9_\\.*]+~i', array($this, 'resolveSelect'), $v));
								// vyrad NULL a prazdne hodnoty
								if (!empty($v)) {
									$items[] = $v;
								}
							}

							if ( ($arg = implode(",", array_filter($items, function ($item) { return (bool) $item; }))) ) {
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
					return parent::__call('select', $args);
				break;



			case 'leftJoin':
			case 'join':
			case 'from':

				if ($args[0]{0} == '#') {
					$as = $this->getTableAs($this->resolveTable($args[0]));
					return parent::__call($clause, (array)$as['table'])
							->as($as['as']);
				}
				else {
					return parent::__call($clause, $args);
				}

				break;




			case 'as':
				if (count($args) > 1) {
					throw new \LogicException('WTF? as() can be declare only with one alias! Or you should update DbRecordFluent!');
				}

				$this->as[] = $args[0];

				return parent::__call('as', $args);

				break;



			case 'orderBy':
			case 'on':
			case 'where':
				$args = $this->resolve($clause, $args);
				return parent::__call($clause, $args);
				break;

			default:
				return parent::__call($clause, $args);
				break;
		}
	}

	public function resolve($clause, $args)
	{
		switch($clause) {
			case 'orderBy':
			case 'on':
			case 'where':

				foreach($args as $k => $arg) {
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
