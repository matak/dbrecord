<?php


namespace dbrecord;

/**
 * EntityCollection collects objects
 *
 * @author Roman Matěna
 */
class EntityCollection extends LazyCollection implements IObjectContainerToFree
{

	/** @var Query */
	protected $query;


	/** @var EntityRepository */
	protected $repository;
	
	/** should be collected records mapped? */
	protected $options = array(
			'mapped' => false,
		);


	/**
	 * Constructor
	 *
	 * @param string $recordClass
	 */
	public function __construct(EntityRepository $repository)
	{
		$this->repository = $repository;
		parent::__construct(NULL, $repository->getMetadata()->getEntityClass());
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
	 * Create new query based on repository.
	 *
	 * @param string $conditions
	 * @return EntityCollection
	 */
	public function query($conditions = NULL)
	{
		$query = $this->repository->query($conditions);
		// po vytvoreni noveho query je nutne kolekci vyprazdnit, prvky v kolekci obsazene by neodpovidali novemu query
		$this->clear();
		// je mozne opet loadovat
		$this->setLoadable(true);

		return $query;
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
		
		if (!$this->query) {
			// pokud neni fluent set, nastav defaultni
			$this->query();
			#throw new Exception('Fluent is not set');
		}

		foreach ($this->query->toArray() as $values) {
			$this->repository->createObjectFromValues($values);

			// map by IdentityMap
			if ($this->options['mapped']) {
				$item = $class::identity()->map($item);
			}

			$this->append($item);
		}
		$this->setLoaded(true);
		
		return $this;
	}



	/**
	 * Save every DbRecord in the Collection.
	 *
	 * @return DbRecordCollection
	 */
	public function save()
	{
		foreach ($this as &$item) {
			$this->repository->save($item);
		}
		
		return $this;
	}






	/**
	 * Get query object.
	 *
	 * @return DbRecordFluent
	 */
	public function getQuery() 
	{
		if (!$this->query) {
			throw new Exception('Query is not set!');
		}
		
		return $this->query;
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
		foreach ($this as &$item) {
			if ($item->hasErrors()) {
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
		foreach ($this as $key => &$item) {
			$e = $item->getErrors();
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

				if (!$this->query) {
					$this->query();
				}

				return call_user_func_array(array($this->query, $clause), $args);
				break;

			default:
				return parent::__call($clause, $args);
				break;
		}
	}



	
	/**
	 * 
	 * @param type $pk
	 * @return item|null
	 */
	public function pk($pk)
	{
		$pkName = $this->repository->getMetadata()->getPrimaryColumn();
		foreach($this as $item) {
			if ($item->$pkName == $pk) {
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


}
