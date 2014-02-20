<?php

/*
 * 		// validace objektu je tady pro validovani modelu, coz ne vzdy odpovida validovani formulare,
 *		// vystup z formulare muze byt ruzne upravovan nasledne pred samotnym ulozenim do dtb,
 *		// validace by mela respektovat pouze omezeni databaze (model ne vzdy prijima data z formulare, ale ruznymi jinymi procesy),
 *		// predchozi validace uz zalezi na okolnostech a funkcich ktere se pred samotnym ulozenim provedou,
 *		// *** tedy validace formulare se da generovat z validatoru ale nektere veci si musi upravit primo formular
 *
 */

/**
 * Basic validation object for DbRecord model.
 *
 * Allows basic insert, update validations of model, also allows generating
 * basic rules for Nette form which is based only on needs of model validation.
 * Other rules must be set manually with the form definition.
 *
 * Be carefull of efficiency, validation of 1000 objects and more takes time in seconds!
 * During mass transactions you should consider to disable this type of validations!
 *
 * @author     Roman Matěna inspired by David Grudl
 * @copyright  Copyright (c) 2010 Roman Matěna
 * @package    DbRecord
 */

namespace System\DbRecord;

use Nette\Object, 
		Nette\Forms\Form;

class DbValidator extends Object implements \ArrayAccess
{

	/**#@+ operation name */
	const EQUAL = Form::EQUAL;
	const IS_IN = Form::IS_IN;
	const FILLED = Form::FILLED;
	const VALID = Form::VALID;

	/**#@+ validation rule name */
	const MIN_LENGTH = Form::MIN_LENGTH;
	const MAX_LENGTH = Form::MAX_LENGTH;
	const LENGTH = Form::LENGTH;
	const EMAIL = Form::EMAIL;
	const PHONE = ':phone';
	const URL = Form::URL;
	const REGEXP = Form::REGEXP;
	const INTEGER = Form::INTEGER;
	const NUMERIC = Form::NUMERIC;
	const FLOAT = Form::FLOAT;
	const RANGE = Form::RANGE;

	/**#@+ predefined validation scopes */
	const VALIDATION_INSERT = "insert";
	const VALIDATION_UPDATE = "update";
	const VALIDATION_NOT = "not";

	/** @var array */
	protected $columns = array();

	/** @var array default translation messages (is tested on is_array => cant be initiated as array) */
	protected $defaultMessages;

	/** @var \Nette\Localization\ITranslator */
	protected static $translator;

	/**#@+ operation name /
	const EQUAL = ':equal';
	const IS_IN = ':equal';
	const FILLED = ':filled';
	const VALID = ':valid';

	// text
	const MIN_LENGTH = ':minLength';
	const MAX_LENGTH = ':maxLength';
	const LENGTH = ':length';
	const EMAIL = ':email';
	const URL = ':url';
	const REGEXP = ':regexp';
	const INTEGER = ':integer';
	const NUMERIC = ':integer';
	const FLOAT = ':float';
	const RANGE = ':range';
	*/

	public function  __construct($recordClass)
	{
		//$this->autoDetect($recordClass);
	}

	public function autoDetect($recordClass)
	{
		throw new \Nette\NotImplementedException;

		// autodetect je v podstate nemožný,
		// v případě mandatory ještě to neznamená, že není možné zadat prázdnou hotnodu jako ""
		// stejně tak v případě že sloupec není nullable, by neprošla ani hodnota ""
		// což není pravda i prázdné hodnoty mohou být řešením
		//
		//
		// podmínky jsou diskutabilní, nicméně lze uvažovat o tom,
		// že mandatory hodnota znamená, že ani prázdná hodnota nemá smysl
		//
		// u not nullable hodnot je potřeba testovat is_null

		$translator = $this->getTranslator();
		if ($translator) {

			$filledmsg = $translator->_('v/filled', 1,
				array(
					'{__ITEM__}' => "%s",
				)
			);

			$nullablemsg = $translator->_('v/filled', 1,
				array(
					'{__ITEM__}' => "%s",
				)
			);
		} else {

			$filledmsg = "Value (%s) is mandatory, must be filled!";
			$nullablemsg = "Value (%s) can not be NULL!";

		}

		// mandatory must be filled
		$columns = $recordClass::config()->getColumns();
		foreach ($columns as $name => $column) {

			if ($column['mandatory']) {

				$this->addColumn($name)
					->addRule(self::FILLED, $filledmsg);

			} elseif (!$column['nullable']) {

				$this->addColumn($name)
					->addRule(~'is_null', $nullablemsg);

			}
		}

	}

