<?php


/**
 * Base mapper class by pattern Table Data Gateway.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace System\DbRecord;


class DatabaseMapper implements IDbRecordMapper
{

	/** @var string classname of System\DbRecord\DbRecord */
	protected $recordClass;

	/**
	 * Mapper's constructor.
	 * @param string $recordClass
	 */
	public function __construct($recordClass)
	{

		$this->recordClass = $recordClass;

		$behaviors = $recordClass::behaviors();

		foreach($behaviors as $event => $eventbehaviors) {
			$event = 'on' . ucfirst($event);
			foreach($eventbehaviors as $behavior) {
				$onevent = &$this->$event;
				$onevent[] = $behavior;
			}
		}

	}




	/**
	 * Insert row
	 *
	 * @throws Exception
	 * @param DbRecord $record
	 *
	 * @return System\DbRecord\DbRecord
	 */
	public function insert(DbRecord $record)
	{
		/*if (!$record->isRecordNew())
			   throw new \Nette\InvalidStateException("Only new record can be inserted.");*/

		// pro asociace ktere nejsou dirty (tedy byli vytovrene prazdne) nevyhazujeme exception, ty preskakujeme
		if (!$record->isDirty()) {
			if ($record->belongsToAssociation) {
				return false;
			}
			throw new \Nette\InvalidStateException("You cant inserting empty object!");
		}

		$engine = $this->getConnection();
//echo "<br/>insert intrans-" . $engine->inTransaction() . "-<br/>";
		if (!$engine->inTransaction()) {
//echo "<br/>begin trans<br/>";
			$doCommit = true;
			$engine->begin();
		}

		try {
			if (isset($record->belongsToCollection)) {

				// patrim do kolekce, mam relacni klic nastaven? jinak se nemuzu ulozit
				$collection = $record->belongsToCollection;
				$collectionOwner = $collection->getBelongsToRecord();
				$localId = $collection->getAssociation()->getForeignId();
				$ownerId = $collection->getAssociation()->getLocalId();

				if (!isset($record->$localId)) {
					// 1. resim, jde o novy zaznam a objekty v kolekci neznají id vlozeného majitele kolekce (localId = $collectionOwner->PK), reseni sahnou si pro neho
					// reseni sahni na majitele kolekce, jestli ani ten nema pak je to chyba
					if (!isset($collectionOwner->$ownerId)) {
						throw new \LogicException("Member of collection can not be save without known id of owner of collection!");
					}

					$record->$localId = $collectionOwner->$ownerId;
				} else {
					// 2. resim kdy ucastnik kolekce ma nastaven relacni klic, ale neni shodny s klicem majitele
					if (!isset($collectionOwner->$ownerId) || $record->$localId != $collectionOwner->$ownerId) {
						throw new \LogicException("Member of collection has different id then owner of collection! [" . $record->$localId . " / " . $collectionOwner->$ownerId . "]");
					}
				}

			}
			elseif (isset($record->belongsToAssociation)) {

				$associationOwner = $record->belongsToAssociation->getBelongsToRecord();
				$ownerId = $record->belongsToAssociation->getForeignId();
				$localId = $record->belongsToAssociation->getLocalId();
				if (isset($associationOwner->$ownerId)) {
					if (isset($record->$localId) && $record->$localId != $associationOwner->$ownerId) {
						throw new \LogicException("Owner of association has different id!");
					}
					$record->$localId = $associationOwner->$ownerId;
				}
			}
//echo "<br/>onbeforeinsert intrans-" . $engine->inTransaction() . "-<br/>";

			$this->onBeforeInsert($record);

			if (!$record->isValid(DbValidator::VALIDATION_INSERT)) {
				$errors = $record->getErrors();
				if (count($errors)) {
					$msg = current($errors);
					throw new Exception($msg[0]);
				}
				else {
					throw new Exception("Undefined validator exception!");
				}
			}

//echo "<br/>onbeforeinsertaftervalidation intrans-" . $engine->inTransaction() . "-<br/>";
			$this->onBeforeInsertAfterValidation($record);

			$values = $record->getModifiedValues(true);
			$config = $this->getConfig();

			if ($config->isPrimaryAutoincrement()) {
				$pk = $config->getPrimaryColumn();
				unset($values[$pk . "%" . $config->getType($pk)]);
			}

//echo "<br/>engineinsert intrans-" . $engine->inTransaction() . "-<br/>";
			$engine->insert($record::table(), $values)->execute(); // alternativně execute(dibi::IDENTIFIER) vrati insertedId
//echo "<br/>afterengineinsert intrans-" . $engine->inTransaction() . "-<br/>";

			// fill auto increment primary key
			if ($config->isPrimaryAutoincrement()) {
				$record->{$config->getPrimaryColumn()} = $engine->getInsertId();
			}
		}
		catch (\DibiException $e) {
			if ($engine->getDriver() instanceof \DibiMySqliDriver) {
				switch ($e->getCode()) {
					case \DibiMySqliDriver::ERROR_DUPLICATE_ENTRY:
						if (preg_match('~Duplicate entry \'([^\']*)\' for key \'([^\']*)\'~', $e->getMessage(), $matches)) {
							$msg = $record::validator()->translate('v/recordDuplicity', 1, array(
									'{__ENTRY__}' => $matches[1],
									'{__KEY__}' => $matches[2],
								));
							throw new Exception($msg, $e->getCode(), $e, $record);
						}
						else {
							throw new Exception($e->getMessage(), $e->getCode(), $e, $record);
						}
						break;

					default:
						throw new Exception($e->getMessage(), $e->getCode(), $e, $record);
						break;
				}
			}
			else {
				throw new \Nette\NotImplementedException('exceptions only for mysql.');
			}
		}


		// set state
		$record->setState($record::STATE_EXISTING);

		foreach($record->getAssociations() as $key => $associationObject) {
			// treba neexistuje, kdyz neni povinna vytvori se NULL
			if (!$associationObject) {
				continue;
			}
			$associationObject->save();
		}

		$this->onAfterInsert($record);

		// otevreli jsme si transakci jen pro tento save? nebo uz existovala a tak do ni nebudeme zasahovat?
		if (isset($doCommit)) {
			$engine->commit();
		}

		// set action / pravdepodobne probehla v poradku
		$record->lastAction('i');
		return $record;
	}



	/**
	 * Update row.
	 *
	 * @throws Exception
	 *
	 * @return System\DbRecord\DbRecord
	 */
	public function update(DbRecord $record)
	{
		if (!$record->isRecordExisting()) {
			throw new \Nette\InvalidStateException("Only existing row can be updated.");
		}

		$engine = $this->getConnection();

		if (!$engine->inTransaction()) {
			$doCommit = true;
			$engine->begin();
		}

		if ($record->isDirty()) {
			try {
				if (isset($record->belongsToCollection)) {

					//	patrim do kolekce a již jsem uložen v databázi! logicky
					//	predek uz musi met nastaveny PK ...
					$collection = $record->belongsToCollection;
					$localId = $collection->getAssociation()->getForeignId();
					$collectionOwner = $collection->getBelongsToRecord();
					$ownerId = $collection->getAssociation()->getLocalId();

					if (!isset($collectionOwner->$ownerId)) {
						throw new \LogicException("Collection owner has not primary key, associations can not be updated! $ownerId");
					}

					//	... i ja musím mít nastaven relační klíč...
					if (!isset($record->$localId)) {
						throw new \LogicException("Member of collection can not be save without known primary key of owner of collection! $localId");
					}

					//	... a musí být shodný s rodičem kolekce.
					if ($record->$localId != $collectionOwner->$ownerId) {
						throw new \LogicException("Member of collection has different id then owner of collection! [" . $record->$localId . " / " . $collectionOwner->$ownerId . "]");
					}

				}

				$this->onBeforeUpdate($record);

				if (!$record->isValid(DbValidator::VALIDATION_UPDATE)) {
					$errors = $record->getErrors();
					if (count($errors)) {
						$msg = current($errors);
						throw new Exception($msg[0]);
					}
					else {
						throw new Exception("Undefined validator exception!");
					}
				}

				$this->onBeforeUpdateAfterValidation($record);

				$values = $record->getModifiedValues(true);
				$config = $this->getConfig();

				if ($config->isPrimaryAutoincrement()) {
					$pk = $config->getPrimaryColumn();
					unset($values[$pk . "%" . $config->getType($pk)]);
				}

				if (count($values)) {
					$engine
							->update($record::table(), $values)
							->where('%and', $record->getPrimaryCondition())
									->execute();
				}
			}
			catch (\DibiException $e) {
				if ($engine->getDriver() instanceof \DibiMySqliDriver) {
					switch ($e->getCode()) {
						case \DibiMySqliDriver::ERROR_DUPLICATE_ENTRY:
							if (preg_match('~Duplicate entry \'([^\']*)\' for key \'([^\']*)\'~', $e->getMessage(), $matches)) {
								$msg = $record::validator()->translate('v/recordDuplicity', 1, array(
										'{__ENTRY__}' => $matches[1],
										'{__KEY__}' => $matches[2],
									));
								throw new Exception($msg, $e->getCode(), $e);
							}
							else {
								throw $e;
							}
							break;

						default:
							throw $e;
					}
				}
				else {
					throw new \LogicException('implementovane pouze pro mysql.');
				}
			}

			$record->clearModified();
		}

		foreach($record->getAssociations() as $key => $associationObject) {
			// treba neexistuje, kdyz neni povinna vytvori se NULL
			if (!$associationObject) {
				continue;
			}
			$associationObject->save();
		}

		$this->onAfterUpdate($record);

		if (isset($doCommit)) {
			$engine->commit();
		}

		// set action / pravdepodobne probehla v poradku
		$record->lastAction('u');
		$record->setLastAffectedRows($engine->affectedRows());

		return $record;
	}



	/**
	 * Delete row
	 * @throws Exception
	 * @return void
	 */
	public function delete(DbRecord $record)
	{
		if (!$record->isRecordExisting()) {
			throw new \Nette\InvalidStateException("Only existing row can be deleted.");
		}

		$engine = $this->getConnection();

		if (!$engine->inTransaction()) {
			$doCommit = true;
			$engine->begin();
		}

		$this->onBeforeDelete($record);

		$engine
			->delete($record::table())
			->where('%and', $record->getPrimaryCondition())
				->execute();

		$this->onAfterDelete($record);

		if (isset($doCommit)) {
			$engine->commit();
		}

		// set state
		$record->setState($record::STATE_DELETED);
		// set action / pravdepodobne probehla v poradku
		$record->lastAction('d');

	}
	
	
	/**
	 * Create DbRecordFluent instance
	 * 
	 * @param string $recordClass
	 * @return \System\DbRecord\DbRecordFluent
	 */
	public function createDbRecordFluentClass($recordClass)
	{
		return new DbRecordFluent($recordClass);
	}



	/**
	 * Get defined query (fluent) by name.
	 *
	 * @throws	\InvalidArgumentException	Demand on undefined query.
	 * @param		string $name				Predefined query by object.
	 * @return	DbRecordFluent
	 */
	public function getQuery($name = NULL)
	{
		$recordClass = $this->recordClass;
		$fluent = $this->createDbRecordFluentClass($recordClass);
		switch ($name) {

			case "emptyselect":
				$fluent
					->select(false)
					->from('#');
				break;

			case NULL:
				$fluent
					->select('#.*')
					->from('#');
#					->as('base');
#					->as($recordClass);
				break;

			default:
				throw new \Nette\InvalidArgumentException('Undefined query type '.$name.'!');
				break;

		}
		return $fluent;
	}



	public function fluent($conditions = NULL)
	{
		#preg_match('~^\[(?<select>.*)\]\s+(?<query>%\w+)\s+(?<objects>#\w+(?:\s+#\w+)*)$~Ui', $conditions, $match);
		#preg_match('~^%(?<query>\w+)~', $conditions, $match);

		if (!$conditions) {
			$fluent = $this->getQuery();

		}
		elseif (preg_match('~^%(.*)~', $conditions, $matches)) {
			$fluent = $this->getQuery($matches[1]);

		}
		else {
			$fluent = $this->getQuery('emptyselect');
			$fluent->select($conditions);

		}

		return $fluent;
	}



	/**
	 * Select pairs (primary, index) usualy for selectbox / mainindex could be set by configuration object
	 * @param  string index column
	 * @return \DibiResult
	 */
	public function fetchPairs($index = NULL)
	{
		/*
		if (count($params)) {
			if (isset($params['order'])) $order=$params['order'];
		}
		 */
		$where = $order = null;
		$record = $this->recordClass;

		if (!$index) {
			$index = $record::mainIndex();
		}

		$config = $this->getConfig();
		return $record::connection()->query(
			'SELECT %n', $config->getPrimaryColumn(), ', %n', $index,' FROM %n', $record::table(),
			'%ex', $where ? array('WHERE %and', $where) : NULL,
			'%ex', $order ? array('ORDER BY %by', $order) : array('ORDER BY %by', $index)
		)->fetchPairs($config->getPrimaryColumn(), $index);

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
	}

}
