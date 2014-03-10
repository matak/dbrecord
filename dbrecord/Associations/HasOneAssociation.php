<?php

/**
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

final class HasOneAssociation extends Association
{





	/**
	 * Association constructor.
	 *
	 * @param string $referenceClass  referenced object name
	 * @param string $localId  localId column name
	 */
	public function __construct($referenceClass, $params = array())
	{
		parent::__construct(
				self::HAS_ONE, 
				$referenceClass, 
				$params['localId'], 
				$params['foreignId'], 
				isset($params['condition']) ? $params['condition'] : NULL, 
				isset($params['through']) ? $params['through'] : NULL, 
				isset($params['throughLocalId']) ? $params['throughLocalId'] : NULL, 
				isset($params['throughForeignId']) ? $params['throughForeignId'] : NULL
		);
	}

}
