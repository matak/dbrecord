<?php


/**
 * Base mapper class by pattern Table Data Gateway.
 *
 * @author     Roman Matěna, Martin Takáč
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

/**
 *	
 *	old - vypocitat umisteni, sx, dx
 *	vytorit misto
 *	ulozit
 *	uzavrit misto (vyjma sebe)
 *
 *	scenare
 *		A - posunuti dolu v ramci nodu (ord)
 *		B - posunuti nahoru v ramci nodu (ord)
 *		C - posunuti na zacatek v ramci nodu (ord)
 *		  - posunuti na konec v ramci nodu ([ord]): neexistuje.
 *
 *		E - presunuti/vlozeni do prazdneho nodu (idParent)
 *
 *		F - presunuti/vlozeni do neprazdneho nodu na zacatek (idParent, ord)
 *		G - presunuti/vlozeni do neprazdneho nodu na konec (idParent)
 *		H - presunuti/vlozeni do neprazdneho nodu nekam doprostred (idParent, ord)
 *		I - presunuti/vlozeni do neprazdneho nodu na konec definovany (idParent, ord)
 *
 *
 *	@author     Roman Matěna, Martin Takáč
 *	@copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */
class DbTreeMapper extends DbMapper
{

	/**
	 * Get root record, basic peak of tree (pyramide).
	 *
	 * @param int $idTopic
	 * @return System\DbRecord\DbTreeRecord
	 */
	public function getRoot($idTopic)
	{

		$engine = $this->getConnection();

		$recordClass = $this->getRecordClass();

		$topicIndex = $recordClass::topicIndex();
		$mainIndex = $recordClass::mainIndex();

		$values = $engine->select('*')
				->from($recordClass::table())
				->where($topicIndex . "=%i", $idTopic, " AND idParent IS NULL AND " . $mainIndex . " = '__root__'")
				->fetch();

		if (!$values) {
			return $this->insertRoot($idTopic);
		}

		$root = new $recordClass($values);

		$root->setState($root::STATE_EXISTING);

		return $root;

	}



	/**
	 * Insert root record, basic peak of tree (pyramide).
	 *
	 * @param int $idTopic
	 * @return System\DbRecord\DbTreeRecord
	 */
	private function insertRoot($idTopic)
	{
		$engine = $this->getConnection();

		$recordClass = $this->getRecordClass();

		$topicIndex = $recordClass::topicIndex();
		$mainIndex = $recordClass::mainIndex();

		$root = new $recordClass(array(
			$topicIndex => $idTopic,
			'idParent' => NULL,
			$mainIndex => "__root__",
			'ord' => 0,
			'lvl' => 0,
			'sx' => 1,
			'dx' => 2,
		));

		$values = $root->getModifiedValues(true);

		$engine->insert($recordClass::table(), $values)->execute();
		
		$root->{$this->getConfig()->getPrimaryColumn()} = $engine->getInsertId();

		$root->setState($root::STATE_EXISTING);

		return $root;
	}



