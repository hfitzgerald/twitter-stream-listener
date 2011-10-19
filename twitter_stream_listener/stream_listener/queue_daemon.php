#!/usr/bin/php -q
<?php
/**
 * System_Daemon turns PHP-CLI scripts into daemons.
 * 
 * PHP version 5
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @link      http://github.com/kvz/system_daemon
 */

/**
 * System_Daemon Example Code
 * 
 * If you run this code successfully, a daemon will be spawned
 * and stopped directly. You should find a log enty in 
 * /var/log/simple.log
 * 
 */

// Include Class
error_reporting(E_ALL);
require_once('lib/daemon/System/Daemon.php');
require_once('queue_process.php');
require_once('config.php');

// Bare minimum setup
System_Daemon::setOption("appName", "FriendConsumerDaemon");
System_Daemon::setOption("authorEmail", "hfitzgerald@bfgcom.com");
System_Daemon::setOption("usePEAR", false);
System_Daemon::setOption("appDir", dirname(__FILE__));

System_Daemon::log(System_Daemon::LOG_INFO, "Daemon not yet started so ".
    "this will be written on-screen");

// Spawn Deamon!
System_Daemon::start();
System_Daemon::log(System_Daemon::LOG_INFO, "Daemon: '".
    System_Daemon::getOption("appName").
    "' spawned! This will be written to ".
    System_Daemon::getOption("logLocation"));

// Your normal PHP code goes here. Only the code will run in the background
// so you can close your terminal session, and the application will
// still run.

$qp = new QueueProcessor();  
$lastCheck = 0;

// This variable gives your own code the ability to breakdown the daemon:
$runningOkay = true;

// While checks on 3 things in this case:
// - That the Daemon Class hasn't reported it's dying
// - That your own code has been running Okay
// - That we're not executing more than 3 runs
while (!System_Daemon::isDying() && $runningOkay) {	
	$lastCheck = time();
	$qp->process();

	// Wait until ready for next check
	while (time() - $lastCheck < 5) {	
		System_Daemon::iterate(2);
	}
} // Infinite loop

System_Daemon::stop();