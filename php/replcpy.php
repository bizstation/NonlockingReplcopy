<?php
/* ================================================================
   Copyright (C) 2016 BizStation Corp All rights reserved.

   This program is free software; you can redistribute it and/or
   modify it under the terms of the GNU General Public License
   as published by the Free Software Foundation; either version 2
   of the License, or (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software 
   Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  
   02111-1307, USA.
================================================================ */
require_once('transactd.php');
use BizStation\Transactd as bz;

const DYNAMIC_REPLCOPY_VERSION = "1.0.0";

const VER_IDX_DB_SERVER         = 1;

const REP_TYPE_REGACY           = 0;
const REP_TYPE_GTID_MA          = 1;
const REP_TYPE_GTID_MY          = 2;
const REP_TYPE_GTID_MY_POS      = 3;

const ER_BAD_SLAVE              = 26200;
const MASTER_LOG_FILE           = 5;
const MASTER_LOG_POS            = 6;
const SLAVE_IO_RUNNINGE         = 10;
const SLAVE_SQL_RUNNINGE        = 11;
const SLAVE_EXEC_MASTER_LOG_POS = 21;
const SLAVE_LAST_IO_ERRNO       = 34;
const SLAVE_LAST_IO_ERROR       = 35;
const SLAVE_LAST_SQL_ERRNO      = 36;
const SLAVE_LAST_SQL_ERROR      = 37;
const SLAVE_STATUS_MASTER_UUID  = 40;
const SLAVE_EXECUTED_GTID_SET   = 52;

const MYSQL_ERROR_OFFSET        = 25000;
const MYSQL_ER_UNKNOWN_TABLE_NAME  = 1051;

function validateStat($obj, $msg)
{
	if ($obj->stat())
	{
		$stat = $obj->stat();
		if ($stat > MYSQL_ERROR_OFFSET)
			throw new Exception($msg . " MySQL/MariaDB error code " . ($stat - MYSQL_ERROR_OFFSET));
		throw new Exception($msg . " Transactd stat = " . $stat);
	}
}

function getUri($param, $type, $db)
{
	$user = $param[$type]["user"] != "" ? $param[$type]["user"]."@" : "";
	$pwd = $param[$type]["passwd"] != "" ? "?pwd=".$param[$type]["passwd"] : "";
	return "tdap://" . $user . $param[$type]["host"] . "/" .  $db. $pwd;
}

function getSlaveUri($param, $db) { return getUri($param, "slave", $db); }

function getMasterUri($param, $db) { return getUri($param, "master", $db); }

function getStartSlaveSql($binlog)
{
	return "start slave until master_log_file='" .$binlog->filename .
			"', master_log_pos=".$binlog->pos;
}

function removePortFromHost($host)
{
	$pos = strpos($host, ':');
	if ($pos === false) return $host;
	return substr($host , 0 , $pos );
}

function getChangeMasterSqlDef($param)
{
	return "change master to master_host='". removePortFromHost($param["master"]["host"]) .
			 "', master_port=" . $param["master"]["repl_port"] .
			 ", master_user='" . $param["master"]["repl_user"] .
			 "', master_password='". $param["master"]["repl_passwd"];
}

function getChangeMasterSqlPos($param, $binlog)
{
	return getChangeMasterSqlDef($param) .
			 "', master_log_file='". $binlog->filename . 
			 "', master_log_pos=" . $binlog->pos;
}

function getChangeMasterSqlGtidMa($param)
{
	return getChangeMasterSqlDef($param) . "', master_use_gtid=slave_pos";
}

function getChangeMasterSqlGtidMy($param)
{
	return getChangeMasterSqlDef($param) . "', master_auto_position=1";
}

function getChangeMasterSql($param, $binlog)
{
	if ($param["gtid"]["type"] == REP_TYPE_REGACY) return getChangeMasterSqlPos($param, $binlog);
	if ($param["gtid"]["type"] == REP_TYPE_GTID_MA) return getChangeMasterSqlGtidMa($param);
	if ($param["gtid"]["type"] == REP_TYPE_GTID_MY) return getChangeMasterSqlGtidMy($param);
}