	/**
	 * Insert row
	 *
	 * @throws Exception
	 * @param System\DbRecord\DbTreeRecord $record
	 *
	 * @return System\DbRecord\DbTreeRecord
	 */
	public function insert(DbRecord $record)
	{

		if (!$record->isDirty()) {
			throw new \Nette\InvalidStateException("You cant inserting empty object!");
		}

		//	Validace
		if (!$record instanceof DbTreeRecord) {
			throw new \Nette\InvalidArgumentException('Invalid record type, waiting DbTreeRecord!');
		}


		try {
			if (isset($record->belongsToCollection)) {
				// patrim do kolekce, mam relacni klic nastaven? jinak se nemuzu ulozit
				$collection = $record->belongsToCollection;
				$collectionOwner = $collection->getBelongsToRecord();
				$relationId = $collection->getAssociation()->getRelationId();
				$pk = $collectionOwner::config()->getPrimaryColumn();
				if (!isset($record->$relationId)) {
					// 1. resim, jde o novy zaznam a objekty v kolekci neznají id vlozeného majitele kolekce (relationId = $collectionOwner->PK), reseni sahnou si pro neho
					// reseni sahni na majitele kolekce, jestli ani ten nema pak je to chyba
					if (!isset($collectionOwner->$pk)) {
						throw new \LogicException("Member of collection can not be save without known id of owner of collection!");
					}
					$record->$relationId = $collectionOwner->$pk;
				} else {
					// 2. resim kdy ucastnik kolekce ma nastaven relacni klic, ale neni shodny s klicem majitele
					if (!isset($collectionOwner->$pk) || $record->$relationId != $collectionOwner->$pk) {
						throw new \LogicException("Member of collection has different id then owner of collection!");
					}
				}

			}

			$this->onBeforeInsert($record);

			//	Validace recordu.
			if (!$record->isValid(EntityValidator::VALIDATION_INSERT)) {
				$errors = $record->getErrors();
				if (count($errors)) {
					$msg = current($errors);
					throw new Exception($msg[0]);
				} 
				else {
					throw new Exception("Undefined validator exception!");
				}
			}

			$this->onBeforeInsertAfterValidation($record);

			$config = $this->getConfig();
			$table = $record::table();
			$values = $record->getModifiedValues(true);

			if ($config->isPrimaryAutoincrement()) {
				$pk = $config->getPrimaryColumn();
				unset($values[$pk . "%" . $config->getType($pk)]);
			}

			$engine = $this->getConnection();
			
			if (!$engine->inTransaction()) {
				$doCommit = true;
				$engine->begin();
			}

			$topicIndex = $record::topicIndex();

			$parent = $record->parent;
			if ($parent === NULL) {
				throw new \LogicException("Parent must be set!");
			}

			//	Zmena poradi ---------------------------------------------------
			//	Poradi neuvedeno, tedy na konec.
			if (!isset($values['ord%i'])) {
				$record->sx = $values['sx%i'] = $parent->dx;
			}
			//	Poradi uvedeno, tedy:
			else {
				$ord = $values['ord%i'];

				//	Nastavit jako prvni.
				if (0 === $ord) {
					//	Jednoduzsi, nastavime na prvni.
					$record->sx = $values['sx%i'] = $parent->sx + 1;
				}
				//	Na misto nejakeho existujiciho prvku, nebo na konec. Mimo rozsah se netoleruje.
				else {
					$record->sx = $values['sx%i'] = $engine->select('sx')
						->from($table)
						->where('idParent = %i', $parent->id)
						->and('ord = %i', $ord)
						->fetchSingle();

					//	Na konec, nebo mimo rozsah.
					if (false === $values['sx%i']) {
						//	Overit mimo rozsah. Pokud je to posledni, tak je to ok.
						$tmpOrd = $engine->select('COUNT(*)')
							->from($table)
							->where('idParent = %i', $parent->id)
							->fetchSingle();
						if ($ord == ($tmpOrd)) {
							$record->ord = $ord = $tmpOrd;
							$record->sx = $values['sx%i'] = $parent->dx;
						}
						else {
							throw new \RangeException("Out of range: $ord/$tmpOrd.");
						}
					}
				}
			}

			//	Upravit hodnoty ukladaneho.
			$record->dx = $values['dx%i'] = $values['sx%i'] + 1;
			$record->$topicIndex = $values[$topicIndex . '%i'] = $parent->$topicIndex;
			$record->lvl = $values['lvl%i'] = $parent->lvl + 1;

			//	Vytvoreni mista ------------------------------------------------
			$this->openSpace($engine, $table, $topicIndex, $record);

			//	Upravit hodnoty ukladaneho.
			if (isset($ord)) {
				$record->ord = $ord;
			}
			else {
				//	Pokud obsahuje vic jak jednoho potomka, nevime, zda ten nema dalsi, nebo to jsou sourozenci.
				if (($parent->sx + 2) == $parent->dx) {
					$record->ord = 0;
				}
				else {
					$record->ord = $values['ord%i'] = $engine->select('COUNT(*)')
						->from($table)
						->where('idParent = %i', $parent->id)
						->fetchSingle();
				}
			}

			//	Ulozeni vlastniho zaznamu.
			$engine->insert($table, $values)
					->execute();

			// fill auto increment primary key // musi byt autoincrement - uz jen kvuli insertroot
			$record->{$config->getPrimaryColumn()} = $engine->getInsertId();

			if (isset($doCommit)) {
				$engine->commit();
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
						} else {
							throw $e;
						}
					default:
						throw $e;
				}
			} 
			else {
				throw new LogicException('implementovane pouze pro mysql.');
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

		return $record;
	}



