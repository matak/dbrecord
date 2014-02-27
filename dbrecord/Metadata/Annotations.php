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

		$ret = array();
		foreach ($annotations as $key => $value) {

			if ($key == "Entity") {
				$entity = $value[0];
				if (isset($entity->repositoryClass)) {
					$ret['repositoryClass'] = (string) $entity->repositoryClass;
				}
				elseif (isset($entity->mapperClass)) {
					$ret['mapperClass'] = (string) $entity->mapperClass;
				}
				elseif (isset($entity->validatorClass)) {
					$ret['validatorClass'] = (string) $entity->validatorClass;
				}
			}
			elseif ($key == "Table") {
				$table = $value[0];
				$ret['table'] = (string) $table->name;
			}
			elseif ($key == "property") {
				foreach ($value as $property) {
					$p = static::buildProperty($property);
					d($p);
					if (!$p) {
						continue;
					}

					if ($p['type'] == "Column") {
						$ret['columns'][$p['name']] = $p['definition'];
					}
					elseif (in_array($p['type'], array("AssociationHasMany", "AssociationHasOne"))) {
						$ret['associations'][$p['name']] = $p['definition'];
					}
				}
			}
		}

		return $ret;
	}





	protected static function buildProperty($property)
	{
		$ret = array();
		preg_match("/([^\s]+)[\s]+([^\s]+)[\s]+(.*)/", $property, $matches);
		d($matches);
		// aby to patrilo do entity property tak musi byt zadan treti blok, ktery zacina specificky
		if (isset($matches[3]) && preg_match("/^\b(Column|AssociationHasOne|AssociationHasMany)\b\((.*)\)/", $matches[3], $matches2)) {
			if (isset($matches2[2])) {
				$ret['name'] = ltrim($matches[2], "$");
				$ret['type'] = $type = $matches2[1];

				$definition = static::unserializeArray($matches2[2]);

				// pri teto asociaci nedavame referenceClass do definice
				if ($type == "AssociationHasOne") {
					$definition['referenceClass'] = $matches[1];
				}
				elseif ($type == "AssociationHasMany") {
					$definition['associatedCollectionClass'] = $matches[1];
				}

				$ret['definition'] = $definition;
				return $ret;
			}
		}

		return false;
	}





	protected static function unserializeArray($string)
	{
		$string = "{" . $string . "}";
		return \Nette\Utils\Neon::decode($string);
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
