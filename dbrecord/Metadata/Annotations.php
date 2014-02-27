<?php

/**
 * Base mapper class by pattern Table Data Gateway.
 *
 * @author     Roman Matěna
 * @copyright  Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */

namespace dbrecord\Metadata;

class Annotations
{

	public static function build($className)
	{
		$reflection = \Nette\Reflection\ClassType::from($className);
		$annotations = $reflection->getAnnotations();
		
		d($annotations);
		
		$ret = array();
		foreach ($annotations as $key => $value) {

			if ($key == "Entity") {
				$entity = $value[0];
				if (isset($entity->repositoryClass)) {
					$ret['repositoryClass'] = (string)$entity->repositoryClass;
				}
				elseif (isset($entity->mapperClass)) {
					$ret['mapperClass'] = (string)$entity->mapperClass;
				}
				elseif (isset($entity->validatorClass)) {
					$ret['validatorClass'] = (string)$entity->validatorClass;
				}
			}
			elseif ($key == "Table") {
				$table = $value[0];
				$ret['table'] = (string)$table->name;
			}
			elseif ($key == "Property") {
				foreach($value as $property) {
					$p = $this->buildProperty($property);
					dd($p);
				}
			}
			
		}
		
		return $ret;
	}
	
	
	protected function buildProperty($property)
	{
		dd($property);
	}

}