	public function addColumn($name, $column = NULL)
	{
		$this->columns[$name] = isset($column) ? $column : new DbValidatorColumn($name);
		return $this->columns[$name];
	}

	public function getColumns()
	{
		return $this->columns;
	}

	public function getColumn($name)
	{
		return $this->columns[$name];
	}

	public function isColumn($name)
	{
		return isset($this->columns[$name]);
	}

	public function removeColumn($name)
	{
		unset($this->columns[$name]);
		return $this;
	}

	/**
	 * Returns translator.
	 *
	 * @return \Nette\Localization\ITranslator
	 */
	public static function getTranslator()
	{
		return static::$translator;
	}

	/**
	 * Set translator.
	 *
	 * @param \Nette\Localization\ITranslator
	 */
	public static function setTranslator(\Nette\Localization\ITranslator $translator)
	{
		static::$translator = $translator;
	}

	/**
	 * Returns translated string.
	 * @param  string
	 * @param  int      plural count
	 * @return string
	 */
	public function translate($s, $count = NULL, $arg = NULL)
	{
		$translator = $this->getTranslator();
		return $translator === NULL ? $s : $translator->_($s, $count, $arg);
	}


	/**
	 * Return all messages for validation, it combines defaultMessages class static
	 * var and every parent overwrite by own message the for the same key. Finnally is the array enhanced.
	 *
	 * @staticvar mixed $msg
	 * @return mixed $msg
	 */
	public function getTranslationMessages()
	{
		return $this->defaultMessages;
	}


	/**
	 * Provides record validation,
	 * 1. For insert operations must be all columns valid (included all mandatories columns),
	 * 2. for update operations must be valid only modified columns,
	 *
	 * Right? Do you agree? Is there any situation where this statement isnt true?
	 *
	 * On the other hand, for generating rules of form, we need more variety of validation scopes then insert and update.
	 *
	 * Maybe the result could be two levels of validation scope, first for model validation and second for form validations,
	 * update form not necessarily must have all columns to set! but we can recognize which columns is set in form!
	 *
	 * So definitely we can say, we need only one level of validation scope and 3 types of validation scope!
	 *
	 * Similar problem we can find in function generateRules()
	 *
	 * @param DbRecord $record
	 * @param sring $validationScope
	 * @throws InvalidArgumentException
	 * @return void
	 */
	public function validate(DbRecord $record, $validationScope)
	{
		$record->changeValid(TRUE);

		if ($validationScope === self::VALIDATION_NOT) {
			return;
		}
		elseif ($validationScope === self::VALIDATION_INSERT) {

			foreach ($this->getColumns() as $column) {
		
				if (!$column->getRules()->validate($record)) {
					$record->changeValid(FALSE);
				}

			}
			
		}
		elseif ($validationScope === self::VALIDATION_UPDATE) {

			$modifiedValues = $record->getModifiedValues();
			foreach ($modifiedValues as $column => $v) {
				if (!$this->isColumn($column)) {
					continue;
				}
				
				if ($this->getColumn($column)
				&& !$this->getColumn($column)->getRules()->validate($record)) {
					$record->changeValid(FALSE);
				}
			}
			
		}
	}


