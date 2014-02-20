<?php


/**
 * @author     Roman Matěna
 */

namespace System\DbRecord;

class AssociatedCollection extends DbRecordCollection
{

	/** @var Association  related object */
	private $association;


	/** @var DbRecord  related object */
	public $belongsToRecord;

	/** Asociovana kolekce jde defaultne vzdy nacist, prece fluent dokazeme vygenerovat */
	protected $loadable = true;

	/**
	 * Object constructor.
	 *
	 * @param HasManyAssociation Typ asociace mozne jsou [ TODO ]
	 * @param \System\DbRecord Vlastnik, ke ktere kolekce patri.
	 */
	public function __construct(HasManyAssociation $association, DbRecord $owner)
	{
		$this->association = $association;
		$this->belongsToRecord = $owner;

		$class = $association->getReferenceClass();		
		parent::__construct($class);
	}


	/**
	 *	getter Vlastnika.
	 */
	public function getBelongsToRecord()
	{
		return $this->belongsToRecord;
	}



	/**
	 *	getter typu asociace - zpusobu pripojeni.
	 */
	public function getAssociation()
	{
		return $this->association;
	}



	/**
	 * Replaces (or appends) the item (ArrayAccess implementation).
	 *
	 * @param  int index
	 * @param  object
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException, NotSupportedException, ArgumentOutOfRangeException
	 */
	public function offsetSet($index, $item) 
	{
		$this->beforeAttach($item);
		
		parent::offsetSet($index, $item);
	}



	/**
	 *	Pred vlozenim prvku se kontroluje, zda vkládaný prvek je stejného typu, na který se odkazuje.
	 *	TODO kontrola na PK předka. Problem s poradim id.
	 */
	protected function beforeAttach($item)
	{
		$class = $this->association->getReferenceClass();
		if (!($item instanceof $class)) {
			throw new \Nette\InvalidArgumentException('Item must be \''.$class.'\' object.');
		}

		$item->setBelongsToCollection($this);

		return $item;
	}



	/**
	 * Appends the specified element to the end of this collection.
	 *
	 * @param  mixed
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function append($item)
	{
		$this->beforeAttach($item);

		return parent::append($item);
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

		$as = $this->getAssociation();
		$foreignKey = $as->getForeignId();
		$localKey = $as->getLocalId();

		// rodic kolekce, jeho primary key a value, fluent nelze vytvorit pokud jde o novy prvek! pak by se to melo chovat jako prazdna kolekce
		$parent = $this->getBelongsToRecord();

		if ($parent->getState() === $parent::STATE_NEW) {
			throw new FluentOnNonExistingParentOfAssociationException;
			
		}

		if (!$parent->$localKey) {
			throw new \LogicException("pk ($localKey) of owner not found");
		}
		/* dibi types
		const TEXT = 's', // as 'string'
		BINARY = 'bin',
		BOOL = 'b',
		INTEGER = 'i',
		FLOAT = 'f',
		DATE = 'd',
		DATETIME = 't',
		TIME = 't';
		 * 
		 */		

		// zjisti parentuv primary type jestli je to %i nebo %s
		$parentConfig = $parent::config();
		$primaryColumnName = $parentConfig->getPrimaryColumn();
		$primaryColumnType = $parentConfig->getType($primaryColumnName);
		
		$parentType = "%s";
		if ($primaryColumnType == "i") {
			$parentType = "%i";
		}
		
		if ($as->getThrough()) {

			$on = '#.' . $as->getForeignId() . ' = through.' . $as->getThroughForeignId() . ' AND through.' . $as->getThroughLocalId() . ' = ' . $parentType;

			$fluent->join($as->getThrough())
				->as('through')
				->on($on, $parent->$localKey);

		}
		else {

			$fluent->where("#.$foreignKey = " . $parentType, $parent->$localKey);

		}

		$this->importFluent($fluent);

		return $fluent;
	}


	public function load()
	{
		if (!$this->fluent) {
			try {
				// pokud neni fluent set, nastav defaultni
				$this->fluent();
			}
			// nelze vytvorit fluent, pravdepodobne parent neni zpusobily nest kolekci, napr. nema primary key, je novy atd.
			catch (FluentOnNonExistingParentOfAssociationException $e) {
				$this->setLoadable(false);
				return $this;
			}
		}

		return parent::load();
	}


	/**
	 * Returns array of values with associations.
	 * @return array
	 */
	public function toAssociatedArray($associations = array(), $params = array())
	{
		$data = array();
		foreach($this->getIterator() as $key => $object) {
			$data[$key] = $object->toAssociatedArray($associations, $params);
		}
		return $data;
	}




}