	/**
	 * Rebuild all indexes sx, dx, lvl by parent in whole table.
	 *
	 */
	public function rebuildAll()
	{
		$engine = $this->getConnection();

		$recordClass = $this->getRecordClass();
		$topicIndex = $recordClass::topicIndex();

		$ids = $engine->select($topicIndex)
				->from($recordClass::table())
				->groupBy($topicIndex)
				->fetchAssoc('[]='.$topicIndex);

		foreach($ids as $idTopic) {
			$this->rebuildTopic($idTopic);
		}
	}


	
	/**
	 * Rebuild all indexes sx, dx, lvl by parent in specified topic.
	 *
	 */
	public function rebuildTopic($idTopic)
	{
		$engine = $this->getConnection();
		try {
			if (!$engine->inTransaction()) {
				$doCommit = true;
				$engine->begin();
			}


			$root = $this->getRoot($idTopic);
			$this->rebuildTree($idTopic, $root->id, 1, 0, 0);

			if (isset($doCommit)) {
				$engine->commit();
			}

		}
		catch (Exception $e) {
			if (isset($doCommit)) {
				$engine->rollback();
			}
			throw $e;
		}
	}



	/**
	 *	Rebuild all indexes sx, dx, lvl by parent in specified topic.
	 *
	 */
	private function rebuildTree($idTopic, $id, $sx, $ord, $lvl)
	{
		$dx = $sx + 1; // the right value of this node is the left value + 1
		$ord2 = 0;
		$engine = $this->getConnection();
		$record = $this->getRecordClass();

		//	Vsechny prvky patrici primo parentu.
		foreach ($engine->select($record::topicIndex())->as('topic')
				->select('id')
				->from($record::table())
				->where('idParent = %i', $id)
				->orderBy('ord ASC') as $row) {
			//	Rovnou se posune dx
			$dx = $this->rebuildTree($idTopic, $row->id, $dx, $ord2, $lvl + 1);
			$ord2 ++;
		}

		//	Aktualizovat sebe
		$engine->update(
				$record::table(),
				array(
						$record::topicIndex() => $idTopic,
						'sx' => $sx,
						'dx' => $dx,
						'lvl' => $lvl,
						'ord' => $ord
					)
			)
			->where('id = %i', $id)
			->execute();

		return $dx + 1;
	}



	/**
	 *	Vytvoreni mista, kam se bude vkladat prvek.
	 *	Posune cely blok o neco dolu a tento rozsah vrati.
	 *
	 *	@return int O kolik je to posunute.
	 */
	private function openSpace($engine, $table, $topicIndex, $new, $hack = false)
	{
		if (!isset($new->dx)) {
			throw new \Nette\InvalidArgumentException('dx');
		}
		if (!isset($new->$topicIndex)) {
			throw new \Nette\InvalidArgumentException($topicIndex);
		}

		$range = ($new->dx - $new->sx) + 1;

		// update left
		$engine->update($table, array('sx%sql' => "sx + $range"))
			->where("sx >= %i", $new->sx)
			->and($topicIndex . " = %i", $new->$topicIndex)
			->execute();

		// update right
		$engine->update($table, array('dx%sql' => "dx + $range"))
			->where("dx >= %i", $new->sx)
			->and($topicIndex . " = %i", $new->$topicIndex)
			->execute();

		//	update ord, posunout ostatnim sourozence
		if (!$hack && isset($new->ord)) {
			$engine->update($table, array('ord%sql' => "ord + 1"))
				->where('idParent = %i', $new->idParent)
				->and('ord >= %i', $new->ord)
				->execute();
		}

		return $range;
	}



