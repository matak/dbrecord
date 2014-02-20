<?php

namespace dbrecord;

/**
 * ArrayList with lazy loading.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */
abstract class LazyCollection extends Collection
{

	/** @var bool */
	private $loaded = false;


	/** @var bool */
	protected $loadable = false;

	/**
	 * Is collection loaded? Public property getter.
	 *
	 * @return bool
	 */
	public function isLoaded()
	{
		return $this->loaded;
	}

	/**
	 * Is collection loadable? When the collection is set before load, then it cant be loaded any more.
	 *
	 * @return bool
	 */
	public function isLoadable()
	{
		return $this->loadable;
	}


	/**
	 * Public property setter.
	 * @param bool $loaded
	 */
	protected function setLoaded($loaded) {
		$this->loaded = (bool) $loaded;
	}

	/**
	 * Public property setter.
	 * @param bool $loadable
	 */
	protected function setLoadable($loadable) {
		$this->loadable = (bool) $loadable;
	}

	/**
	 * Loads items into collection.
	 * @return void
	 */
	abstract protected function load();


	/**
	 * Appends the specified element to the end of this collection.
	 * @param  mixed
	 * @return void
	 * @throws \Nette\InvalidArgumentException
	 */
	public function append($item)
	{
		// collection was not loaded before, now it is only manualy set collection
		if (!$this->isLoaded()) {
			$this->loadable = false;
		}
		return parent::append($item);
	}


	/********************* ArrayList method modifications *********************/



	/**
	 * Removes all of the elements from this collection.
	 * @return void
	 * @throws NotSupportedException
	 */
	public function clear() {
		parent::clear();
		$this->loaded = false;
	}


	/**
	 * Import from array or any traversable object.
	 * @param  array|Traversable
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function import($arr) {
		parent::import($arr);
	}

	/**
	 * Exports the ArrayObject to an array.
	 * @return array
	 */
	public function getArrayCopy() {
		if (!$this->isLoaded() && $this->isLoadable()) {
			$this->load();
		}

		return parent::getArrayCopy();
	}



	/********************* interface ArrayAccess ********************/


	/**
	 * Returns item (\ArrayAccess implementation).
	 * @param  int index
	 * @return mixed
	 * @throws \ArgumentOutOfRangeException
	 */
	public function offsetGet($index)
	{
		if (!$this->isLoaded() && $this->isLoadable()) {
			$this->load();
		}

		return parent::offsetGet($index);
	}

	/**
	 * Replaces (or appends) the item (ArrayAccess implementation).
	 * @param  int index
	 * @param  object
	 * @return void
	 * @throws InvalidArgumentException, NotSupportedException, ArgumentOutOfRangeException
	 */
	public function offsetSet($index, $item)
	{
		// collection was not loaded before, now it is only manualy set collection
		if (!$this->isLoaded()) {
			$this->loadable = false;
		}
		return parent::offsetSet($index, $item);
	}


	/**
	 * Exists item? (\ArrayAccess implementation).
	 *
	 * @param  int index
	 *
	 * @return bool
	 */
	public function offsetExists($index)
	{
		if (!$this->isLoaded() && $this->isLoadable()) {
			$this->load();
		}
		return parent::offsetExists($index);
	}


	/**
	 * Removes the element at the specified position in this list.
	 * @param  int index
	 * @return void
	 * @throws NotSupportedException, ArgumentOutOfRangeException
	 */
	public function offsetUnset($index)
	{
		if (!$this->isLoaded() && $this->isLoadable()) {
			$this->load();
		}
		return parent::offsetUnset($index);
	}






	/********************* interface Countable *********************/

	/**
	 * Get the number of public properties in the ArrayObject
	 * @return int
	 */
	public function count()
	{
		if (!$this->isLoaded() && $this->isLoadable()) {
			$this->load();
		}
		return parent::count();
	}


	/**
	 * Returns the iterator.
	 * @return ArrayIterator
	 */
	public function getIterator()
	{
		if (!$this->isLoaded() && $this->isLoadable()) {
			$this->load();
		}
		return parent::getIterator();
	}


	/**
	 * Return the first Record in the Collection.
	 *
	 * @return mixed
	 */
	public function first()
	{

		if (!$this->isLoaded() && $this->isLoadable()) {
			$this->load();
		}

		$copy = $this->getArrayCopy();
		$el = reset($copy);
		return $el === false ? NULL : $el;
	}


	/**
	 * Return the last Record in the Collection.
	 *
	 * @return mixed
	 */
	public function last() 
	{

		if (!$this->isLoaded() && $this->isLoadable()) {
			$this->load();
		}

		$copy = $this->getArrayCopy();
		$el = end($copy);
		return $el === false ? NULL : $el;
	}


	/**
	 * Return a copy of the Collection sorted in reverse.
	 *
	 * @return void  intentionally not fluent
	 */
	public function reverse() {
		$this->import(array_reverse($this->getArrayCopy()));
		return $this;
	}


	/**
	 * Removes and returns the first Record in the Collection.
	 *
	 * @return ActiveRecord
	 */
	public function shift() {
		$item = $this->first();
		$this->remove($item);
		return $item;
	}


	/**
	 * Removes and returns the last Record in the Collection.
	 *
	 * @return ActiveRecord
	 */
	public function pop() {
		$item = $this->last();
		$this->remove($item);
		return $item;
	}


	/**
	 * Append Record to the Collection.
	 *
	 * @return DbRecord
	 */
	public function push(DbRecord $item) {
		$this->append($item);
	}


}