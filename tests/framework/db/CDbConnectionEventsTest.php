<?php
Yii::import('system.db.CDbConnection');

class CDbConnectionEventsTest extends CTestCase
{
	/**
	 * @var CDbConnection
	 */
	private $_db;

	public function setUp()
	{
		if(!extension_loaded('pdo') || !extension_loaded('pdo_pgsql'))
			$this->markTestSkipped('PDO and PostgreSQL extensions are required.');

		$this->_db = new CDbConnection('pgsql:host=127.0.0.1;dbname=yii','test','test');
		$this->_db->charset = 'UTF8';
		try
		{
			$this->_db->active = true;
		}
		catch(Exception $e)
		{
			$schemaFile = realpath(dirname(__FILE__).'/data/postgres.sql');
			$this->markTestSkipped("Please read $schemaFile for details on setting up the test environment for PostgreSQL test case.");
		}

		try	{ $this->_db->createCommand('DROP SCHEMA test CASCADE')->execute(); } catch(Exception $e) { }
		try	{ $this->_db->createCommand('DROP TABLE yii_types CASCADE')->execute(); } catch(Exception $e) { }

		$sqls = file_get_contents(dirname(__FILE__).'/data/postgres.sql');
		foreach (explode(';',$sqls) as $sql)
		{
			if (trim($sql)!=='')
				$this->_db->createCommand($sql)->execute();
		}
	}

	public function tearDown()
	{
		$this->_db->active=false;
	}

	public function testTransactionAutocommit()
	{
		$this->assertFalse($this->_db->transactionAutocommit);
		$this->_db->transactionAutocommit = true;
		$this->assertTrue($this->_db->transactionAutocommit);
		$this->_db->transactionAutocommit = false;
		$this->assertFalse($this->_db->transactionAutocommit);

		$this->_db->active=true;

		$sql = 'SELECT COUNT(*) FROM test.users WHERE "id"=:id';

		$sql1 = "INSERT INTO test.users(id,username,password,email) VALUES (100,'user100','pass100','email100');";
		$sql2 = "INSERT INTO test.users(id,username,password,email) VALUES (101,'user101','pass101','email101');";
		$sql3 = "INSERT INTO test.users(id,username,password,email) VALUES (102,'user102','pass102','email102');";

		$transaction = $this->_db->beginTransaction();
		$this->assertTrue($transaction->getActive());

		$this->_db->pdoInstance->exec($sql1);
		$this->_db->pdoInstance->exec($sql2);
		$this->_db->pdoInstance->exec($sql3);

		$this->_db->active = false;
		$this->assertFalse($transaction->getActive());

		$this->_db->active = true;
		$selectStatement = $this->_db->createCommand($sql);
		$selectStatement->bindValue(':id', 100, \PDO::PARAM_INT);
		$this->assertEquals((int)$selectStatement->queryScalar(), 0);
		$selectStatement->bindValue(':id', 101, \PDO::PARAM_INT);
		$this->assertEquals((int)$selectStatement->queryScalar(), 0);
		$selectStatement->bindValue(':id', 102, \PDO::PARAM_INT);
		$this->assertEquals((int)$selectStatement->queryScalar(), 0);

		$this->_db->transactionAutocommit = true;

		$transaction = $this->_db->beginTransaction();
		$this->_db->pdoInstance->exec($sql1);
		$this->_db->pdoInstance->exec($sql2);
		$this->_db->pdoInstance->exec($sql3);
		$this->_db->active = false;
		$this->assertFalse($transaction->getActive());

		$this->_db->active = true;
		$selectStatement = $this->_db->createCommand($sql);
		$selectStatement->bindValue(':id', 100, \PDO::PARAM_INT);
		$this->assertEquals((int)$selectStatement->queryScalar(), 1);
		$selectStatement->bindValue(':id', 101, \PDO::PARAM_INT);
		$this->assertEquals((int)$selectStatement->queryScalar(), 1);
		$selectStatement->bindValue(':id', 102, \PDO::PARAM_INT);
		$this->assertEquals((int)$selectStatement->queryScalar(), 1);
	}
}

 