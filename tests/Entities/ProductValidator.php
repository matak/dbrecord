<?php

namespace dbrecord\Test\Entities;

use dbrecord\EntityValidator;


/**
 * Validation class of Product
 *
 * @author Roman Matěna
 * @copyright Copyright (c) 2010 Roman Matěna (http://www.romanmatena.cz)
 */
class ProductValidator extends EntityValidator
{
	public function __construct()
	{
		$msg = $this->getTranslationMessages();

		$this->addColumn('VATtype')
			->addRule(self::FILLED, isset($msg['VATtype']) ? $msg['VATtype'] : "");

	}

	public function getTranslationMessages()
	{

		static $msg;

		if (!isset($msg)) {

			$msg = parent::getTranslationMessages();

			if (!is_array($msg)) {
				$msg = array();
			}

			$translator = $this->getTranslator();
			if ($translator) {
				/* VAT */
				$msg['VATtype']=$translator->_('v/filled', 1,
					array(
						'{__ITEM__}' => "DPH",
					)
				);

			}

			if (is_array($this->defaultMessages))
				$msg = array_merge($this->defaultMessages, $msg);

		}

		return $msg;
	}

}