function getParamArray($param, $key1, $key2)
{
	$v = array();
	if (array_key_exists($key2, $param[$key1]))
		$v = explode(',', $param[$key1][$key2]);
	if (count($v) == 1 && trim($v[0]) === '') $v = array();
	return $v;
}

function getDbNameForSlave($param)
{
	$dbnames = getParamArray($param, "master", "databases");
	$n = count($dbnames);
	if ($n == 1 && ($dbnames[0] != ""))
		return $dbnames[0];
	return "mysql";
}

function dbnameFromUri($uri)
{
	$pos = strpos($uri, '/', 8) + 1;
	$pos2 = strpos($uri, '?', $pos);
	if ($pos2 === false)
		$pos2 = strpos($uri, '&', $pos);
	if ($pos2 === false)
		return substr ($uri , $pos);
	return substr ($uri , $pos, $pos2 - $pos);
}

function createOpen($db, $uri)
{
	$dbname = dbnameFromUri($uri);
	$db->open($uri);
	if ($db->stat() == bz\transactd::ERROR_NO_DATABASE && ($dbname != "mysql"))
	{
		$db->create($uri);
		validateStat($db, "Can not create database. uri=" . $uri);
		$db->open($uri);
	}
	validateStat($db, "Can not open database. uri=" . $uri);
}

function openSlaveDb($db, $param)
{
	$dbname = getDbNameForSlave($param);
	$uri = getSlaveUri($param, $dbname);
	createOpen($db, $uri);
}

function getDatabaseList($param)
{
	$mgr = createMasterConnMgr($param);
	$recs = $mgr->databases();
	validateStat($mgr, "getDatabaseList");
	$mgr->disconnect();
	unset($mgr);
	$dbNames =  array();
	bz\connMgr::removeSystemDb($recs);
	foreach($recs as $rec)
		$dbNames[] = $rec->name;
	return $dbNames;
}

function getMasterUris($param)
{
	$uris = array();
	$dbnames = getParamArray($param, "master", "databases");
	$n = count($dbnames);
	if ($n == 1 && ($dbnames[0] != ""))
	{
		$uris[] = getMasterUri($param, $param["master"]["databases"]);
		return $uris;
	}
	else if ((($n == 1) && ($dbnames[0] == "")) || ($n == 0))
		$dbnames = getDatabaseList($param);

	foreach ($dbnames as $name)
		$uris[] = getUri($param, "master", $name);
	return $uris;
}

function setSqlMode($db) { execSql($db, "set session sql_mode=''"); }

function setLogBinOff($db) { execSql($db, "set session sql_log_bin=OFF"); }

function openMasterDb($db, $param)
{
	$uris = getMasterUris($param);
	$dbs = array();
	$n = count($uris);
	if ($n == 0) throw new Exception("No replication databases");
	
	$i = 0;
	foreach ($uris as $uri)
	{
		$dbtmp = $db;
		if($i != 0)
			$dbtmp = $db->createAssociate();
		$dbtmp->open($uri, bz\transactd::TYPE_SCHEMA_BDF, bz\transactd::TD_OPEN_READONLY);
		validateStat($db, "Can not open master database. uri=" . $uri);
		$dbs[] = $dbtmp;
		++$i;
	}
	return $dbs;
}

function createConnMgr($uri)
{
	$db = new bz\database();
	$mgr = new bz\connMgr($db);
	$mgr->connect($uri);
	validateStat($mgr, "connMgr connect");
	return $mgr;
}

function createSlaveConnMgr($param)
{
	return createConnMgr(getSlaveUri($param, ""));
}

function createMasterConnMgr($param)
{
	return createConnMgr(getMasterUri($param,""));
}

function execSql($db, $sql)
{
	$db->execSql($sql);
	validateStat($db, $sql . " error");
}

