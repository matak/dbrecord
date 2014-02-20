<?php

namespace System\DbRecord;

class Exception extends \Exception
{
	
	protected $entity;
	
	public function __construct ($message = NULL, $code = NULL, $previous = NULL, $entity = NULL) 
	{
		parent::__construct($message, $code, $previous);
		$this->entity = $entity;
	}
	

	
	public function getEntity()
	{
		return $this->entity;
	}
	
}

class NotFoundException extends Exception
{
}

class FluentOnNonExistingParentOfAssociationException extends Exception
{
}