	/**
	 * Form rules generator. I have serious concerns that this is useless,
	 * the validation of forms in most cases doesnt have same rules!
	 * Mostly it depends on another form values that we can represent in
	 * model validation.
	 *
	 * After some tests validationScope shows big WTF factor and the rules
	 * become very unclear and chaotic.
	 *
	 * @param Form $form
	 * @param sring $validationScope
	 * @return Form
	 */
	public static function generateRules(Form $form, $validationScope = NULL)
	{
		
		foreach ($this->getColumns() as $column) {
			foreach ($column->getRules() as $rule)  {
				if (!$validationScope || in_array($validationScope, $rule->validationScope)) continue;
				if ($rule->type === DbValidatorRule::VALIDATOR && isset($form[$rule->column->name])) {
					$form[$rule->column->name]->addRule($rule->operation, $rule->message, $rule->arg);
				}
			}
		}

		return $form;
	}


	/********************* validation *********************/



	/**
	 * Equal validator: are record's value and second parameter equal?
	 * @param  string value
	 * @param  mixed
	 * @return bool
	 */
	public static function validateEqual($column, DbRecord $record, $arg)
	{
		$value = $record->{$column->name};
		$value = (string) $value;
		foreach ((is_array($arg) ? $arg : array($arg)) as $item) {
			if ($item instanceof DbValidatorColumn) {
				
				if (!$record) {
					throw new \LogicException("Equal validation can´t be done, because of missing record.");
				}
				
				if ($value === (string) $record->{$item->name}) {
					return TRUE;
				}

			} 
			else {
				if ($value === (string) $item) return TRUE;
				
			}
		}
		return FALSE;
	}


	/**
	 * Filled validator: is control filled?
	 * @param  string value
	 * @return bool
	 */
	public static function validateFilled($column, DbRecord $record)
	{
		$value = $record->{$column->name};
		return (string) $value !== ''; // NULL, FALSE, '' ==> FALSE
	}



	/**
	 * Valid validator: is control valid?
	 * @param  DbValidatorColumn $column
	 * @return bool
	 */
	public static function validateValid($column)
	{
		return $column->rules->validate(TRUE);
	}


	/**
	 * Min-length validator: has control's value minimal length?
	 * @param  string value
	 * @param  int    length
	 * @return bool
	 */
	public static function validateMinLength($column, DbRecord $record, $length)
	{
		$value = $record->{$column->name};
		return iconv_strlen($value, 'UTF-8') >= $length;
	}

	/**
	 * Max-length validator: is control's value length in limit?
	 * @param  string value
	 * @param  int    length
	 * @return bool
	 */
	public static function validateMaxLength($column, DbRecord $record, $length)
	{
		$value = $record->{$column->name};
		return iconv_strlen($value, 'UTF-8') <= $length;
	}

	/**
	 * Length validator: is control's value length in range?
	 * @param  string value
	 * @param  array  min and max length pair
	 * @return bool
	 */
	public static function validateLength($column, DbRecord $record, $range)
	{
		$value = isset($record->{$column->name}) ? $record->{$column->name} : NULL;
		if (!is_array($range)) {
			$range = array($range, $range);
		}
		$len = iconv_strlen($value, 'UTF-8');
		return ($range[0] === NULL || $len >= $range[0]) && ($range[1] === NULL || $len <= $range[1]);
		
	}

	/**
	 * Email validator: is control's value valid email address?
	 * @param  string value
	 * @return bool
	 */
	public static function validateEmail($column, DbRecord $record)
	{
		$value = $record->{$column->name};
		$atom = "[-a-z0-9!#$%&'*+/=?^_`{|}~]"; // RFC 5322 unquoted characters in local-part
		$localPart = "(\"([ !\\x23-\\x5B\\x5D-\\x7E]*|\\\\[ -~])+\"|$atom+(\\.$atom+)*)"; // quoted or unquoted
		$chars = "a-z0-9\x80-\xFF"; // superset of IDN
		$domain = "[$chars]([-$chars]{0,61}[$chars])"; // RFC 1034 one domain component
		return (bool) preg_match("(^$localPart@($domain?\\.)+[a-z]{2,14}\\z)i", $value); // strict top-level domain
	}


	
	