function stopSlave($db) { execSql($db, "stop slave"); }

function resetSlave($db) { execSql($db, "reset slave all"); }

function startSlave($db) { execSql($db, "start slave"); }

function listupMasterTables($param, $dburi)
{
	$dbname = dbnameFromUri($dburi);
	$mgr = createMasterConnMgr($param);
	$recs = $mgr->tables($dbname);
	validateStat($mgr, "listupMasterTables");
	$mgr->disconnect();
	unset($mgr);
	$tableNames =  array();
	
	foreach($recs as $rec)
		$tableNames[] = $rec->name;
	
	return $tableNames;
}

function removeIgnores($tableNames, $ignoreTables)
{
	foreach ($ignoreTables as $name)
	{
		$key = array_search($name, $tableNames);
		if ($key != false)
			unset($tableNames[$key]);
	}
	return $tableNames;
}

function openMasterTables($dbs, $param)
{
	$tableNames = array(); 
	$dbcount = count($dbs);
	$ignoreTables = getParamArray($param, "master", "ignore_tables");
	
	//mysql.general_log and mysql.slow_log tables are mariadb system tables.
	$ignoreTables[] = "general_log";
	$ignoreTables[] = "slow_log";
	
	if ($dbcount == 1)
		$tableNames = getParamArray($param, "master", "tables");
	$n = count($tableNames);
	$tbs = array();
	for ($j = 0; $j < $dbcount; ++$j)
	{
		if (($j > 0) || $n == 0 || $tableNames[0] == "")
		{
			$tableNames = listupMasterTables($param, $dbs[$j]->uri());
			$n = count($tableNames);
		}
		$tableNames = removeIgnores($tableNames, $ignoreTables);
		if ($n == 0 || $tableNames[0] == "")
			throw new Exception("No copy tables");
		
		foreach ($tableNames as $name)
		{
			$tbs[] = $dbs[$j]->openTable($name, bz\transactd::TD_OPEN_READONLY);
			validateStat($dbs[$j], "open master table " . $name);
		}
	}
	return $tbs;
}

function beginSnapshotWithBinlogPos($db)
{
	$binlog = new bz\binlogPos();
	$binlog = $db->beginSnapshot(bz\transactd::CONSISTENT_READ_WITH_BINLOG_POS);
	validateStat($db, "beginSnapshot");
	return $binlog;
}

function startSlaveUntil($db, $sql)
{
	$db->createTable($sql);
	if ($db->stat() == ER_BAD_SLAVE)
		return false;
	if ($db->stat() && ($db->stat() != ER_BAD_SLAVE))
		throw new Exception("start slave until stat=" . $db->stat());
	return true;
}

function setGtidSlavePosMa($db, $binlog)
{
	$sql = "set global gtid_slave_pos='". $binlog->gtid. "'";
	execSql($db, $sql);
	echo PHP_EOL. "\t" .$sql . "; ";
}

function setGtidSlavePosMy($db, $binlog)
{
	execSql($db, "reset master");
	$sql = "set global gtid_purged='" . $binlog->gtid . "'";
	execSql($db, $sql);
	echo PHP_EOL. "\t" .$sql . "; " ;
}

function changeMaster($db, $param, $binlog)
{
	$sql = getChangeMasterSql($param, $binlog);
	$gtidType = $param["gtid"]["type"];
	if ($gtidType == REP_TYPE_GTID_MA)
		setGtidSlavePosMa($db, $binlog);
	else if ($gtidType == REP_TYPE_GTID_MY)
	{
		if ($param["slave"]["master_resettable"] == 1)
			setGtidSlavePosMy($db, $binlog);
	}
	$db->createTable($sql);
	echo PHP_EOL. "    " .$sql . "; ";
	validateStat($db, "changeMaster");
}

function getSlaveExecPos($recs)
{
	if ($recs->size() > SLAVE_EXEC_MASTER_LOG_POS)
		return $recs[SLAVE_EXEC_MASTER_LOG_POS]->longValue;
	return 0;
}

