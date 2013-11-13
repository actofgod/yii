<?php

Yii::import('system.db.CDbConnection');

class CDbTransactionTest extends CTestCase
{
	/**
	 * @var CDbConnection
	 */
	private $_connection;
	private $_raises;

	public function setUp()
	{
		if(!extension_loaded('pdo') || !extension_loaded('pdo_sqlite'))
			$this->markTestSkipped('PDO and SQLite extensions are required.');

		$this->_connection=new CDbConnection('sqlite::memory:');
		$this->_connection->active=true;
		$this->_connection->pdoInstance->exec(file_get_contents(dirname(__FILE__).'/data/sqlite.sql'));
		$this->_raises = 0;
	}

	public function tearDown()
	{
		$this->_connection->active=false;
	}

	public function testBeginTransaction()
	{
		$sql='INSERT INTO posts(id,title,create_time,author_id) VALUES(10,\'test post\',11000,1)';
		$transaction=$this->_connection->beginTransaction();
		try
		{
			$this->_connection->createCommand($sql)->execute();
			$this->_connection->createCommand($sql)->execute();
			$this->fail('Expected exception not raised');
			$transaction->commit();
		}
		catch(Exception $e)
		{
			$transaction->rollback();
			$reader=$this->_connection->createCommand('SELECT * FROM posts WHERE id=10')->query();
			$this->assertFalse($reader->read());
		}
	}

	public function testCommit()
	{
		$sql='INSERT INTO posts(id,title,create_time,author_id) VALUES(10,\'test post\',11000,1)';
		$transaction=$this->_connection->beginTransaction();
		try
		{
			$this->_connection->createCommand($sql)->execute();
			$this->assertTrue($transaction->active);
			$transaction->commit();
			$this->assertFalse($transaction->active);
		}
		catch(Exception $e)
		{
			$transaction->rollback();
			$this->fail('Unexpected exception');
		}
		$n=$this->_connection->createCommand('SELECT COUNT(*) FROM posts WHERE id=10')->queryScalar();
		$this->assertEquals($n,1);
	}

	public function testBeforeCommitEventRaising()
	{
		$sql = 'INSERT INTO posts(id,title,create_time,author_id) VALUES(10,\'test post\',11000,1)';
		$transaction = $this->_connection->beginTransaction();
		$transaction->attachEventHandler('onBeforeCommit', array($this, 'onBeforeRaiseTrue'));
		try
		{
			$this->_connection->createCommand($sql)->execute();
			$this->assertTrue($transaction->active);
			$transaction->commit();
			$this->assertFalse($transaction->active);
			$this->assertEquals($this->_raises, 1);
		}
		catch(Exception $e)
		{
			$transaction->rollback();
			$this->fail('Unexpected exception');
		}
		$n=$this->_connection->createCommand('SELECT COUNT(*) FROM posts WHERE id=10')->queryScalar();
		$this->assertEquals($n,1);
	}

	public function testBeforeCommitFalseEventRaising()
	{
		$sql = 'INSERT INTO posts(id,title,create_time,author_id) VALUES(10,\'test post\',11000,1)';
		$transaction = $this->_connection->beginTransaction();
		$transaction->attachEventHandler('onBeforeCommit', array($this, 'onBeforeRaiseFalse'));
		try
		{
			$this->_connection->createCommand($sql)->execute();
			$this->assertTrue($transaction->active);
			$transaction->commit();
			$this->assertTrue($transaction->active);
			$this->assertEquals($this->_raises, 1);
			$transaction->rollback();
		}
		catch(Exception $e)
		{
			$transaction->rollback();
			$this->fail('Unexpected exception');
		}
		$n=$this->_connection->createCommand('SELECT COUNT(*) FROM posts WHERE id=10')->queryScalar();
		$this->assertEquals($n, 0);
	}

	public function testBeforeRollbackEventRaising()
	{
		$sql = 'INSERT INTO posts(id,title,create_time,author_id) VALUES(10,\'test post\',11000,1)';
		$transaction = $this->_connection->beginTransaction();
		$transaction->attachEventHandler('onBeforeRollback', array($this, 'onBeforeRaiseTrue'));
		try
		{
			$this->_connection->createCommand($sql)->execute();
			$this->_connection->createCommand($sql)->execute();
			$this->fail('Expected exception not raised');
			$transaction->commit();
		}
		catch(Exception $e)
		{
			$transaction->rollback();
			$this->assertFalse($transaction->active);
			$this->assertEquals($this->_raises, 1);
		}
		$n=$this->_connection->createCommand('SELECT COUNT(*) FROM posts WHERE id=10')->queryScalar();
		$this->assertEquals($n, 0);
	}

	public function testBeforeRollbackFalseEventRaising()
	{
		$sql = 'INSERT INTO posts(id,title,create_time,author_id) VALUES(10,\'test post\',11000,1)';
		$transaction = $this->_connection->beginTransaction();
		$transaction->attachEventHandler('onBeforeRollback', array($this, 'onBeforeRaiseFalse'));
		try
		{
			$this->_connection->createCommand($sql)->execute();
			$this->_connection->createCommand($sql)->execute();
			$this->fail('Expected exception not raised');
			$transaction->commit();
		}
		catch(Exception $e)
		{
			$transaction->rollback();
			$this->assertTrue($transaction->active);
			$this->assertEquals($this->_raises, 1);
			$transaction->commit();
		}
		$n=$this->_connection->createCommand('SELECT COUNT(*) FROM posts WHERE id=10')->queryScalar();
		$this->assertEquals($n, 1);
	}

	public function onBeforeRaiseTrue(CDbTransactionEvent $event)
	{
		$this->_raises++;
	}

	public function onBeforeRaiseFalse(CDbTransactionEvent $event)
	{
		$this->_raises++;
		$event->isValid = false;
	}
}