	/**
	 * Email validator: is control's value valid phone number?
	 * @param  string value
	 * @return bool
	 */
	public static function validatePhone($column, DbRecord $record)
	{
		// povoli polsko, česko, slovensko
		$regexp = '/^(\+48|\+420|\+421)[0-9]{9}$/';
		$value = $record->{$column->name};
		return (bool) preg_match($regexp, $value);
	}

	/**
	 * URL validator: is control's value valid URL?
	 * @param  string value
	 * @return bool
	 */
	public static function validateUrl($column, DbRecord $record)
	{
		$value = $record->{$column->name};
		return (bool) preg_match('/^.+\.[a-z]{2,6}(\\/.*)?$/i', $value);
	}

	/**
	 * Regular expression validator: matches control's value regular expression?
	 * @param  string value
	 * @param  string
	 * @return bool
	 */
	public static function validateRegexp($column, DbRecord $record, $regexp)
	{
		$value = $record->{$column->name};
		return (bool) preg_match($regexp, $value);
	}

	/**
	 * Integer validator: is a control's value decimal number?
	 * @param  int value
	 * @return bool
	 */
	public static function validateInteger($column, DbRecord $record)
	{
		$value = $record->{$column->name};
		return (bool) preg_match('/^-?[0-9]+$/', $value);
	}

	/**
	 * Float validator: is a control's value float number?
	 * @param  float value
	 * @return bool
	 */
	public static function validateFloat($column, DbRecord $record)
	{
		$value = $record->{$column->name};
		return (bool) preg_match('/^-?[0-9]*[.,]?[0-9]+$/', $value);
	}

	/**
	 * Rangle validator: is a control's value number in specified range?
	 * @param  int value
	 * @param  array  min and max value pair
	 * @return bool
	 */
	public static function validateRange($column, DbRecord $record, $range)
	{
		if (!isset($record->{$column->name})) {
			return false;
		}

		$value = $record->{$column->name};
		return ($range[0] === NULL || $value >= $range[0]) && ($range[1] === NULL || $value <= $range[1]);
	}

	/********************* interface \ArrayAccess ****************d*g**/



	/**
	 * Adds the component to the container.
	 * @param  string  component name
	 * @param  Nette\IComponent
	 * @return void
	 */
	final public function offsetSet($name, $column)
	{
		$this->addColumn($column, $name);
	}



	/**
	 * Returns component specified by name. Throws exception if component doesn't exist.
	 * @param  string  component name
	 * @return Nette\IComponent
	 * @throws \Nette\InvalidArgumentException
	 */
	final public function offsetGet($name)
	{
		return $this->getColumn($name);
	}



	/**
	 * Does component specified by name exists?
	 * @param  string  component name
	 * @return bool
	 */
	final public function offsetExists($name)
	{
		return $this->getColumn($name) !== NULL;
	}



	/**
	 * Removes component from the container.
	 * @param  string  component name
	 * @return void
	 */
	final public function offsetUnset($name)
	{
		$column = $this->getColumn($name);
		if ($column !== NULL) {
			$this->removeColumn($column);
		}
	}


}


/**
 * DbValidatorColumn represents column of database model (DbRecord) also a FormControl in Nette forms.
 *
 * @author     Roman MatÄ›na inspired by David Grudl
 * @copyright  Copyright (c) 2010 Roman MatÄ›na
 * @package    DbRecord
 */
class DbValidatorColumn extends Object
{

	/** @var string column name */
	private $name;

	/** @var Rules */
	private $rules;

	/** @var bool */
	private $disabled = FALSE;

	/**
	 * @param string name
	 */
	public function __construct($name)
	{
		$this->name = $name;
		$this->rules = new DbValidatorRules($this);
	}

	/**
	 * Adds a validation rule.
	 * @param  mixed      rule type
	 * @param  string     message to display for invalid data
	 * @param  mixed      optional rule arguments
	 * @return DbValidatorColumn  provides a fluent interface
	 */
	public function addRule($operation, $message = NULL, $arg = NULL, $validationScope = array())
	{
		$this->rules->addRule($operation, $message, $arg, $validationScope);
		return $this;
	}
	

