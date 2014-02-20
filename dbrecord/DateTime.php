<?php


namespace System\DbRecord;



/**
 * DateTime with __toString method, default format is database.
 *
 * @copyright  Copyright (c) 2010 Roman Matěna
 * @package    Solis
 */
class DateTime extends \DateTime
{

	public function __toString()
	{
		return $this->format('Y-m-d H:i:s');
	}

}
