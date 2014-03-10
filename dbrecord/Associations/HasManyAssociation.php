<?php

/**
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

final class HasManyAssociation extends Association
{

	/** @var string */
	public $associatedCollectionClass;





	/**
	 * Association constructor.
	 *
	 * @param string $referenceClass  referenced object name
	 * @param array $params
	 */
	public function __construct($referenceClass, $params = array())
	{
		parent::__construct(
				self::HAS_MANY, 
				$referenceClass, 
				$params['localId'], 
				$params['foreignId'], 
				isset($params['condition']) ? $params['condition'] : NULL, 
				isset($params['through']) ? $params['through'] : NULL, 
				isset($params['throughLocalId']) ? $params['throughLocalId'] : NULL, 
				isset($params['throughForeignId']) ? $params['throughForeignId'] : NULL
		);

		$this->associatedCollectionClass = isset($params['associatedCollectionClass']) ? $params['associatedCollectionClass'] : (__NAMESPACE__ . "\AssociatedCollection");
	}





	public function getAssociatedCollectionClass()
	{
		return $this->associatedCollectionClass;
	}









}
