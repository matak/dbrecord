<?php

/**
 * Base mapper class by pattern Table Data Gateway.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord\Metadata;

class EntityMetadata
{

	/** @var string */
	protected $table;


	/** @var string */
	protected $repositoryClass;


	/** @var string */
	protected $validatorClass;


	/** @var string */
	protected $mapperClass;


	/** @var array */
	protected $associations = array();


	/** @var array */
	protected $columns = array();



	public function create($metadata)
	{
		d($metadata);
	}

}