function getSlaveIOPos($recs)
{
	if ($recs->size() > MASTER_LOG_POS)
		return $recs[MASTER_LOG_POS]->longValue;
	return 0;
}

function isPosBrokn($recs)
{
	$iop = getSlaveIOPos($recs);
	$exp = getSlaveExecPos($recs);
	return $exp > $iop;
}

function isSlaveSqlRunning($recs)
{
	return $recs[SLAVE_SQL_RUNNINGE]->value == "Yes";
}

function isSlaveIoRunning($recs)
{
	return $recs[SLAVE_IO_RUNNINGE]->value == "Yes";
}

function getSlaveIoErrno($recs)
{
	if ($recs->size() > SLAVE_LAST_IO_ERRNO)
		return $recs[SLAVE_LAST_IO_ERRNO]->longValue;
	return 0;
}

function getSlaveSqlError($recs)
{
	if ($recs->size() > SLAVE_LAST_SQL_ERROR)
		return $recs[SLAVE_LAST_SQL_ERROR]->value;
	return "";
}

function getSlaveIoError($recs)
{
	if ($recs->size() > SLAVE_LAST_IO_ERROR)
		return $recs[SLAVE_LAST_IO_ERROR]->value;
	return "";
}

function setSlaveSkipCounter($db)
{
	execSql($db, "set global sql_slave_skip_counter=1");
}

function setSlaveSkipGtid($db, $gtid)
{
	$sql = "set gtid_next = '" . $gtid . "'";
	execSql($db, $sql);
	execSql($db, "start transaction");
	execSql($db, "commit");
	execSql($db, "set gtid_next = automatic");
}

function nextGtid($uuid, $set)
{
	$pos = strrpos ($set, $uuid);
	if ($pos === false) return $uuid.':'.'1';
	$pos2 = strpos ($set, ',', $pos);
	if ($pos2 === false) $pos2 = strlen($set);
	$s = substr($set , $pos, $pos2 - $pos);
	$pos = strpos($s, ':');
	if ($pos === false) return "";
	$pos2 = strpos($s, '-', $pos);
	if ($pos2 !== false)$pos = $pos2;
	$v = (int)substr($s , ++$pos);
	return $uuid.':'.++$v;
}

function getSkipGtid($recs)
{
	if ($recs->size() <= SLAVE_EXECUTED_GTID_SET)
		return "";
	$uuid = $recs[SLAVE_STATUS_MASTER_UUID]->value;
	$set = $recs[SLAVE_EXECUTED_GTID_SET]->value;
	return nextGtid($uuid, $set);
}

function skipSqlError($db, $recs, $binlog, $mysqlGtidMode)
{
	if ($mysqlGtidMode === true)
		setSlaveSkipGtid($db, getSkipGtid($recs));
	else
		setSlaveSkipCounter($db);
	startSlaveUntil($db, getStartSlaveSql($binlog));
	usleep(10000);
}

function resolvBroknError($db, $recs)
{
	echo "\n\n-------------------------" .
		"\nThe execute position is greater than the readed position. Replication is broken.\n".
		"Do you want stop and reset slave ?\n (Y/N) ?";
	$s = trim(fgets(STDIN));
	if ($s === 'y' || $s === 'Y')
	{
		stopSlave($db);
		resetSlave($db);
		return true;
	}
	throw new Exception("Log position error.\nRebuild of replication is required.");
}

function resolvIOError($db, $recs)
{
	echo "\n\n-------------------------" .
		"\nIO thread has error(s)." .
		"\n-------------------------" .
		"\n".getSlaveIoError($recs).
		"\nDo you want stop and reset slave ?\n (Y/N) ?";
	$s = trim(fgets(STDIN));
	if ($s === 'y' || $s === 'Y')
	{
		stopSlave($db);
		resetSlave($db);
		return true;
	}
	throw new Exception("IO thread has error(s).\nPlease retry after remove error(s).");
}

