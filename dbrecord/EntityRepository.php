<?php

/**
 * Base mapper class by pattern Table Data Gateway.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord;

class EntityRepository
{

	/** @var EntityManager */
	protected $em;


	/** @var EntityMetadata */
	protected $metadata;


	/** @var IDbRecordMapper */
	protected $mapper;





	public function __construct(EntityManager $em, EntityMetadata $metadata)
	{
		$this->em = $em;
		$this->metadata = $metadata;
	}





	/**
	 * Return all defined behaviors for record.
	 *
	 * @return array
	 */
	public static function behaviors()
	{
		return array(
			'beforeInsert' => array(
			),
			'afterInsert' => array(
			),
			'beforeUpdate' => array(
			),
			'afterUpdate' => array(
			),
			'beforeDelete' => array(
			),
			'afterDelete' => array(
			),
		);
	}





	public function getConnection()
	{
		return $this->em->getConnection();
	}





	public function getMapper()
	{
		if (!$this->mapper) {
			$mapperClass = $this->getMetadata()->getMapperClass() ? : "\dbrecord\Mappers\DatabaseMapper";
			$this->mapper = new $mapperClass;
		}

		return $this->mapper;
	}





	public function getMetadata()
	{
		return $this->metadata;
	}

}
