<?php


/**
 * @author     Roman Matěna, Roman Sklenář
 * @copyright  Copyright (c) 2009 Roman Sklenář (http://romansklenar.cz)
 */

namespace System\DbRecord;

class AssociatedTreeCollection extends AssociatedCollection
{

	protected function beforeAttach($item)
	{
		parent::beforeAttach($item);
		$item->parent = $this->belongsToRecord;

		return $item;
	}


	/**
	 * Create fluent based on association.
	 *
	 * @param string $conditions
	 * @return AssociatedTreeCollection
	 */
	public function fluent($conditions = NULL)
	{
		$class = $this->getItemType();
		$fluent = $class::fluent($conditions)
			->where("#.sx > %i", $this->getBelongsToRecord()->sx)
			->where("#.dx < %i", $this->getBelongsToRecord()->dx)
			->orderBy('#.sx');

		$this->importFluent($fluent);
		return $fluent;
	}



	/**
	 * Load all items to tree, can be limited by max level from parent.
	 */
	public function load($level = NULL)
	{

		if ($this->isLoaded()) {
			return $this;
		}
		if (!$this->isLoadable()) {
			throw new Exception('This collection is not loadable! Maybe items were set manualy before load!');
		}
		if (!$this->fluent) {
			throw new Exception('Fluent is not set');
		}
		try {

			if ($level) {
				$level += $this->belongsToRecord->lvl;
			}

			//	Vysledny level
			if (NULL !== $level) {
				$this->where('#.lvl <= %i', $level);
			}
			
			$class = $this->getItemType();
			$stack = $this->fluent->execute();

			$storage = array();
			foreach($stack as $v) {
				$values = array();
				foreach($v as $k1 => $v1) {
					$values[preg_replace("~^base__~", "", $k1)] = $v1;
				}

				$item = new $class($values, DbRecord::STATE_EXISTING);
				$storage[$item->id] = $item;
				// zpracovali jsme uz rodice? dejme mu dite
				if (isset($storage[$item->idParent])) {
					// map by IdentityMap
					if ($this->options['mapped']) {
						$item = $class::identity()->map($item);
					}

					$storage[$item->idParent]->children->append($item);
				}
				// jeste jsme na rodice nenarazili, zkusime jestli rodicem neni vlastnik kolekce
				elseif($item->idParent == $this->belongsToRecord->id) {
					// map by IdentityMap
					if ($this->options['mapped']) {
						$item = $class::identity()->map($item);
					}
					
					$this->belongsToRecord->children->append($item);
				}
			}

			$this->setLoaded(true);
			return $this;
		}
		catch (\Exception $e) {
			throw new Exception("execute query failed. " . $e->getMessage(), $e->getCode(), $e);
		}


	}

}
