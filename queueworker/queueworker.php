<?php

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Route;
use Zotlabs\Extend\Hook;

/**
 * Name: queueworker
 * Description: EXPERIMENTAL Queue work queue for backgrounded tasks
 * Version: 0.8.0
 * Author: Matthew Dent <dentm42@dm42.net>
 * MinVersion: 4.2.1
 */

class QueueWorkerUtils {

 	public static $queueworker_version = '0.8.0';
	public static $hubzilla_minver = '4.2.1';
	public static $queueworker_dbver = 1;
	public static $queueworker = null;
	public static $maxworkers = 0;
	public static $workermaxage = 0;
	public static $workersleep = 100;

  	public static function check_min_version ($platform,$minver) {
      		switch ($platform) {
          		case 'hubzilla':
              		$curver = STD_VERSION;
              		break;
          	case 'queueworker':
              		$curver = QueueWorkerUtils::$queueworker_version;
              		break;
          		default:
              		return false;
      		}

    		$checkver = explode ('.',$minver);
    		$ver = explode ('.',$curver);

    		$major = (intval($checkver[0]) <= intval($ver[0]));
    		$minor = (intval($checkver[1]) <= intval($ver[1]));
    		$patch = (intval($checkver[2]) <= intval($ver[2]));

    		if ($major && $minor && $patch) {
         		return true;
    		} else {
         		return false;
    		}
    
  	}

	static public function dbCleanup () {

		$success=UPDATE_SUCCESS;

		$sqlstmts[DBTYPE_MYSQL] = Array (
	    		1 => Array (
				"DROP TABLE IF EXISTS workerq;"
	    		)
        	);
        	$sqlstmts[DBTYPE_POSTGRES] = Array (
	   		1 => Array (
				"DROP TABLE IF EXISTS workerq;"
	   		)
		);
        	$dbsql=$sqlstmts[ACTIVE_DBTYPE];
		foreach ($dbsql as $updatever=>$sql) {
	  		foreach ($sql as $query) {
		  		$r = q($query);
		  		if (!$r) {
			  		logger ('Error running dbCleanup. sql query failed: '.$query,LOGGER_NORMAL);
			  		$success = UPDATE_FAILED;
		  		}
	  		}
		}
		if ($success==UPDATE_SUCCESS) {
	  		logger ('dbCleanup successful.',LOGGER_NORMAL);
	  		self::delsysconfig("dbver");
  		} else {
	  		logger ('Error in dbCleanup.',LOGGER_NORMAL);
		}
			return $success;
	}

	static public function dbUpgrade () {
		$dbverconfig = self::getsysconfig("dbver");

		$dbver = $dbverconfig ? $dbverconfig : 0;

		$dbsql[DBTYPE_MYSQL] = Array (
			1 => Array (
				"CREATE TABLE IF NOT EXISTS workerq (
					workerq_id BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					workerq_priority smallint,
					workerq_reservationid varchar(25) DEFAULT NULL,
					workerq_processtimeout datetime NOT NULL DEFAULT '0001-01-01 00:00:00',
					workerq_data text,
					KEY `workerq_priority` (`workerq_priority`),
					KEY `workerq_reservationid` (`workerq_reservationid`),
					KEY `workerq_processtimeout` (`workerq_processtimeout`)
					) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
				"
			)
		);
	
