<?php
/**
 * CDbTransaction class file
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

/**
 * CDbTransaction represents a DB transaction.
 *
 * It is usually created by calling {@link CDbConnection::beginTransaction}.
 *
 * The following code is a common scenario of using transactions:
 * <pre>
 * $transaction=$connection->beginTransaction();
 * try
 * {
 *    $connection->createCommand($sql1)->execute();
 *    $connection->createCommand($sql2)->execute();
 *    //.... other SQL executions
 *    $transaction->commit();
 * }
 * catch(Exception $e)
 * {
 *    $transaction->rollback();
 * }
 * </pre>
 *
 * @property CDbConnection $connection The DB connection for this transaction.
 * @property boolean $active Whether this transaction is active.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.db
 * @since 1.0
 */
class CDbTransaction extends CComponent
{
	private $_connection=null;
	private $_active;

	/**
	 * Constructor.
	 * @param CDbConnection $connection the connection associated with this transaction
	 * @see CDbConnection::beginTransaction
	 */
	public function __construct(CDbConnection $connection)
	{
		$this->_connection=$connection;
		$this->_active=true;
	}

	public function __destruct()
	{
		if ($this->_active) {
			if ($this->_connection->getTransactionAutocommit())
				$this->commit();
			else
				$this->rollback();
		}
	}

	/**
	 * Commits a transaction.
	 * @throws CException if the transaction or the DB connection is not active.
	 */
	public function commit()
	{
		if($this->_active && $this->_connection->getActive())
		{
			Yii::trace('Committing transaction','system.db.CDbTransaction');
			if ($this->beforeCommit()) {
				$this->_connection->getPdoInstance()->commit();
				$this->_active=false;
				if ($this->hasEventHandler('onAfterCommit'))
					$this->onAfterCommit(new CDbTransactionEvent($this->_connection, $this));
			}
		}
		else
			throw new CDbException(Yii::t('yii','CDbTransaction is inactive and cannot perform commit or roll back operations.'));
	}

	/**
	 * Rolls back a transaction.
	 * @throws CException if the transaction or the DB connection is not active.
	 */
	public function rollback()
	{
		if($this->_active && $this->_connection->getActive())
		{
			Yii::trace('Rolling back transaction','system.db.CDbTransaction');
			if ($this->beforeRollback()) {
				$this->_connection->getPdoInstance()->rollBack();
				$this->_active=false;
				if ($this->hasEventHandler('onAfterRollback'))
					$this->onAfterRollback(new CDbTransactionEvent($this->_connection, $this));
			}
		}
		else
			throw new CDbException(Yii::t('yii','CDbTransaction is inactive and cannot perform commit or roll back operations.'));
	}

	/**
	 * @return CDbConnection the DB connection for this transaction
	 */
	public function getConnection()
	{
		return $this->_connection;
	}

	/**
	 * @return boolean whether this transaction is active
	 */
	public function getActive()
	{
		return $this->_active;
	}

	/**
	 * @param boolean $value whether this transaction is active
	 */
	protected function setActive($value)
	{
		$this->_active=$value;
	}

	protected function beforeCommit()
	{
		if ($this->hasEventHandler('onBeforeCommit')) {
			$event = new CDbTransactionEvent();
			$this->onBeforeCommit($event);
			return $event->isValid;
		}
		else
			return true;
	}

	public function onBeforeCommit(CDbTransactionEvent $event)
	{
		$this->raiseEvent('onBeforeCommit', $event);
	}

	public function beforeRollback()
	{
		if ($this->hasEventHandler('onBeforeCommit')) {
			$event = new CDbTransactionEvent();
			$this->onBeforeRollback($event);
			return $event->isValid;
		}
		else
			return true;
	}

	public function onBeforeRollback(CDbTransactionEvent $event)
	{
		$this->raiseEvent('onBeforeRollback', $event);
	}

	public function onAfterCommit(CDbTransactionEvent $event)
	{
		$this->raiseEvent('onAfterCommit', $event);
	}

	public function onAfterRollback(CDbTransactionEvent $event)
	{
		$this->raiseEvent('onAfterRollback', $event);
	}
}