	/**
	 * Adds a validation condition a returns new branch.
	 * @param  mixed     condition type
	 * @param  mixed      optional condition arguments
	 * @return Rules      new branch
	 */
	public function addCondition($operation, $value = NULL)
	{
		return $this->rules->addCondition($operation, $value);
	}



	/**
	 * Adds a validation condition based on another control a returns new branch.
	 * @param  DbValidatorColumn validator column
	 * @param  mixed      condition type
	 * @param  mixed      optional condition arguments
	 * @return Rules      new branch
	 */
	public function addConditionOn(DbValidatorColumn $column, $operation, $value = NULL)
	{
		return $this->rules->addConditionOn($column, $operation, $value);
	}
	

	/**
	 * Disables or enables column validation.
	 * @param  bool
	 * @return FormControl  provides a fluent interface
	 */
	public function setDisabled($value = TRUE)
	{
		$this->disabled = (bool) $value;
		return $this;
	}

	/**
	 * Is column disabled?
	 * @return bool
	 */
	public function isDisabled()
	{
		return $this->disabled;
	}

	/**
	 * @return Rules
	 */
	final public function getRules()
	{
		return $this->rules;
	}

	/**
	 * @return name
	 */
	public function getName()
	{
		return $this->name;
	}


}

/**
 * List of validation & condition rules.
 *
 * @author     Roman Matěna inspired by David Grudl
 * @copyright  Copyright (c) 2010 Roman Matěna
 * @package    DbRecord
 */



final class DbValidatorRules extends Object implements \IteratorAggregate
{
	/** @ignore internal */
	const VALIDATE_PREFIX = 'validate';

	/** @var array */
	public static $defaultMessages = array(
		DbValidator::RANGE => 'VALIDATION_RANGE %name %value',
	);

	/** @var array of Rule */
	private $rules = array();

	/** @var Rules */
	private $parent;

	/** @var DbColumn */
	private $column;

	/**
	 * @param DbColumn $column
	 */
	public function __construct($column)
	{
		$this->column = $column;
	}

	/**
	 * Adds a validation rule for the current column.
	 * @param  mixed      rule type
	 * @param  string     message to display for invalid data
	 * @param  mixed      optional rule arguments
	 * @return Rules      provides a fluent interface
	 */
	public function addRule($operation, $message = NULL, $arg = NULL, $validationScope = array())
	{
		$rule = new DbValidatorRule;
		$rule->column = $this->column;
		$rule->operation = $operation;
		$rule->validationScope = $validationScope;
		$this->adjustOperation($rule);
		$rule->arg = $arg;
		$rule->type = DbValidatorRule::VALIDATOR;
		if ($message === NULL && isset(self::$defaultMessages[$rule->operation])) {
			$rule->message = self::$defaultMessages[$rule->operation];
		} else {
			$rule->message = $message;
		}

		$this->rules[] = $rule;
		return $this;
	}

	/**
	 * Adds a validation condition a returns new branch.
	 * @param  mixed      condition type
	 * @param  mixed      optional condition arguments
	 * @return Rules      new branch
	 */
	public function addCondition($operation, $arg = NULL)
	{
		return $this->addConditionOn($this->column, $operation, $arg);
	}



	/**
	 * Adds a validation condition on specified control a returns new branch.
	 * @param  IFormControl form control
	 * @param  mixed      condition type
	 * @param  mixed      optional condition arguments
	 * @return Rules      new branch
	 */
	public function addConditionOn(DbValidatorColumn $column, $operation, $arg = NULL, $validationScope = array())
	{
		$rule = new DbValidatorRule;
		$rule->column = $column;
		$rule->operation = $operation;
		$rule->validationScope = $validationScope;
		$this->adjustOperation($rule);
		$rule->arg = $arg;
		$rule->type = DbValidatorRule::CONDITION;
		$rule->subRules = new self($this->column);
		$rule->subRules->parent = $this;

		$this->rules[] = $rule;
		return $rule->subRules;
	}