		$dbsql[DBTYPE_POSTGRES] = Array (
			1 => Array (
				"CREATE TABLE IF NOT EXISTS workerq (
					workerq_id bigserial NOT NULL,
					workerq_priority smallint,
					workerq_reservationid varchar(25) DEFAULT NULL,
					workerq_processtimeout timestamp NOT NULL DEFAULT '0001-01-01 00:00:00',
					workerq_data text,
					PRIMARY KEY (workerq_id)
					);
				",
				"CREATE INDEX idx_workerq_priority ON workerq (workerq_priority);",
				"CREATE INDEX idx_workerq_reservationid ON workerq (workerq_reservationid);",
				"CREATE INDEX idx_workerq_processtimeout ON workerq (workerq_processtimeout);"
			)
		);
	
		foreach ($dbsql[ACTIVE_DBTYPE] as $ver => $sql) {
			if ($ver <= $dbver) {
				continue;
			}
			foreach ($sql as $query) {
				$r = q($query);
				if (!$r) {
					logger ('dbUpgrade/Install Error (query): '.$query,LOGGER_NORMAL);
					return UPDATE_FAILED;
				}
			}
			self::setsysconfig("dbver",$ver);
		}
        	return UPDATE_SUCCESS;
	}

	static private function maybeunjson ($value) {

    		if (is_array($value)) {
        		return $value;
    		}

    		if ($value!=null) {
        		$decoded=json_decode($value,true);
    		} else {
        		return null;
    		}

    		if (json_last_error() == JSON_ERROR_NONE) {
        		return ($decoded);
    		} else {
        		return ($value);
    		}
	}

	static private function maybejson ($value,$options=0) {

    		if ($value!=null) {
        		if (!is_array($value)) {
            		$decoded=json_decode($value,true);
        		}
    		} else {
        		return null;
    		}

    		if (is_array($value) || json_last_error() != JSON_ERROR_NONE) {
                	$encoded = json_encode($value,$options);
        		return ($encoded);
    		} else {
        		return ($value);
    		}
	}

	static public function checkver() {
		if (QueueWorkerUtils::getsysconfig("appver") == self::$queueworker_version) {
			return true;
		}

		QueueWorkerUtils::setsysconfig("status","version-mismatch");
		return false;
	}

	static public function getsysconfig($param) {
		$val = get_config("queueworker",$param);
		$val=QueueWorkerUtils::maybeunjson($val);
		return $val;
	}

	static public function setsysconfig($param,$val) {
	  	$val=QueueWorkerUtils::maybejson($val);
		return set_config("queueworker",$param,$val);
	}

	static public function delsysconfig($param) {
		return del_config("queueworker",$param);
	}

	private static function qbegin($tablename) {
		switch (ACTIVE_DBTYPE) {
			case DBTYPE_MYSQL:
				q('BEGIN');
				q('LOCK TABLE '.$tablename.' WRITE');
				break;

			case DBTYPE_POSTGRESQL:
				q('BEGIN');
				q('LOCK TABLE '.$tablename.' IN ACCESS EXCLUSIVE MODE');
				break;
		}
		return;
	}

	private static function qcommit() {
		switch (ACTIVE_DBTYPE) {
			case DBTYPE_MYSQL:
				q("UNLOCK TABLES");
				q("COMMIT");
				break;

			case DBTYPE_POSTGRESQL:
				q("COMMIT");
				break;
		}
		return;

	}
	private static function qrollback() {
		switch (ACTIVE_DBTYPE) {
			case DBTYPE_MYSQL:
				q("ROLLBACK");
				q("UNLOCK TABLES");
				break;

			case DBTYPE_POSTGRESQL:
				q("ROLLBACK");
				break;
		}
		return;

	}


	public static function MasterSummon(&$arr) {
		
		$argv=$arr['argv'];
		$argc=count($argv);
		if ($argv[0]!='Queueworker') {

			$priority = 0; //Default priority @TODO allow reprioritization

			$workinfo = ['argc'=>$argc,'argv'=>$argv];
		
        		$r = q("select * from workerq where workerq_data = '%s'",
                		dbesc(self::maybejson($workerinfo)));
        		if ($r) {
                		logger("Ignoring duplicate workerq task",LOGGER_DEBUG);
                		return;
        		}
	
			self::qbegin('workerq');
			$r = q("insert into workerq (workerq_priority,workerq_data) values (%d,'%s')",
				intval($priority),
				dbesc(self::maybejson($workinfo)));
			self::qcommit();
			if (!$r) {
				logger("INSERT FAILED",LOGGER_DEBUG);
				return;
			}
			logger('INSERTED: '.self::maybejson($workinfo),LOGGER_DEBUG);
		}
		$argv=[];
		$arr=['argv'=>$argv];
		$workers=self::GetWorkerCount();
		if ($workers < self::$maxworkers) {
			logger("Less than max active workers ($workers) max = ".self::$maxworkers.".",LOGGER_DEBUG);
			$phpbin = get_config('system','phpbin','php');
			proc_run($phpbin,'Zotlabs/Daemon/Master.php',['Queueworker']);
		}
	}

	public static function MasterRelease(&$arr) {
		
		$argv=$arr['argv'];
		$argc=count($argv);
		if ($argv[0] != 'Queueworker') {
			$priority = 0; //Default priority @TODO allow reprioritization

			$workinfo = ['argc'=>$argc,'argv'=>$argv];
		
        		$r = q("select * from workerq where workerq_data = '%s'",
                		dbesc(self::maybejson($workerinfo)));
        		if ($r) {
                		logger("Ignoring duplicate workerq task",LOGGER_DEBUG);
                		return;
        		}

			self::qbegin('workerq');
			$r = q("insert into workerq (workerq_priority,workerq_data) values (%d,'%s')",
				intval($priority),
				dbesc(self::maybejson($workinfo)));
			self::qcommit();
			if (!$r) {
				logger("Insert failed: ".json_encode($workinfo),LOGGER_DEBUG);
				return;
			}
			logger('INSERTED: '.self::maybejson($workinfo),LOGGER_DEBUG);
		}
		$argv=[];
		$arr=['argv'=>$argv];
                self::Process();
	}

	static public function GetWorkerCount() {
		if (self::$maxworkers == 0) {
			self::$maxworkers = get_config('queueworker','max_queueworkers',4);
			self::$maxworkers = (self::$maxworkers > 3) ? self::$maxworkers : 4;
		}
		if (self::$workermaxage == 0) {
			self::$workermaxage = get_config('queueworker','max_queueworker_age');
			self::$workermaxage = (self::$workermaxage > 120) ? self::$workermaxage : 300;
		}
		q("update workerq set workerq_reservationid = null where workerq_processtimeout < %s", db_utcnow());
		usleep(self::$workersleep); 
		$workers = q("select distinct workerq_reservationid from workerq");
		$workers = isset($workers) ? intval(count($workers)) : 1;
		logger("WORKERCOUNT: $workers",LOGGER_DEBUG);
		return intval($workers);
	}

	static public function GetWorkerID() {
		if (self::$queueworker) {
			return self::$queueworker;
		}

	
		$wid = uniqid('',true);
		usleep(mt_rand(500000,3000000)); //Sleep .5 - 3 seconds before creating a new worker.
		$workers = self::GetWorkerCount();
		if ($workers >= self::$maxworkers) {
			logger("Too many active workers ($workers) max = ".self::$maxworkers,LOGGER_DEBUG);
			return false;
		}
		
		self::$queueworker = $wid;
		
		return $wid;
        }

	static private function getworkid() {
		self::GetWorkerCount();

		self::qbegin('workerq');
		$work = q("select workerq_id from workerq 
				where workerq_reservationid is null
				order by workerq_priority,workerq_id limit 1;");

		if (!$work) {
			self::qrollback();
			return false;
		}

		$id = $work[0]['workerq_id'];
                $work = q("update workerq set workerq_reservationid='%s', workerq_processtimeout = %s + interval %s where workerq_id = %d",
                        self::$queueworker,
			db_utcnow(),
			db_quoteinterval(self::$workermaxage." SECOND"),
                        intval($id));

		if (!$work) {
			self::qrollback();
			logger("Could not update workerq.",LOGGER_DEBUG);
			return false;
		}
		logger("GOTWORK: ".json_encode($work), LOGGER_DEBUG);
		self::qcommit();
		return $id;
	}

        static public function Process() {
                self::$workersleep = get_config('queueworker','queue_worker_sleep');
                self::$workersleep = (intval($workersleep) > 100) ? intval($workersleep) : 100;
		
                if (!self::GetWorkerID()) {
                        logger('Unable to get worker ID. Exiting.',LOGGER_DEBUG);
                        killme();
                }

                $jobs = 0;
		$workid = self::getworkid();
                while ($workid) {
                        sleep ($workersleep); 
			// @FIXME:  Currently $workersleep is a fixed value.  It may be a good idea
			//          to implement a "backoff" instead - based on load average or some
			//	    other metric.

			self::qbegin('workerq');
                        $workitem = q("select * from workerq where workerq_id = %d",
				$workid);
			self::qcommit();

                        if (isset($workitem[0])) {

				// At least SOME work to do.... in case there's more, let's ramp up workers.
				$workers=self::GetWorkerCount();
				if ($workers < self::$maxworkers) {
					logger("Less than max active workers ($workers) max = ".self::$maxworkers.".",LOGGER_DEBUG);
					$phpbin = get_config('system','phpbin','php');
					proc_run($phpbin,'Zotlabs/Daemon/Master.php',['Queueworker']);
				}

                                $jobs++;
                                $workinfo = self::maybeunjson($workitem[0]['workerq_data']);
                                $argc = $workinfo['argc'];
                                $argv = $workinfo['argv'];
                                logger('Master: process: ' . json_encode($argv),LOGGER_DEBUG);

                                $cls = '\\Zotlabs\\Daemon\\' . $argv[0];
                                $cls::run($argc,$argv);
                                //@FIXME: Right now we assume that if we get a return, everything is OK.
                                //At some point we may want to test whether the run returns true/false
                                //    and requeue the work to be tried again if needed.  But we probably want
                                //    to implement some sort of "retry interval" first.
			
				self::qbegin('workerq');

                                q("delete from workerq where workerq_id = %d",
                                        $workid);
				self::qcommit();
                        } else {
				logger("NO WORKITEM!",LOGGER_DEBUG);
                        }
			$workid = self::getworkid();
                }
                logger('Master: Worker Thread: queue items processed:' . $jobs,LOGGER_DEBUG);
                del_config('queueworkers','workerstarted_'.self::$queueworker);
        }

	static public function ClearQueue() {
                $work = q("select * from workerq");
		while ($work) {
                	foreach ($work as $workitem) {
                        	$workinfo = self::maybeunjson($workitem['v']);
                        	$argc = $workinfo['argc'];
                        	$argv = $workinfo['argv'];
                        	logger('Master: process: ' . print_r($argv,true), LOGGER_ALL,LOG_DEBUG);
				if (!isset($argv[0])) {
					q("delete from workerq where workerq_id = %d",
						$work[0]['workerq_id']);
					continue;
				}
                        	$cls = '\\Zotlabs\\Daemon\\' . $argv[0];
                        	$cls::run($argc,$argv);
				q("delete from workerq where workerq_id = %d",
					$work[0]['workerq_id']);
				usleep(300000);
				//Give the server .3 seconds to catch its breath between tasks.  
				//This will hopefully keep it from crashing to it's knees entirely
				//if the last task ended up initiating other parallel processes 
				//(eg. polling remotes)
                	}
			//Make sure nothing new came in
                	$work = q("select * from workerq");
		}
		return;
	}
	static public function uninstall() {
  		logger ('Uninstall start.');
		//Prevent new work form being added.
		Hook::unregister('daemon_master_release',__FILE__,'QueueWorkerUtils::MasterRelease');
		QueueWorkerUtils::ClearQueue();
        	QueueWorkerUtils::dbCleanup();

		QueueWorkerUtils::delsysconfig("appver");
		QueueWorkerUtils::setsysconfig("status","uninstalled");
		notice ('QueueWorker Uninstalled.'.EOL);
		logger ('Uninstalled.');
		return;
	}

	static public function install() {
		logger ('Install start.');
		if (QueueWorkerUtils::dbUpgrade () == UPDATE_FAILED) {
			notice ('QueueWorker Install error - Abort installation.'.EOL);
			logger ('Install error - Abort installation.');
			QueueWorkerUtils::setsysconfig("status","install error");
			return;
		}
		notice ('QueueWorker Installed successfully.'.EOL);
		logger ('QueueWorker Installed successfully.',LOGGER_NORMAL);
		QueueWorkerUtils::setsysconfig("appver",self::$queueworker_version);
		QueueWorkerUtils::setsysconfig("status","ready");
	}
}

function queueworker_install() {
	QueueWorkerUtils::install();
}

function queueworker_uninstall() {
	QueueWorkerUtils::uninstall();
}

function queueworker_load(){
        // HOOK REGISTRATION
	Hook::register('daemon_master_release',__FILE__,'QueueWorkerUtils::MasterRelease',1,0);
	Hook::register('daemon_master_summon',__FILE__,'QueueWorkerUtils::MasterSummon',1,0);

	Route::register(dirname(__FILE__).'/Mod_Queueworker.php','queueworker');

	QueueWorkerUtils::dbupgrade();
}

function queueworker_unload(){
	Hook::unregister_by_file(__FILE__);
	Route::unregister_by_file(dirname(__FILE__).'/Mod_Queueworker.php');

}
