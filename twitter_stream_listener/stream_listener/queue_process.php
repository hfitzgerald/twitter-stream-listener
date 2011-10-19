<?php
require_once('lib/idiorm/idiorm.php');
require_once('lib/twitter_oauth/twitteroauth.php');
require_once('config.php');
require_once('lib/google_url/google_url.php');
require_once('lib/daemon/System/Daemon.php');

class QueueProcessor {
  
  /**
   * Member attribs
   */
  protected $queueDir;
  protected $filePattern;
  protected $checkInterval;
  /**
   * Construct the consumer and start processing
   */
  public function __construct($queueDir = '/tmp', $filePattern = 'userstream.*.queue', $checkInterval = 10)
  {
    $this->queueDir = $queueDir;
    $this->filePattern = $filePattern;
    $this->checkInterval = $checkInterval;
    
    // Sanity checks
    if (!is_dir($queueDir)) {
      throw new ErrorException('Invalid directory: ' . $queueDir);
    }
    

  }
  
  /**
   * Method that actually starts the processing task (never returns).
   */
	public function process() {
	    // Get a list of queue files
	    $queueFiles = glob($this->queueDir . '/' . $this->filePattern);     
	    System_Daemon::log(System_Daemon::LOG_INFO, 'Found ' . count($queueFiles) . ' queue files to process...');
    
	    // Iterate over each file (if any)
	    foreach ($queueFiles as $queueFile) {
	      $this->processQueueFile($queueFile);
	    }
	}
  
  /**
   * Processes a queue file and does something with it (example only)
   * @param string $queueFile The queue file
   */
  protected function processQueueFile($queueFile) {
    System_Daemon::log(System_Daemon::LOG_INFO, 'Processing file: ' . $queueFile);
    
    // Open file
    $fp = fopen($queueFile, 'r');
    
    // Check if something has gone wrong, or perhaps the file is just locked by another process
    if (!is_resource($fp)) {
      System_Daemon::log(System_Daemon::LOG_INFO, 'WARN: Unable to open file or file already open: ' . $queueFile . ' - Skipping.');
      return FALSE;
    }
    
    // Lock file
    flock($fp, LOCK_EX);
    
    // Loop over each line (1 line per status)
    $followsCounter = 0;
    while ($rawStatus = fgets($fp, 8192)) {
      
      
      /**
       * Figure out if this is a new friend request
       */

      $data = json_decode($rawStatus, true);
      if (is_array($data)){
		if(array_key_exists('event', $data)) {
			if($data['event'] === 'follow'){
	 			$this->addFollower($data['source']);
        		System_Daemon::log(System_Daemon::LOG_INFO, var_export($data));
				$followsCounter ++;
			}
		}
     }
      
    } // End while
    
    // Release lock and close
    flock($fp, LOCK_UN);
    fclose($fp);
    
    // All done with this file
    System_Daemon::log(System_Daemon::LOG_INFO, 'Successfully processed ' . $followsCounter . ' follows ' . $queueFile . ' - deleting.');
    unlink($queueFile);
    
  }
 
  /**
   * Add a new follower to the table
   **/
	protected function addFollower($data){
		/**
		 * Set up database connection
		 */
		ORM::configure('mysql:host=localhost;dbname='.DATABASE_TABLE);
		ORM::configure('username', DATABASE_USERNAME);
		ORM::configure('password', DATABASE_PASSWORD);
		
		if($data['screen_name'] == TWITTER_USERNAME) return; // TODO change to production twitter handle 
		$follow_code = md5($data['screen_name']);
		$follower = ORM::for_table('followers')->where('twitter_id', $data['id'])->find_one();
		
		if($follower == false){
			$follower = ORM::for_table('followers')->create();
			$follower->twitter_id = $data['id'];
			$follower->username = $data['screen_name'];
			$follower->follow_code = $follow_code;
		}
		
		$follower->created = date('Y-m-d H:i:s', time());
		$follower->save();
		
		/**
		 * Send Direct Message
		 **/
		$google = new GoogleURL(GOOGLE_API_KEY);	
		$url = $google->shorten(WEB_APP_BASE_URL . "?follow_code=$follow_code");
		$message = "Thanks for following, please visit ". $url ." within the hour to verify your age or you'll be blocked.";
		$connection = new TwitterOAuth(TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET, OAUTH_TOKEN, OAUTH_SECRET);		
		$message_resp = $connection->post('direct_messages/new', array('user_id' => $data['id'], 'text' => $message));
		
		System_Daemon::log(System_Daemon::LOG_INFO, "New request to follow, direct message http code resp was " . $connection->http_code);
		// get rid of db connection
		unset($follower); // get rid of db connection			
	}
}