	/**
	 * Validates against ruleset.
	 * @param  DbRecord $record
	 * @param  bool     stop before first error?
	 * @return bool     is valid?
	 */
	public function validate(DbRecord $record, $onlyCheck = FALSE)
	{
		$valid = TRUE;
		foreach ($this->rules as $rule) {

			if ($rule->column->isDisabled()) {
				continue;
			}

			$success = ($rule->isNegative xor $this->getCallback($rule)->invoke($rule->column, $record, $rule->arg)); // drasticky narocne na vykon
			//$success = ($rule->isNegative xor call_user_func($rule->callback, $record->{$rule->column->name}, $rule->arg, $record)); // pozor tento Ĺ™Ăˇdek je kriticky nĂˇroÄŤnĂ˝ na vĂ˝kon!

			if ($rule->type === DbValidatorRule::CONDITION && $success) {
				$success = $rule->subRules->validate($record, $onlyCheck);
				$valid = $valid && $success;

			} elseif ($rule->type === DbValidatorRule::VALIDATOR && !$success) {
				if ($onlyCheck) {
					return FALSE;
				}
				$record->addError(self::formatMessage($rule, $record), $rule->column->name);
				$valid = FALSE;
				if ($rule->breakOnFailure) {
					break;
				}
			}
		}
		return $valid;
	}

	/**
	 * Process 'operation' string.
	 * @param  Rule
	 * @return void
	 */
	private function adjustOperation($rule)
	{
		if (is_string($rule->operation) && ord($rule->operation[0]) > 127) {
			$rule->isNegative = TRUE;
			$rule->operation = ~$rule->operation;
		}

		if (!$this->getCallback($rule)->isCallable()) {
			$operation = is_scalar($rule->operation) ? " '$rule->operation'" : '';
			throw new \Nette\InvalidArgumentException("Unknown operation $operation for column '{$rule->column->name}'.");
		}

	}



	private function getCallback($rule)
	{
		$op = $rule->operation;
		if (is_string($op) && strncmp($op, ':', 1) === 0) {
			return callback(__NAMESPACE__."\DbValidator", self::VALIDATE_PREFIX . ucfirst(ltrim($op, ':')));

		} else {
			return callback($op);
		}
	}

	/**
	 * Iterates over ruleset.
	 * @return ArrayIterator
	 */
	final public function getIterator()
	{
		return new ArrayIterator($this->rules);
	}

	public static function formatMessage($rule, $record)
	{
		$message = $rule->message;
		$message = str_replace('%name', $rule->column->getName(), $message);
		if (strpos($message, '%value') !== FALSE) {
			$message = str_replace('%value', isset($record->{$rule->column->name})?(string)$record->{$rule->column->name}:"undefined", $message);
		}
		$message = vsprintf($message, (array) $rule->arg);
		return $message;

	}


}

/**
 * Single validation rule or condition represented as value object.
 *
 * @author     Roman MatÄ›na inspired by David Grudl
 * @copyright  Copyright (c) 2010 Roman MatÄ›na
 * @package    DbRecord
 */
final class DbValidatorRule extends Object
{
	/** type */
	const CONDITION = 1;

	/** type */
	const VALIDATOR = 2;

	/** type */
	const FILTER = 3;

	/** type */
	const TERMINATOR = 4;

	/** @var DbColumn */
	public $column;

	/** @var mixed */
	public $operation;

	/** @var mixed */
	public $callback;	// !! speed hack !! better way to store callback then find it again and again during validation

	/** @var mixed */
	public $arg;

	/** @var int (CONDITION, VALIDATOR, FILTER) */
	public $type;

	/** @var bool */
	public $isNegative = FALSE;

	/** @var string (only for VALIDATOR type) */
	public $message;

	/** @var bool (only for VALIDATOR type) */
	public $breakOnFailure = TRUE;

	/** @var Rules (only for CONDITION type)  */
	public $subRules;

	/** @var array  */
	public $validationScope = array();

}
