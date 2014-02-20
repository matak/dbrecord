<?php

/**
 * DbRecordCollection collects objects
 *
 * @author Roman Matěna
 */

namespace System\DbRecord;

class DbRecordCollection extends LazyCollection implements IObjectContainerToFree
{

	/** @var DbRecordFluent */
	protected $fluent;


	/** should be collected records mapped? */
	protected $options = array(
			'mapped' => false,
		);


	/**
	 * Constructor
	 *
	 * @param string $recordClass
	 */
	public function __construct($itemType)
	{
		parent::__construct(NULL, $itemType);
	}



	/**
	 * Set options of collection by array. Use fluent interface.
	 *
	 * Defined options:
	 * mapped: should be loaded objects mapped by IdentityMap?
	 *
	 * @param array $options
	 * @return DbRecordCollection
	 */
	public function options(array $options)
	{
		$this->options = array_merge($this->options, $options);
		return $this;
	}

	/**
	 * Set fluent for collection.
	 *
	 * @param DbRecordFluent $fluent
	 */
	public function importFluent(DbRecordFluent $fluent)
	{
		$this->fluent = $fluent;
		$this->setLoadable(true);
		$this->clear();

		return $this;
	}



	/**
	 * Load all items defined by fluent.
	 *
	 * 
	 * @return DbRecordCollection
	 */
	public function load()
	{
		if ($this->isLoaded()) {
			return $this;
		}
		
		if (!$this->isLoadable()) {
			throw new Exception('This collection is not loadable! Maybe items were set manualy before load!');
		}
		
		if (!$this->fluent) {
			// pokud neni fluent set, nastav defaultni
			$this->fluent();
			#throw new Exception('Fluent is not set');
		}
		try {
			$class = $this->getItemType();
			foreach ($this->fluent->toArray() as $item) {
				$item = new $class($item);
				$item->setState(DbRecord::STATE_EXISTING);

				// map by IdentityMap
				if ($this->options['mapped']) {
					$item = $class::identity()->map($item);
				}

				$this->append($item);
			}
			$this->setLoaded(true);
			return $this;
		}
		catch (\Exception $e) {
			throw new Exception("execute query failed. " . $e->getMessage(), $e->getCode(), $e);
		}
	}



	/**
	 * Save every DbRecord in the Collection.
	 *
	 * @return DbRecordCollection
	 */
	public function save()
	{
		foreach ($this as &$item) {
			$item->save();
		}
		
		return $this;
	}



	/**
	 * Get DibiDataSource object.
	 *
	 * @return DibiDataSource
	 */
	public function toDataSource() 
	{
		if (!$this->fluent) {
			throw new Exception('Fluent is not set');
		}
		return $this->fluent->toDataSource();
	}



	/**
	 * Get Fluent object.
	 *
	 * @return DbRecordFluent
	 */
	public function getFluent() 
	{
		if (!$this->fluent) {
			throw new Exception('Fluent is not set');
		}
		return $this->fluent;
	}



	/**
	 * Freeze collection
	 */
	public function freeze()
	{
		foreach ($this as &$item) {
			$item->freeze();
		}
		
		parent::freeze();
	}


	/**
	 * Return if is object in collection with error.
	 *
	 * @return bool
	 */
	public function hasErrors()
	{
		foreach ($this as &$object) {
			if ($object->hasErrors()) {
				return true;
			}
		}
		return false;
	}



	/**
	 * Returns collection of errors from all objects.
	 * @return array
	 */
	public function getErrors()
	{
		$errors = array();
		foreach ($this as $key => &$object) {
			$e = $object->getErrors();
			if (count($e)) {
				$errors[$key] = $e;
			}
		}
		return $errors;
	}

	/**
	 * Apply call function on fluent if it is fluent function.
	 *
	 * 
	 */
	public function __call($clause, $args)
	{
		if (strncmp($clause, 'findBy', 6) === 0) { // single record
			$name = substr($clause, 6);
			// ProductIdAndTitle -> array('productId', 'title')
			$parts = array_map('lcfirst', explode('And', $name));

			if (count($parts) !== count($args)) {
				throw new \Nette\InvalidArgumentException("Magic find expects " . count($parts) . " parameters, but " . count($args) . " was given.");
			}

			return $this->findBy(array_combine($parts, $args));
		}


		switch($clause) {
			case 'rselect':
			case 'rwhere':
			case 'select':
			case 'from':
			case 'join':
			case 'leftJoin':
			case 'rightJoin':
			case 'as':
			case 'on':
			case 'where':
			case 'groupBy':
			case 'orderBy':

				if ($this->isLoaded()) {
					throw new \LogicException('Collection has been loaded before!');
				}

				if (!$this->fluent) {
					$this->fluent();
				}

				return call_user_func_array(array($this->fluent, $clause), $args);
				break;

			default:
				return parent::__call($clause, $args);
				break;
		}
	}

	/**
	 * Create new fluent and replace it to the collection.
	 *
	 * @param string $conditions
	 * @return AssociatedCollection
	 */
	public function fluent($conditions = NULL)
	{
		$class = $this->getItemType();
		$fluent = $class::fluent($conditions);
		$this->importFluent($fluent);

		return $fluent;

	}

	
	/**
	 * 
	 * @param type $pk
	 * @return item|null
	 */
	public function pk($pk)
	{
		$item = $this->getItemType();

		$pkname = $item::config()->getPrimaryColumn();
		foreach($this as $item) {
			if ($item->$pkname == $pk) {
				return $item;
			}
		}
		return NULL;
	}

	public function findBy($conditions)
	{
		foreach($this as $item) {
			$skip = false;
			foreach($conditions as $k => $v) {
				if ($item->$k != $v) {
					$skip = true;
					break;
				}
			}
			if ($skip) {
				continue;
			}
			return $item;
		}
		return NULL;
	}


	/**
	 * Because of memory leaks :(
	 */
	public function free()
	{
		//$fh = fopen("x:\x.log", "a");fwrite($fh, __CLASS__ . "/" .__FUNCTION__ . "\n");fclose($fh);
		
		foreach (array_keys(get_object_vars($this)) as $key) {
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

		// nejdrive zlikvidujeme property jinak se to zacykli
		if ($this->isLoaded()) {
			foreach($this->getIterator() as $object) {
				$object->free();
			}
		}
		
		
	}	


	public function getConnection()
	{
		return $this->getFluent()->getConnection();
	}
}