function resolvSqlError($db, $recs, $binlog, $mysqlGtidMode)
{
	echo "\n\n-------------------------" .
		"\nSQL thread has error(s)." .
		"\n-------------------------" .
		"\n". getSlaveSqlError($recs).
		"\nDo you want skip only this error ?".
		"\nY: Skip this error | A: Skip all error | C: Cancel replication\n (Y/A/C) ?" ;
	$s = trim(fgets(STDIN));
	if ($s === 'a' || $s === 'A')
	{
		stopSlave($db);
		resetSlave($db);
		return true;
	}
	else if ($s === 'y' || $s === 'Y')
	{
		skipSqlError($db, $recs, $binlog, $mysqlGtidMode);
		return false;
	}
	throw new Exception("SQL thread has error(s).\nPlease retry after remove error(s).");
}

function slaveSync($db, $mgr, $binlog, $mysqlGtidMode)
{
	while (1)
	{
		$recs = $mgr->slaveStatus();
		validateStat($mgr, "slaveStatus");
		/* In the case of first-time replication, size is zero. */
		if ($recs->size() === 0) return true;
		if (isPosBrokn($recs))
		{
			if (resolvBroknError($db, $recs)) return true;
		}
		$ret = ($recs[MASTER_LOG_FILE]->value === $binlog->filename) && 
				($recs[SLAVE_EXEC_MASTER_LOG_POS]->longValue === $binlog->pos);
		if ($ret === true) return true;
	
		if ((isSlaveIoRunning($recs) === false) && getSlaveIoErrno($recs))
		{
			if (resolvIOError($db, $recs)) return true;
		}
		else if (isSlaveSqlRunning($recs) == false)
		{
			if (resolvSqlError($db, $recs, $binlog, $mysqlGtidMode)) return true;
		}
	}
	return false;
}

function isMysqlGtidModeOn($param, $db, $mgr)
{
	$vers = new bz\btrVersions;
	$db->getBtrVersion($vers);
	validateStat($db, "get slave version");
	$ver = $vers->version(VER_IDX_DB_SERVER);
	if (chr($ver->type) == 'A') return false;
	if ($ver->minorVersion < 6) return false;
	
	//ToDO ver 3.5 or later can auto detect by $mgr.
	return ($param["gtid"]["using_mysql_gtid"] == 1);
}

function waitForSlaveSync($db, $param, $binlog, $mgr)
{
	$startSlaveUntilSql = getStartSlaveSql($binlog);
	$mysqlGtidMode = isMysqlGtidModeOn($param, $db, $mgr);
	if (startSlaveUntil($db, $startSlaveUntilSql))
	{
		$sleveSync = false;
		for ($i = 0; $i < 10; ++$i)
		{
			sleep(1);
			$sleveSync = slaveSync($db, $mgr, $binlog, $mysqlGtidMode);
			if ($sleveSync) break;
		}
		if (!$sleveSync)
			throw new Exception("The slave SQL thread could not be executed until the target of the log position in time");
	}
	stopSlave($db);
}

function assignDbDef($db, $srcDb)
{
	$db->assignSchemaData($srcDb->dbDef());
	validateStat($db, "assignDbDef");
}

function isSchemaTable($name)
{
	return ($name == bz\transactd::TRANSACTD_SCHEMANAME) ||
		(stristr($name, '.bzs') !== false);
}

function removeSchemaRecords($tb)
{
	$tb->seekFirst();
	while ($tb->stat() === 0)
	{
		$tb->del();
		validateStat($tb, "Clear transactd schema record");
		$tb->seekNext();
	}
}

function prepareSlaveTable($db, $name, $sql)
{
	$isSchema = isSchemaTable($name);
	if ($isSchema === false)
	{
		$db->dropTable($name);
		validateStat($db, "dropTable");
	}
	$db->createTable($sql);
	if ($isSchema === false)
		validateStat($db, "createTable");

	$tb = $db->openTable($name);
	validateStat($db, "prepareSlaveTable open");
	$tb->setTimestampMode(bz\transactd::TIMESTAMP_VALUE_CONTROL);
	if ($isSchema === true)
		removeSchemaRecords($tb);
	return $tb;
}

