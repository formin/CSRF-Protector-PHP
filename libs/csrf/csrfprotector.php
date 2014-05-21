<?php

//name of HTTP POST variable for authentication
define("CSRFP_POST","CSRFPROTECTOR_AUTH_TOKEN");

/**
 * child exception classes
 */
class configFileNotFoundException extends \exception {};
class logDirectoryNotFoundException extends \exception {};
class jsFileNotFoundException extends \exception {};
class logFileWriteError extends \exception {};

class csrfProtector
{
	/**
	 * Name of the token sent to client as cookie and
	 * sent from client as post
	 * @var string
	 */
	public static $tokenName = 'CSRF_AUTH_TOKEN';	//NOTE: DO NOT CHANGE THIS

	/**
	 * expiry time for cookie
	 * @var int
	 */
	public static $cookieExpiryTime = 300;	//5 minutes

	/**
	 * flag for cross origin/same origin request
	 * @var bool
	 */
	private static $isSameOrigin = true;

	/**
	 * flag to check if output file is a valid HTML or not
	 * @var bool
	 */
	private static $isValidHTML = false;

	/**
	 * Varaible to store weather request type is post or get
	 * @var string
	 */
	private static $requestType = "GET";

	/**
	 * config file for CSRFProtector
	 * @var int Array, length = 6
	 * @property #1: failedAuthAction (int) => action to be taken in case autherisation fails
	 * @property #2: logDirectory (string) => directory in which log will be saved
	 * @property #3: customErrorMessage (string) => custom error message to be sent in case
	 *						of failed authentication
	 * @property #4: jsFile (string) => location of the CSRFProtector js file
	 * @property #5: tokenLength (int) => default length of hash
	 * @property #6: disabledJavascriptMessage (string) => error message if client's js is disabled
	 */
	public static $config = array();

	/**
	 * function to initialise the csrfProtector work flow
	 * @parameters: variables to override default configuration loaded from file
	 * @param $isGETEnabled - boolean variable to set GET request validation for a specific page
	 * @param $length - length of CSRF_AUTH_TOKEN to be generated
	 * @param $action - int, for different actions to be taken in case of failed validation
	 * @return void
	 * @throw configFileNotFoundException			
	 */
	public static function init($isGETEnabled = false, $length = null, $action = null)
	{
		if (!file_exists(__DIR__ ."/../config.php")) {
			throw new configFileNotFoundException("configuration file not found for CSRFProtector!");	
		}

		//load configuration file and properties
		self::$config = include(__DIR__ ."/../config.php");

		//overriding isGETEnabled property
		if ($isGETEnabled === true) {
			self::$config['isGETEnabled'] = true;
		}

		//overriding length property if passed in parameters
		if ($length !== null) {
			self::$config['tokenLength'] = intval($length);
		}
		
		//action that is needed to be taken in case of failed authorisation
		if ($action !== null) {
			self::$config['failedAuthAction'] = intval($action);
		}	

		//authorise the incoming request
		self::authorisePost();

		// Initialize output buffering handler
		ob_start('csrfProtector::ob_handler');
	}

	/**
	 * function to authorise incoming post requests
	 * @param void
	 * @return void
	 * @throw logDirectoryNotFoundException
	 */
	public static function authorisePost()
	{
		//#todo this method is valid for same origin request only,
		//#todo take required action if self::$config['isGETEnabled'] is true 
		//enable it for cross origin also sometime
		//for cross origin the functionality is different
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {

			//set request type to POST
			self::$requestType = "POST";

			//currently for same origin only
			if (!(isset($_POST[CSRFP_POST]) 
				&& isset($_COOKIE[self::$tokenName])
				&& ($_POST[CSRFP_POST] === $_COOKIE[self::$tokenName])
				)) {

				//action in case of failed validation
				self::failedValidationAction();			
			}
		} else if (self::$config['isGETEnabled']) {
			
			//currently for same origin only
			if (!(isset($_GET[CSRFP_POST]) 
				&& isset($_COOKIE[self::$tokenName])
				&& ($_GET[CSRFP_POST] === $_COOKIE[self::$tokenName])
				)) {

				//action in case of failed validation
				self::failedValidationAction();			
			}
		}

		/**
		 * Refresh cookie for each request
		 */
		self::setCookie();	
	}