	/**
	 *	Zaceleni mista, kam se vkladal prvek.
	 *	Posune cely blok o neco nahoru.
	 *
	 *	@return int O kolik je to posunute.
	 */
	private function closeSpace($engine, $table, $topicIndex, $sx, $dx, $topicIndexValue)
	{
		$range = ($dx - $sx) + 1;

		// update left
		$engine->update($table, array('sx%sql' => "sx - $range"))
			->where("sx >= %i", $sx)
			->and($topicIndex . " = %i", $topicIndexValue)
			->execute();

		// update right
		$engine->update($table, array('dx%sql' => "dx - $range"))
			->where('dx >= %i', $dx)
			->and("$topicIndex = %i", $topicIndexValue)
			->execute();
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
		
		if ($record->isDirty()) {
			try {
				if (isset($record->belongsToCollection)) {
					// patrim do kolekce a již jsem uložen v databázi! logicky musím mít nastaven relační klíč a musí být shodný s rodičem kolekce
					$collection = $record->belongsToCollection;
					$collectionOwner = $collection->getBelongsToRecord();
					$relationId = $collection->getAssociation()->getRelationId();
					$pk = $collectionOwner::config()->getPrimaryColumn();

					if (!isset($record->$relationId)) {
						throw new \LogicException("Member of collection can not be save without known id of owner of collection!");
					} else {
						if (!isset($collectionOwner->$pk) || $record->$relationId != $collectionOwner->$pk) {
							throw new \LogicException("Member of collection has different id then owner of collection!");
						}
					}

				}

				$this->onBeforeUpdate($record);

				if (!$record->isValid(EntityValidator::VALIDATION_UPDATE)) {
					$errors = $record->getErrors();
					if (count($errors)) {
						$msg = current($errors);
						throw new Exception($msg[0]);
					} else {
						throw new Exception("Undefined validator exception!");
					}
				}

				$this->onBeforeUpdateAfterValidation($record);

				$values = $record->getModifiedValues(true);
				$config = $this->getConfig();
				$topicIndex = $record::topicIndex();

				if ($config->isPrimaryAutoincrement()) {
					$pk = $config->getPrimaryColumn();
					unset($values[$pk . "%" . $config->getType($pk)]);
				}

				if (!$engine->inTransaction()) {
					$doCommit = true;
					$engine->begin();
				}

				$table = $record::table();


				//	Zmana rodice -----------------------------------------------
				if (isset($values['idParent%i'])
				&& !isset($values['ord%i'])) {
					$values['ord%i'] = $engine->select('COUNT(*)')
						->from($table)
						->where('idParent = %i', $values['idParent%i'])
						->fetchSingle();
				}

				//	Zmena poradi -----------------------------------------------
				if (isset($values['ord%i'])) {
					$ord = $values['ord%i'];

					//	Stare umisteni
					if (!isset($old)) {
						$old = $engine->select('sx, dx, lvl, ord, idParent, ' . $topicIndex)
							->select('id, name')
							->from($table)
							->where('id = %i', $record->id)
							->fetch();
					}

					//	Bug, $record->parent v pripade neexistujucihi parenta mel yvhodit vyjimku, ne nacist nesmysl.
					if (empty($record->idParent)) {
						$record->idParent = $old->idParent;
					}

					if (!isset($parent)) {
						$parent = $record->parent;
					}

					if ($ord == $old->ord
					&& $parent->id == $old->idParent) {
						unset($values['ord%i']);
						unset($ord);
					}
					else {
						//	Nastavit jako prvni.
						if (0 === $ord) {
							//	Jednoduzsi, nastavime na prvni.
							$record->sx = $values['sx%i'] = $parent->sx + 1;
						}
						//	Na misto nejakeho existujiciho prveku, nebo na konec. Mimo 
						//	rozsah se netoleruje. Pokud se posunuje dolu, tak je to za
						//	existujici prvek, pokud je to posun nahoru, tak je to pred
						//	existujici prvek.
						//	Spatne. Vzdy je to pred prvke s danym indexem. Ale muze se 
						//	stat, ze se trefim primo na index za poslednim.
						else {
							$tmp = $engine->select('sx, dx, idParent')
								->from($table)
								->where('idParent = %i', $parent->id)
								->and('ord = %i', $ord)
								->fetch();

							//	Na konec, nebo mimo rozsah.
							if (false === $tmp) {
								//	Overit mimo rozsah. Pokud je to posledni, tak je to ok.
								$tmpOrd = $engine->select('COUNT(*)')
									->from($table)
									->where('idParent = %i', $parent->id)
									->fetchSingle();
								if ($ord == ($tmpOrd)) {
									$record->ord = $ord = $tmpOrd;
									$tmp = (object) array (
											'sx' => $parent->dx,
											'dx' => $parent->dx,
											'idParent' => $parent->id
										);
								}
								else {
									throw new \RangeException("Out of range: $ord/$tmpOrd.");
								}
							}

							//	Dolu
							if (($tmp->dx > $old->dx)
							&&	($tmp->idParent == $old->idParent)) {
								$record->sx = $values['sx%i'] = $tmp->dx + 1;
							}
							//	Nahoru
							else {
								$record->sx = $values['sx%i'] = $tmp->sx;
							}
						}
						$record->dx = $values['dx%i'] = $record->sx + ($old->dx - $old->sx);
					}
				}


				//	Vytvorit misto ---------------------------------------------
				if (isset($ord)) {
					$record->$topicIndex = $parent->$topicIndex; // ???
					$range = $this->openSpace($engine, $table, $topicIndex, $record, true);

					//	Posunout zaznam vcetne potomku -------------------------
					//	Posunuli jsme sebe
					$selfshift = 0;
					if (($old->sx >= $record->sx)
					&& ($old->$topicIndex == $record->$topicIndex)) {
						$selfshift = $range;
					}

					// update left
					$engine->update(
							$table, 
							array(
									'sx%sql' => "(sx - " . ($old->sx + $selfshift) . ") + {$record->sx}",
									$topicIndex . '%i' => $parent->$topicIndex
								)
						)
						->where("sx >= %i", $old->sx + $selfshift)
						->and("sx < %i", ($old->dx + $selfshift))
						->and($topicIndex . " = %i", $old->$topicIndex)
						->execute();
					// update right
					$engine->update(
							$table, 
							array(
									'dx%sql' => "(dx - " . ($old->dx + $selfshift) . ") + {$record->dx}",
									$topicIndex . '%i' => $parent->$topicIndex
								)
						)
						->where("dx <= %i", $old->dx + $selfshift)
						->and("dx > %i", $old->sx + $selfshift)
						->and('%sql', "($topicIndex = {$old->$topicIndex} OR $topicIndex = {$record->$topicIndex})")
						->execute();
				}

				//	Ulozit zaznam ----------------------------------------------
				if (count($values)) {
					$engine
						->update($table, $values)
						->where('%and', $record->getPrimaryCondition())
						->execute();
				}

				//	Zacelit diru -----------------------------------------------
				if (isset($ord)) {
					$this->closeSpace(
							$engine, 
							$table, 
							$topicIndex, 
							$old->sx + $selfshift, 
							$old->dx + $selfshift, 
							$old->$topicIndex
						);

					if ($record->sx >= $old->sx) {
						$record->sx -= $range;
						$record->dx -= $range;
					}
				}
				if (isset($parent)) {
					$record->lvl = $parent->lvl + 1;
				}

				if (isset($old)) {
					//	Opravit ord --------------------------------------------
					//	Sourozence, ktere jsem opustil.
					$engine->update($table, array('ord%sql' => 'ord - 1'))
						->where('idParent = %i', $old->idParent)
						->and('id != %i', $record->id)
						->and('ord >= %i', $old->ord)
						->execute();
				
					//	Sourozence, kam jsem se vetrel. To by mela zajistit openSpace().
					$engine->update($table, array('ord%sql' => "ord + 1"))
						->where('idParent = %i', $record->idParent)
						->and('id != %i', $record->id)
						->and('ord >= %i', $record->ord)
						->execute();

					//	Opravit lvl	svych vetvy. -------------------------------
					if ($record->idParent != $old->idParent) {
						$engine->update($table, array('lvl%sql' => "lvl + ({$record->lvl} - {$old->lvl})"))
							->where('sx >= %i', $record->sx)
							->and('dx <= %i', $record->dx)
							->and($topicIndex . " = %i", $record->$topicIndex)
							->execute();
					}
				}
				
				//	Naplnit record ---------------------------------------------
#				if (isset($old)) {
#				
#				}

				//	Ukoncit transakci ------------------------------------------
				if (isset($doCommit)) {
					$engine->commit();
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

			//	Zmeny uplatneny.
			$record->clearModified();
		}

		//	Vse se to tyka take asociaci. -- toto dohledat.
		foreach($record->getAssociations() as $key => $associationObject) {
			// treba neexistuje, kdyz neni povinna vytvori se NULL
			if (!$associationObject) {
				continue;
			}

			$associationObject->save();
		}

		return $engine->affectedRows();
	}



	/**
	 * Insert or update row
	 * @throws Exception
	 * @return System\DbRecord\DbRecord
	 */
	public function delete(DbRecord $record)
	{
		if (!$record->isRecordExisting()) {
			throw new \Nette\InvalidStateException("Only existing row can be deleted.");
		}

		$engine = $this->getConnection();
		$table = $record::table();
		$topicIndex = $record::topicIndex();

		if (!$engine->inTransaction()) {
			$doCommit = true;
			$engine->begin();
		}

		$res = $engine
			->select('sx, dx, ' . $topicIndex . ' AS topic')
			->from($record::table())
			->where('%and', $record->getPrimaryCondition())
			->fetchAll();

		//	Zadny zaznam?
		if (!isset($res[0])) {
			throw new NotFoundException('Item pk(' . implode(', ', $record->getPrimaryCondition()) . ') not found.', 1);
		}
		$res = $res[0];
		
		//	Odstraneni zaznamu.
		$ret = $engine
			->delete($table)
			->where('%and', $record->getPrimaryCondition())
			->execute();

		//	Zadny zaznam?
		if (!$ret) {
			throw new NotFoundException('Item pk(' . implode(', ', $record->getPrimaryCondition()) . ') not found: (' . $ret . ')', 2);
		}

		// update left
		$engine->update($table, array('sx%sql' => "sx - " . (($res->dx - $res->sx) + 1)))
			->where("sx >= %i", $res->sx)
			->and($topicIndex . "=%i", $res->topic)
			->execute();

		// update right
		$engine->update($table, array('dx%sql' => "dx - " . (($res->dx - $res->sx) + 1)))
			->where("dx >= %i", $res->dx)
			->and($topicIndex . "=%i", $res->topic)
			->execute();

		//	Upravit poradi. ord.

		if (isset($doCommit)) {
			$engine->commit();
		}

		// set state
		$record->setState($record::STATE_DELETED);

		return $ret;
	}




}