function copyTable($mastr_tb, $db)
{
	$sql = $mastr_tb->getCreateSql();
	$fileName = $mastr_tb->tableDef()->fileName();
	$s = sprintf("    table : %s ... ", $fileName);
	echo $s;
	$slave_tb = prepareSlaveTable($db, $fileName, $sql);
	$useReadMultiRecord = -2;
	$db->copyTableData($slave_tb, $mastr_tb, false, $useReadMultiRecord);
	validateStat($db, "copyTableData");
	echo " done!".PHP_EOL;
}

function prepareSlaveDatabase($param, $dbname)
{
	$db = new bz\database();
	createOpen($db, getSlaveUri($param, $dbname));
	setSqlMode($db);
	$logbin = ($param["slave"]["log_bin"] == 1);
	if ($logbin === false)
		setLogBinOff($db);
	return $db;
}

function getViewNames($param, $dbname)
{
	$mgr = createMasterConnMgr($param);
	$recs = $mgr->views($dbname);
	validateStat($mgr, "viewList");
	$mgr->disconnect();
	unset($mgr);
	$viewNames =  array();
	foreach($recs as $rec)
		$viewNames[] = $rec->name;
	$ignoreTables = getParamArray($param, "master", "ignore_tables");
	return removeIgnores($viewNames, $ignoreTables);
}

function copyViews($slave_db, $master_db, $param)
{
	$dbname = dbnameFromUri($master_db->uri());
	$viewNames = getViewNames($param, $dbname);
	foreach ($viewNames as $viewName)
	{
		$sql = $master_db->getCreateViewSql($viewName);
		validateStat($master_db, "getCreateViewSql : " . $viewName);
		$s = sprintf("    view  : %s ... ", $viewName);
		echo $s;
		$slave_db->execSql("drop view ". $viewName);
		if ($slave_db->stat() != 0 && 
			$slave_db->stat() != MYSQL_ER_UNKNOWN_TABLE_NAME + MYSQL_ERROR_OFFSET)
			validateStat($slave_db, "drop view : ".$viewName);
		execSql($slave_db, $sql);
		echo " done!".PHP_EOL;
	}
}

function copyTables($param, $master_dbs, $master_tables)
{
	echo PHP_EOL;
	$tbaleIndex = 0;
    $isCopyView = (count($master_dbs) > 1) || (count(getParamArray($param, "master", "tables"))== 0); 
	foreach ($master_dbs as $db_master)
	{
		$dbname = dbnameFromUri($db_master->uri());
		$db = prepareSlaveDatabase($param, $dbname);
		echo "  [database : ".$dbname."]".PHP_EOL;
		assignDbDef($db, $db_master);
		$dbdef = $db_master->dbDef();
		for ($j = 1; $j <= $dbdef->tableCount(); ++$j)
		{
			$mastr_tb = $master_tables[$tbaleIndex];
			copyTable($mastr_tb, $db);
			++$tbaleIndex;
		}
		if ($isCopyView) copyViews($db, $db_master, $param);
		$db->close();
		unset($db);
	}
}

function echoSlaveStatusRecord($mgr, $recs, $index)
{
	$result = "";
	if ($recs[$index]->type == 0)
		$result = $recs[$index]->longValue;
	else
		$result = $recs[$index]->value;
	$s = sprintf("%s : %s", $mgr->slaveStatusName($index), $result);
	echo($s . PHP_EOL);
}

