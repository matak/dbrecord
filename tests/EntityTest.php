<?php

namespace dbrecord\Test;

require __DIR__ . "/Entities/Product.php";
require __DIR__ . "/Entities/ProductRepository.php";
require __DIR__ . "/Entities/ProductValidator.php";

/**
 * Entity test
 *
 * @author Roman Matena
 */
class EntityTest extends \PHPUnit_Framework_TestCase
{

	/** @var Entity */
	private $entity;

	protected function setUp()
	{
		$this->entity = new Entities\Product;
	}

	public function testSetProperties()
	{
		//$entity = $this->entity;
		//$entity->AS = 1;
		//$this->assertEquals(1, $entity->AS);
	}

}