	/**
	 * function to be called in case of failed validation
	 * performs logging and take appropriate action
	 * @param: void
	 * @return: void
	 */
	private static function failedValidationAction()
	{
		if (!file_exists(__DIR__ ."/../" .self::$config['logDirectory'])) {
			throw new logDirectoryNotFoundException("Log Directory Not Found!");		
		}
	
		//call the logging function
		self::logCSRFattack();

		//#todo: ask mentors if $failedAuthAction is better as an int or string
		//default case is case 0
		switch (self::$config['failedAuthAction']) {
			case 0:
				//send 403 header
				header('HTTP/1.0 403 Forbidden');
				exit("<h2>403 Access Forbidden by CSRFProtector!</h2>");
				break;
			case 1:
				//unset the query parameters and forward
				if (self::$requestType === "GET") {
					unset($_GET);
				} else {
					unset($_POST);
				}
				break;
			case 2:
				//redirect to custom error page
				header("location: self::$config[errorRedirectionPage]");
				exit;
			case 3:
				//send custom error message
				exit(self::$config['customErrorMessage']);
				break;
			case 4:
				//send 500 header -- internal server error
				header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
				exit("<h2>500 Internal Server Error!</h2>");
				break;
			default:
				//unset the query parameters and forward
				if (self::$requestType === "GET") {
				unset($_GET);
				} else {
					unset($_POST);
				}
				break;
		}		
	}

	/**
	 * function to set auth cookie 
	 * @param: void
	 * @return void
	 */
	public static function setCookie()
	{
		setcookie(self::$tokenName, 
			self::generateAuthToken(), 
			time() + self::$cookieExpiryTime);
	}

	/**
	 * function to generate random hash of length as given in parameter
	 * max length = 128
	 * @param: length to hash required, int
	 * @return string
	 */
	public static function generateAuthToken()
	{
		//if config tokenLength value is 0 or some non int
		if (intval(self::$config['tokenLength']) === 0) {
			self::$config['tokenLength'] = 32;	//set as default
		}

		//if $length > 128 throw exception #todo 

		if (function_exists("hash_algos") && in_array("sha512", hash_algos())) {
			$token = hash("sha512", mt_rand(0, mt_getrandmax()));
		} else {
			$token = '';
			for ($i = 0; $i < 128; ++$i) {
				$r = mt_rand(0, 35);
				if ($r < 26) {
					$c = chr(ord('a') + $r);
				} else { 
					$c = chr(ord('0') + $r - 26);
				}
				$token .= $c;
			}
		}
		return substr($token, 0, self::$config['tokenLength']);
	}

	/**
	 * Rewrites <form> on the fly to add CSRF tokens to them. This can also
	 * inject our JavaScript library.
	 * @param: $buffer, output buffer to which all output are stored
	 * @param: flag
	 * @return string, complete output buffer
	 */
	public static function ob_handler($buffer, $flags)
	{
		// Even though the user told us to rewrite, we should do a quick heuristic
	    // to check if the page is *actually* HTML. We don't begin rewriting until
	    // we hit the first <html tag.
	    if (!self::$isValidHTML) {
	        // not HTML until proven otherwise
	        if (stripos($buffer, '<html') !== false) {
	            self::$isValidHTML = true;
	        } else {
	            return $buffer;
	        }
	    }
		
	    /*
	    //you can add code to check if js file exists
	    if (!file_exists(self::$config['jsFile'])) {
	        $buffer = "CSRFProtector js file not found at " .self::$config['jsFile'] ." in " 
	        .__FILE__ ." on line " .__LINE__;
	        return $buffer;
	    }
	    */
	    
	    //add a <noscript> message to outgoing HTML output,
	    //informing the user to enable js for CSRFProtector to work
	    //best section to add, after <body> tag
	    $buffer = preg_replace("/<body(.*)>/", "$0 <noscript>" .self::$config['disabledJavascriptMessage'] .
	    	"</noscript>", $buffer);


	    $script = '<script type="text/javascript" src="' .self::$config['jsFile'] .'"></script>';	

	    //implant the CSRFGuard js file to outgoing script
	    $buffer = str_ireplace('</body>', $script . '</body>', $buffer, $count);
	    if (!$count) {
	        $buffer .= $script;
	    }

	    return $buffer;
	}

	/**
	 * Functio to log CSRF Attack
	 * @param: void
	 * @retrun: void
	 * @throw: logFileWriteError
	 */
	private static function logCSRFattack()
	{
		//if file doesnot exist for, create it
		$logFile = fopen(__DIR__ ."/../" .self::$config['logDirectory']
		."/" .self::getCurrentLogFileName(), "a+");
		
		//throw exception if above fopen fails
		if (!$logFile) {
			throw new logFileWriteError("Unable to write to the log file");	
		}

		//miniature version of the log
		$log = array();
		$log['timestamp'] = time();
		$log['HOST'] = $_SERVER['HTTP_HOST'];
		$log['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
		$log['requestType'] = self::$requestType;

		if (self::$requestType === "GET") {
			$log['query'] = $_GET;
		} else {
			$log['query'] = $_POST;
		}

		$log['cookie'] = $_COOKIE;

		//convert log array to JSON format to be logged
		$log = json_encode($log) .PHP_EOL;

		//append log to the file
		fwrite($logFile, $log);

		//close the file handler
		fclose($logFile);
	}

	/**
	 * function to return filename of log file for current date
	 * @param: void
	 * @return: string, filename
	 */
	private static function getCurrentLogFileName()
	{
		return date("m-20y") .".log";
	}

};