<?php

namespace dbrecord\Test\Entities;

use dbrecord\EntityRepository;

/**
 * ProductRepository
 *
 * @author Roman Matěna
 * @copyright Copyright (c) 2010-2014 Roman Matěna (http://www.romanmatena.cz)
 *
 */
class ProductRepository extends EntityRepository
{





	public function save(Entity $record)
	{
		if ($record->isModified('visibility') && $record->isModified('salesPrice') && $record->isModified('datePublished') && $record->isModified('dateFinished')) {
			if ($record->visibility == 1 && $record->salesPrice > 0 && strtotime($record->datePublished) < mktime() && (strtotime($record->dateFinished) > mktime() || !$record->dateFinished)) {
				$record->visibilityFront = 1;
			}
			else {
				$record->visibilityFront = 0;
			}
		}

		return parent::save($record);
	}

}
