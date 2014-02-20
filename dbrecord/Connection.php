<?php

namespace System\DbRecord;

use DibiEvent;
use DibiException;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onEvent(DibiEvent $event)
 */
class Connection extends \DibiConnection
{

	/** @var bool  Is in transaction? */
	private $inTxn = FALSE;


	/**
	 * Is in transaction?
	 * @return bool
	 */
	public function inTransaction()
	{
		return $this->inTxn;
	}



	public function begin($savepoint = NULL)
	{
		$this->connected || $this->connect();
		if (!$savepoint && $this->inTxn) {
			throw new DibiException('There is already an active transaction.');
		}

		$event = $this->onEvent ? new DibiEvent($this, DibiEvent::BEGIN, $savepoint) : NULL;
		try {
			$this->driver->begin($savepoint);
			$this->inTxn = TRUE;
			$event && $this->onEvent($event->done());

		} catch (DibiException $e) {
			$event && $this->onEvent($event->done($e));
			throw $e;
		}
	}



	public function commit($savepoint = NULL)
	{
		if (!$this->inTxn) {
			throw new DibiException('There is no active transaction.');
		}

		$this->connected || $this->connect();
		$event = $this->onEvent ? new DibiEvent($this, DibiEvent::COMMIT, $savepoint) : NULL;
		try {
			$this->driver->commit($savepoint);
			$this->inTxn = (bool) $savepoint;

			$event && $this->onEvent($event->done());

		} catch (DibiException $e) {
			$event && $this->onEvent($event->done($e));
			throw $e;
		}
	}



	public function rollback($savepoint = NULL)
	{
		if (!$this->inTxn) {
			throw new DibiException('There is no active transaction.');
		}

		$this->connected || $this->connect();
		$event = $this->onEvent ? new DibiEvent($this, DibiEvent::ROLLBACK, $savepoint) : NULL;
		try {
			$this->driver->rollback($savepoint);
			$this->inTxn = (bool) $savepoint;

			$event && $this->onEvent($event->done());

		} catch (DibiException $e) {
			$event && $this->onEvent($event->done($e));
			throw $e;
		}
	}

}