function checkSlaveRunning($mgr)
{
	sleep(1);
	$recs = $mgr->slaveStatus();
	validateStat($mgr, "slaveStatus");
	$hasError = false;
	if (isSlaveIoRunning($recs) === false)
	{
		$hasError = true;
		echo "Slave_IO_Running = No. Please check the following error.". PHP_EOL;
		echoSlaveStatusRecord($mgr, $recs, SLAVE_LAST_IO_ERRNO);
		echoSlaveStatusRecord($mgr, $recs, SLAVE_LAST_IO_ERROR);
	}else
		echo "Slave_IO_Running = Yes". PHP_EOL;
	if (isSlaveSqlRunning($recs) === false)
	{
		$hasError = true;
		echo "Slave_SQL_Running = No. Please check the following error.". PHP_EOL;
		echoSlaveStatusRecord($mgr, $recs, SLAVE_LAST_SQL_ERRNO);
		echoSlaveStatusRecord($mgr, $recs, SLAVE_LAST_SQL_ERROR);
	}else
		echo "Slave_SQL_Running = Yes". PHP_EOL;
	return ($hasError == false);
}

function progressMsg($msg, $done = true)
{
	if ($done) echo " done!".PHP_EOL;
	if ($msg != "") echo $msg . " ... ";
}

function passwordPrompt($titile)
{
	echo $titile.' password:';
	return trim(fgets(STDIN));
}

function checkPassword(&$param)
{
	if ($param["master"]["passwd"] === '*')
		$param["master"]["passwd"] = passwordPrompt("The master transactd user");
	if ($param["master"]["repl_passwd"] === '*')
		$param["master"]["repl_passwd"] = passwordPrompt("The master replication user");
	if ($param["slave"]["passwd"] === '*')
		$param["slave"]["passwd"] = passwordPrompt("The slave transactd user");
}

function main($argc, $argv)
{
	try
	{
		echo "Nonlocking replcopy version ".DYNAMIC_REPLCOPY_VERSION.PHP_EOL;
		if (count($argv) < 2)
		{
			echo "Please specify replication config file to arg1.";
			return 1;
		}
		echo "--- Start replication setup  ---".PHP_EOL;
		
		$param = parse_ini_file($argv[1], true);
		checkPassword($param);
		bz\database::setCompatibleMode(bz\database::CMP_MODE_MYSQL_NULL);
		$db_master = new bz\database();
		$db_slave = new bz\database();
		
		progressMsg("Open slave database", false);
		openSlaveDb($db_slave, $param);
		
		$mgr = createSlaveConnMgr($param);
		
		progressMsg("Stop slave");
		stopSlave($db_slave);
		
		progressMsg("Open master database");
		$master_dbs = openMasterDb($db_master, $param);
		
		progressMsg("Open master tables");
		$master_tables = openMasterTables($master_dbs, $param);
		
		progressMsg("Begin snapshot on master");
		$binlog = beginSnapshotWithBinlogPos($master_dbs[0]);
		
		try // Exception safety for endSnapshot()
		{
			progressMsg("Wait for stop slave until binlog pos");
			waitForSlaveSync($db_slave, $param, $binlog, $mgr);
			
			progressMsg("Copying tables");
			copyTables($param, $master_dbs, $master_tables, $mgr);
			$master_dbs[0]->endSnapshot();
		}
		catch(Execption $e)
		{
			$master_dbs[0]->endSnapshot();
			throw $e;
		}
		progressMsg("Reset slave", false);
		resetSlave($db_slave);
		
		progressMsg("Change master");
		changeMaster($db_slave, $param, $binlog);
		
		progressMsg("Start slave");
		startSlave($db_slave);
		progressMsg("");
		
		if (checkSlaveRunning($mgr))
			echo "--- Replication setup has been completed ---".PHP_EOL;
		else
			echo "--- Replication setup has an error ---".PHP_EOL;
		return 0;
	}
	
	catch (Exception $e)
	{
		$msg = $e->getMessage();
		if ($msg != "") print($msg.PHP_EOL);
		echo PHP_EOL."--- Replication setup has an error  ---".PHP_EOL;
	}
	return 1;
}

return main($argc, $argv);
