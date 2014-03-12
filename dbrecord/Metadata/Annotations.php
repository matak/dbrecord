<?php

/**
 * Annotations parser library
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

		$ret = array('entityClass' => $className);
		foreach ($annotations as $key => $value) {

			if ($key == "Entity") {
				$entity = $value[0];
				if (isset($entity->repositoryClass)) {
					$ret['repositoryClass'] = (string) $entity->repositoryClass;
				}
				elseif (isset($entity->validatorClass)) {
					$ret['validatorClass'] = (string) $entity->validatorClass;
				}
			}
			elseif ($key == "Table") {
				$table = $value[0];
				$ret['table'] = (string) $table->name;
				$ret['mainIndex'] = isset($table->mainIndex) ? (string) $table->mainIndex : NULL;
				$ret['topicIndex'] = isset($table->topicIndex) ? (string) $table->topicIndex : NULL;
			}
			elseif ($key == "Column") {
				foreach ($value as $column) {
					$name = $column->name;
					unset($column->name);
					$ret['columns'][$name] = (array) $column;
				}
			}
			elseif (in_array($key, array("AssociationHasOne", "AssociationHasMany"))) {
				foreach ($value as $association) {
					$name = $association->name;
					unset($association->name);
					$ret['associations'][$name] = (array) $association;
				}
			}
		}

		return $ret;
	}





	public static function serializeArray($array)
	{
		$serialized = array();
		if (count($array)) {
			foreach ($array as $k => $v) {
				if (is_int($v)) {
					$serialized[] = $k . " = " . $v;
				}
				elseif ($v === NULL) {
					$serialized[] = $k . " = NULL";
				}
				elseif ($v === true) {
					$serialized[] = $k . " = true";
				}
				elseif ($v === false) {
					$serialized[] = $k . " = false";
				}
				else {
					$serialized[] = $k . " = \"" . $v . "\"";
				}
			}
		}

		return implode(", ", $serialized);
	}

}
