<?php


/**
 * DbRecord
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

use Nette\FreezableObject,
		Nette\ObjectMixin;
use Nette\MemberAccessException;

abstract class Entity extends FreezableObject implements \ArrayAccess, IObjectContainerToFree
{
	/** @var EntityManager */
	private static $_em;

	/** @var array */
	protected $_data = array();

	/** @var HasManyAssociation[]|HasOneAssociation[] */
	private $_associations = array();

	/** @var array */
	private $_modified = array();

	/** @var string */
	private $_state;

	/** @var array */
	private $_errors = array();

	/**
	 * Last action i/u/d (insert, update, delete), this property is handled by mapper.
	 *
	 * @var string
	 */
	protected $_lastAction;

	/**
	 * Last affected rows
	 *
	 * @var int
	 */
	protected $_lastAffectedRows;


	/** @var bool */
	protected $_valid;

	/** \System\DbRecord\AssociatedCollection $belongsToCollection */
	public $belongsToCollection;

	/** \System\DbRecord\BelongsToAssociation $belongsToAssociation */
	public $belongsToAssociation;

	/** Settings used for construction process of object and all associations */
	public $_construction = array(
			'trimStrings' => false, /* kazdy string bude prohnan funkci trim() pro odstraneni prazdnych znaku ze zacatku a konce stringu */
			'mergeAssociations' => false, /* pokud je soucasti values pole kde klicem je associace, pak nevytvarime novy objekt ale rozsirime puvodni vytvorenou asociaci */
			'keepModified' => false, /* Keep modified all associations during seting values? */
			'nullableToNULL' => false, /* pokud pracujeme s null hodnotama je potreba osetrit vystup z formulare, protoze z formulare NULL nedostaneme pouze empty "" */
			'floatReplaceDecimals' => false, /* float muze byt omylem zadan s carkou coz databaze nebere, tedy prevedeme pokud je to zapnute */
		);

	/** @var Entity */
	/*protected $parent;*/


	const STATE_EXISTING = 'e';
	const STATE_NEW = 'n';
	const STATE_DELETED = 'd';

	/**
	 * DbRecord constructor.
	 * @param array $values
	 * @param int $state database state of data new/existed/deleted?
	 */
	public function __construct($values = NULL, $state = NULL, $construction = array())
	{
		// nejdrive musime setnout hodnoty pak teprve nastavit stav, protoze pro existujici recordy mazeme ze doslo k nejake modifikaci
		if ($values !== NULL) {
			$this->setValues($values, $construction);
		}

		if ($state === NULL) {
			$this->setState($this->detectState((array) $values), $construction);
		}
		else {
			$this->setState($state, $construction);
		}

	}
	
	
	/**
	 * 
	 * @param \dbrecord\EntityManager $em
	 * @return EntityManager
	 */
	public static function em(EntityManager $em = NULL)
	{
		if (!is_null($em)) {
			self::$_em = $em;
		}
		
		return self::$_em;
	}


	public function lastAction($action = NULL)
	{
		if (is_null($action)) {
			return $this->_lastAction;
		}
		
		$this->_lastAction = $action;

		return $this;
	}

	public function setLastAffectedRows($affectedRows)
	{
		$this->_lastAffectedRows = $affectedRows;

		return $this;
	}

	public function getLastAffectedRows()
	{
		return $this->_lastAffectedRows;
	}


	/**
	 *	Ktere kolekci patri. child -> parent
	 */
	public function setBelongsToCollection(AssociatedCollection $belongsToCollection)
	{
		$this->belongsToCollection = $belongsToCollection;
	}



	/**
	 * Detects record's state.
	 *
	 * @param array $input
	 *
	 * @return int
	 */
	protected function detectState(array $values)
	{
		$state = self::STATE_EXISTING;
		$primaryColumns = self::em()->getRepository(static::class)->getMetadata()->getPrimaryColumns();

		if (!is_array($primaryColumns)) {
			$primaryColumns = array($primaryColumns);
		}
		
		foreach ($primaryColumns as $key) {
			if (isset($values[$key])) {
				if ($values[$key] === NULL) {
					return self::STATE_NEW;
				}
			}
			else {
				return self::STATE_NEW;
			}
		}

		return $state;
	}



	/**
	 * Set all values as unmodified
	 */
	public function clearModified()
	{
		$this->_modified = array();
	}










	/**
	 * Multiple setter
	 * @param array $values
	 * @return DbRecord
	 */
	public function setValues($values, $construction = array())
	{
		// parametry plneni hodnot
		$this->_construction = $construction;

		foreach ($values as $key => $value) {
			try {
				$this->$key = $value;
			}
			catch(\Nette\MemberAccessException $e) {
				// pri hromadnem nastaveni properties se prebyvajici hodnoty vyhazuji - proto se výjimky zachytávají
			}
		}

		// cyklus probehl, zrusit
		$this->_construction = array();

		return $this;
	}










	/**
	 * Gets current primary key(s) formated in string, joined by underscore.
	 * @return array
	 */
	public function getPrimaryKey()
	{
		$config = static::config();
		$condition = array();
		foreach ($config->getPrimaryColumns() as $name)
			$condition[] = $this->$name;

		return implode("_", $condition);
	}





	/**
	 * Gets record's data values in array(column => value)
	 *
	 * Date type is modified to dtb value by set parameters, if not then like Y-m-d H:i:s
	 *
	 * @return array
	 */
	public function toArray($params = array())
	{
		$metadata = self::em()->getRepository(static::class)->getMetadata();
		$columns = $metadata->getColumnsKeys();
		$data = array();
		foreach ($columns as $key) {
			if (array_key_exists($key, $this->_data) || array_key_exists($key, $this->_associations)) {
				$type = $metadata->getColumnType($key);
				if ($type == 'd' || $type == 't') {
					$d = $this->$key;
					if (!$d) {
						$value = NULL;
					}
					elseif (isset($params['dateFormat'])) {
						$value = $d->format($params['dateFormat']);
					}
					else {
						$value = (string)$d;
					}
				}
				else {
					$value = $this->$key;
				}

				$data[$key] = $value;
			}
		}
		return $data;
	}






	/**
	 * Returns array of values with associations.
	 * @return array
	 */
	public function toAssociatedArray($associations = array(), $params = array())
	{
		$data = $this->toArray($params);
		foreach($this->getAssociations() as $key => $associationObject) {
			// treba neexistuje, kdyz neni povinna vytvori se NULL
			if (!$associationObject) {
				continue;
			}

			// pokud někdo pošle NULL, pak pustime vše
			if (is_null($associations)) {

			}
			// pokud neni NULL pustime jen to o co si uzivatel rekne (je tato asociace zahrnuta ve vypisu?)
			elseif (!in_array($key, $associations)) {
				continue;
			}

			$_data = $associationObject->toAssociatedArray($associations, $params);
			if (count($_data)) {
				$data[$key] = $_data;
			}
		}
		return $data;
	}




	/**
	 * Gets record's modified values in array(column => value)
	 * @return array
	 */
	public function getModifiedValues($types = false)
	{
		$config = self::config();
		$values = array();
		foreach ($this->_modified as $name => $bool) {
			$type = $config->getType($name);

			$value = $this->$name;
			// datum prevadime na string, pro spravny zapis do dtb
			if ($type == 'd') {
				// pokud je hodnota NULL a sloupec muze byt NULL a jde o datum neprevadime na string
				if (is_null($value)) {
					if (!$config->isNullable($name)) {
						$value = (string)$this->$name;
					}
				}
				else {
					$value = (string)$this->$name;
				}
			}

			if ($types == true) {
				$values[$name . "%" . $type] = $value;
			} else {
				$values[$name] = $value;
			}
		}

		return $values;
	}



	/**
	 * Checks if Record has unsaved changes.
	 * @return bool
	 */
	public function isDirty() {
		return count($this->_modified);
	}

	/**
	 * Checks if Record has modified db schema column value.
	 * @return bool
	 */
	public function isModified($name) {
		return isset($this->_modified[$name]);
	}

	/**
	 * Get state
	 * @return string
	 */
	public function getState() {
		return $this->_state;
	}

	/**
	 * Get state
	 * @return array|Association[]|HasManyAssociation[]|HasOneAssociation[]
	 */
	public function getAssociations()
	{
		return (array) $this->_associations;
	}

	/**
	 * Set state
	 * @param string $state
	 * @return DbRecord
	 */
	public function setState($state, $construction = array())
	{
		// mazeme informaci o modifikovanych zaznamech, aby nedochazelo pri save k updatu recordu, protoze jen nastavujeme jeho hodnoty, nic se nezmenilo
		if ($state == self::STATE_EXISTING && (!isset($construction['keepModified']) || $construction['keepModified'] !== true) ) {
			$this->clearModified();
		}

		$this->_state = $state;

		return $this;
	}

	/**
	 * Is record existing?
	 * @return bool
	 */
	public function isRecordExisting()
	{
		return $this->getState() === self::STATE_EXISTING;
	}


	/**
	 * Is record new?
	 * @return bool
	 */
	public function isRecordNew()
	{
		return $this->getState() === self::STATE_NEW;
	}


	/**
	 * Is record deleted?
	 * @return bool
	 */
	public function isRecordDeleted()
	{
		return $this->getState() === self::STATE_DELETED;
	}


	/**
	 * Returns property value. Do not call directly.
	 *
	 * @param  string  property name
	 * @return mixed   property value
	 * @throws MemberAccessException if the property is not defined.
	 */
	public function &__get($name)
	{

		try {
			$value = ObjectMixin::get($this, $name);
			return $value;

		}
		catch(\Nette\MemberAccessException $e) {
			$config = static::config();

			if (!$config->hasColumn($name)) {
				if ($config->isAssociation($name)) {
					// associace uz byla drive vytvorena, tak ji vratime
					if (array_key_exists($name, $this->_associations)) {
						$reference = $this->_associations[$name];
						return $reference;
					}

					// jak vidno vytvorena jeste nebyla zjistime o jaky typ se jedna
					$reference = $this->_associations[$name] = $config->getAssociation($name)->retrieveReferenced($this);
					return $reference;
				}

				throw $e;
			}

			if (array_key_exists($name, $this->_data)) {
				$value = $this->_data[$name];
				return $value; // PHP work-around (Only variable references should be returned by reference)
			}
			else {
				$defaults = $config->getDefaults();
				if (array_key_exists($name, $defaults)) {
					$value = $defaults[$name];
					return $value; // PHP work-around (Only variable references should be returned by reference)
				} else {
					$value = NULL;
					return $value;  // PHP work-around (Only variable references should be returned by reference)
				}
			}
		}
	}


	/**
	 * Sets value of a property. Do not call directly.
	 *
	 * @param  string  property name
	 * @param  mixed   property value
	 * @return void
	 * @throws MemberAccessException if the property is not defined or is read-only
	 */
	public function __set($name, $value)
	{
		$this->updating();

		try {

			ObjectMixin::set($this, $name, $value);

		}
		catch(\Nette\MemberAccessException $e) {
			$config = static::config();

			if (!$config->hasColumn($name)) {
				if (!$config->isAssociation($name)) {
					throw $e;
				}

				// mame mergovat?
				if (is_array($value) && isset($this->_construction['mergeAssociations']) && $this->_construction['mergeAssociations'] === true && isset($this->_associations[$name])) {
					$association = $this->_associations[$name];
					$association->setValues($value, $this->_construction);
					return;
				}


				$reference = $config->getAssociation($name)->saveReferenced($this, $value, $this->_construction);
				$this->_associations[$name] = $reference;

				return;
			}

			// hodnoty ktere mohou byt NULL a nemaji v dtb zadnou vychozi hodnotu nebudeme ukladat jako prazdne ale jako NULL
			// jinak by se do dtb zapsal prazdny string coz napr. u company, ico atd. nema vyznam, dokonce to i u ICO vyrazne skodi
			//TODO: docela potencionalni bug / asi by se to melo resit jinde - napr. parametrem v konstruktoru
			if (isset($this->_construction['nullableToNULL']) && $this->_construction['nullableToNULL'] === true) {
				if ($value === "" && $config->isNullable($name) && !$config->isMandatory($name)) {
					$value = NULL;
				}
			}


			if (!is_null($value)) {
				$type = $config->getType($name);
				switch ($type) {
					case "s":
						$value=(string)$value;
						// trimstrings oreze vsechny stringy funkci trim
						if (isset($this->_construction['trimStrings']) && $this->_construction['trimStrings'] === true) {
							$value = trim($value);
						}
						break;
					case "i":
						$value=(int)$value;
						break;
					case "f":
						if (isset($this->_construction['floatReplaceDecimals']) && $this->_construction['floatReplaceDecimals'] === true) {
							$value = preg_replace("/,/", ".", $value);
						}

						$value=(float)$value;
						break;
					case "d":
					case "t": // z dtb vzdy dostaneme tento tvar 2010-01-01 / z formu muzeme dostat ledasco / my budeme pracovat s DateTime / pri ukladani do dtb nebo do array bychom meli ulozit string, tedy Y-m-d H:i:s
						// povazujeme za NULL
						if (!$value || $value == '0000-00-00 00:00:00') {
							$value = NULL;
						}
						else {
							$_value = is_numeric($value) ? (int) $value : strtotime($value); // strtotime si poradí se stringy jako 2010-01-10 12:00, 10.1.2010 12:00, now atd., tedy při vkládání formulářů máme celkem volné ruce
							$value = new DateTime;
							$value->setTimestamp($_value);
						}
						break;

					default:
						throw new \Nette\InvalidArgumentException("Undefined data type!");
				}
			}

			if (!array_key_exists($name, $this->_data) || $this->_data[$name] !== $value) {
				$this->_data[$name] = $value;
				$this->_modified[$name] = true;
			}
		}
	}


	/**
	 * Is property defined?
	 *
	 * @param  string  property name
	 * @return bool
	 */
	public function __isset($name)
	{
		return ObjectMixin::has($this, $name) ? TRUE : ( isset($this->_data[$name]) ? TRUE : array_key_exists($name, $this->_associations) );
	}


	/**
	 * Unset of property.
	 *
	 * @param  string  property name
	 * @return void
	 * @throws MemberAccessException
	 */
	public function __unset($name) {
		$this->updating();

		try {
			parent::__unset($name);
		}
		catch(\Nette\MemberAccessException $e) {
			if (array_key_exists($name, $this->_data)) {
				unset($this->_data[$name]);
				unset($this->_modified[$name]);

			}
			elseif (array_key_exists($name, $this->_associations)) {
				unset($this->_associations[$name]);

			}
		}

	}

	/********************* validation ********************/

	/**
	 * Change valid / there is not use clasic setter, because of possible colision with the name of database column
	 * @return void
	 */
	public function changeValid($valid)
	{
		$this->_valid = $valid;
	}


	/**
	 * Is record valid?
	 * @return bool
	 */
	public function isValid($operation = NULL)
	{
		if ($this->_valid === NULL) {
			$this->validate($operation);
		}
		return $this->_valid;
	}

	/**
	 * Validate record
	 * @return void
	 */
	public function validate($operation = NULL)
	{
		static::validator()->validate($this, $operation ? $operation : EntityValidator::VALIDATION_INSERT);
	}

	/**
	 * Adds error message to the list.
	 * @param  string  error message
	 * @return void
	 */
	public function addError($message, $index = NULL)
	{
		if (isset($index)) {
			if (!isset($this->_errors[$index]) || !in_array($message, $this->_errors[$index], TRUE)) {
				$this->_errors[$index][] = $message;
				$this->_valid = FALSE;
			}
		} else {
			if (!isset($this->_errors['__ERRORS__']) || !in_array($message, $this->_errors['__ERRORS__'], TRUE)) {
				$this->_errors['__ERRORS__'][] = $message;
				$this->_valid = FALSE;
			}
		}
	}


	/**
	 * Returns validation errors.
	 * @return array
	 */
	public function getErrors()
	{
		$errors = $this->_errors;
		foreach($this->getAssociations() as $key => $associationObject) {
			// treba neexistuje, kdyz neni povinna vytvori se NULL
			if (!$associationObject) {
				continue;
			}

			$e = $associationObject->getErrors();
			if (count($e)) {
				$errors[$key] = $e;
			}
		}
		return $errors;
	}

	public function getErrorsList()
	{
		$errors = array();
		foreach($this->_errors as $k => $err) {
			foreach($err as $e) {
				$errors[] = $e;
			}
		}

		foreach($this->getAssociations() as $key => $associationObject) {
			// treba neexistuje, kdyz neni povinna vytvori se NULL
			if (!$associationObject) {
				continue;
			}

			$_errors = $associationObject->getErrors();
			foreach($_errors as $k => $err) {
				foreach($err as $e) {
					$errors[] = $e;
				}
			}
		}

		return $errors;
	}

	/**
	 * @return bool
	 */
	public function hasErrors()
	{
		if (count($this->_errors)) {
			return true;
		}
		foreach($this->getAssociations() as $key => $associationObject) {
			// treba neexistuje, kdyz neni povinna vytvori se NULL
			if (!$associationObject) {
				continue;
			}

			if ($associationObject->hasErrors()) return true;
		}
		return false;
	}



	/**
	 * @return void
	 */
	public function cleanErrors()
	{
		$this->_errors = array();
		$this->_valid = NULL;
	}


	/********************* interface ArrayAccess *********************/

	/**
	 * Returns property value. Do not call directly.
	 *
	 * @param  string $offset  property name
	 * @return mixed           property value
	 * @throws MemberAccessException if the property is not defined.
	 */
	final public function offsetGet($offset)
	{
		return $this->__get($offset);
	}


	/**
	 * Sets value of a property. Do not call directly.
	 *
	 * @param  string $offset  property name
	 * @param  mixed  $value   property value
	 * @return void
	 * @throws MemberAccessException if the property is not defined or is read-only
	 */
	final public function offsetSet($offset, $value)
	{
		$this->__set($offset, $value);
	}


	/**
	 * Is property defined?
	 *
	 * @param  string $offset  property name
	 * @return bool
	 */
	final public function offsetExists($offset)
	{
		return $this->__isset($offset);
	}


	/**
	 * Unset of property.
	 *
	 * @param  string $offset  property name
	 * @return void
	 * @throws MemberAccessException
	 */
	final public function offsetUnset($offset)
	{
		$this->__unset($offset);
	}

	/**
	 * Static Magic find.
	 * - $col = Page::findByUrl('about-us');
	 * - $rec = Page::findByCategoryIdAndVisibility(5, TRUE);
	 * - $rec = User::findByNameAndLogin('John', 'john007');
	 * - $rec = Product::findByCategory(3);
	 *
	 * @param string $name
	 * @param array  $args
	 * @return ActiveCollection|ActiveRecord|NULL
	 */
	public static function __callStatic($name, $args)
	{
		if (strncmp($name, 'findBy', 6) === 0) { // single record
			//$method = 'find';
			$name = substr($name, 6);

		}
		/*
		 * not implemented yet
		 * elseif (strncmp($name, 'findAllBy', 9) === 0) { // record collection
			$method = 'findAll';
			$name = substr($name, 9);

		}*/
		else {
			return parent::__callStatic($name, $args);
		}

		// ProductIdAndTitle -> array('productId', 'title')
		$parts = array_map('lcfirst', explode('And', $name));

		if (count($parts) !== count($args)) {
			throw new \Nette\InvalidArgumentException("Magic find expects " . count($parts) . " parameters, but " . count($args) . " was given.");
		}

		return self::findBy(array_combine($parts, $args));
	}



	/**
	 * Because of memory leaks :(
	 */
	public function free()
	{
		//$fh = fopen("x:\x.log", "a");fwrite($fh, __CLASS__ . "/" .__FUNCTION__ . "\n");fclose($fh);

		foreach (array_keys(get_object_vars($this)) as $k => $key) {
			// nejdrive okolni property pak asociace a data
			if (in_array($k, array("_associations", "_data"))) {
				continue;
			}

			// je to objekt? je typu IObjectContainerToFree? spustime na nej free
			if (is_object($this->$key) && $this->$key instanceof IObjectContainerToFree) {
				// nejdrive prepojime
				$object = $this->$key;
				// pak odpojime
				$this->$key = NULL;
				// a pak zlikvidujeme, jinak by se to mohlo zacyklit, record vola free kolekce a kolekce patri k rekordu takze zase zavola jeho zniceni a tak dokola
				$object->free();
			}
			else {
				$this->$key = NULL;
			}
		}

		if (count($this->_associations)) {
			foreach ($this->_associations as $v) {
				if ($v) {
					$v->free();
				}
			}
		}

		$this->_data = array();

	}


}