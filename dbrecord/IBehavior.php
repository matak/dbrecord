<?php

namespace dbrecord;


/**
 * Interface Behavior
 *
 * @author Jan Marek
 * @license MIT
 */
interface IBehavior
{
	const BEFORE_INSERT = 'beforeInsert';
	const BEFORE_INSERT_AFTER_VALIDATION = 'beforeInsertAfterValidation';
	const AFTER_INSERT = 'afterInsert';
	const BEFORE_UPDATE = 'beforeUpdate';
	const BEFORE_UPDATE_AFTER_VALIDATION = 'beforeUpdateAfterValidation';
	const AFTER_UPDATE = 'afterUpdate';
	const BEFORE_DELETE = 'beforeDelete';
	const AFTER_DELETE = 'afterDelete';
}
