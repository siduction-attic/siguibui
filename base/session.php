<?php
/**
 * Stores session specific data.
 * @author hm
 */
define('TRACE_ALL', '*');

define('TRACE_URL', 'u');
define('TRACE_FINE', '+');
define('TRACE_RARE', '-');
define('TRACE_CONFIG', 'c');
define('OPTION_SELECTED', ' selected="selected"');
define('CONFIG_SEPARATOR', ';');
define('OPT_SEPARATOR', ';');
define('SVOPT_BACKGROUND', 'background');
define('SVOPT_SOURCE', 'source');
define('SVOPT_DEFAULT', 'std');

/**
 * Stores the session data and has helper methods for general usage.
 *
 * @author hm
 */
class Session{
	/// Document root: The directory containing the script file, e.g. /home/www/abc/
	var $homeDir;
	/// The iso name of the language, e.g. de
	var $language;
	/// Debugging: define the trace level.
	var $traceFlag;
	/// Debugging: the name of the trace file
	var $traceFile;
	/// Debugging: the file resource
	var $traceFp;
	/// with local path, e.g. /home/www/abc/index.php
	var $scriptFile;
	/// e.g. sidu-installer
	var $domain;
	/// e.g. 127.0.0.1
	var $clientAddress;
	/// e.g /index.php
	var $scriptUrl;
	/// e.g. http://example.com/index.php
	var $absScriptUrl;
	/// e.g. "/index.php/help?info=1&edit=False"
	var $requestUri;
	/// e.g. help
	var $page;
	/// e.g. info=1&edit=False
	var $paramString;
	/// e.g. [ "info=1", "edit=False" ]
	var $params;
	/// Debugging: a string with messages.
	var $message;
	/// the data from all pages (user specific). Instance of UserData
	var $userData;
	/// the global configuration data. Instance of Configuration
	var $configuration;
	/// the prefix of a static resource, e.g. http://sidu-installer:8086/
	var $urlStatic;
	/// the url of the form, e.g. http://sidu-installer:8086/index.php
	var $urlForm;
	/// directory for logfile links, e.g. http://sidu-installer:8086/log/
	var $urlPublicDir;
	/// directory for logfile links, e.g. /usr/share/sidu-installer/log/
	/// Note: this directory is a link into a writable directory, e.g. /var/cache/siguibui/public
	var $publicDir;
	/// an array of the input field values
	var $fields;
	/// true: the POST method is used for forms
	var $usePost;
	/// the character set of the configuration files
	var $charset;
	/// instance of Executor
	var $executor;
	/// an unique id for the session
	var $sessionId;
	/// this value replaces the marker META_DYNAMIC. Used for automatic refresh
	var $metaDynamic;
	/// temporary directory, needs write right for the php-program, e.g. /var/cache/siguibui
	var $tempDir;
	/// directory containing the class definition and the content template, e.g. /usr/share/sidu-installer/plugins/
	var $pageDir;

	/** Constructor.
	 */
	function __construct(){
		global $_SERVER;
		$doTrace = true;
		$server = $_SERVER;
		$this->charset = 'UTF-8';
		$this->tempDir = NULL;
		$this->usePost = false;
		// Prefix 'x': we use strpos() and we want to get an index > 0.
		$this->traceFlag = 'x' . TRACE_ALL;
		//$this->traceFile = '/tmp/trace.txt';
		$this->traceFile = "/tmp/trace.txt";
		if ($doTrace)
			$this->traceFp = fopen($this->traceFile, "a");
		else
			$this->traceFp = NULL;
		$this->trace(TRACE_RARE, '==== Start:');
		$ix = -1;
		if (! isset($_SERVER))
			$ix = strpos($_SERVER['QUERY_STRING'], '_debug=1');
		if (! isset($_SERVER['DOCUMENT_ROOT']) || $ix > 0)
			$this->simulateServer();
		$this->parseEnvironment();
		$this->configuration = new Configuration($this);
		$this->tempDir = $this->configuration->getValue('.tempdir');
		$this->publicDir = $this->tempDir . 'public/';
		$this->userData = new UserData($this);
	}
	/** Simulates a webserver: for development only.
	 *
	 * Stores needed data in $_SERVER and $_POST.
	 */
	function simulateServer(){
		global $_SERVER, $_POST, $_GET;
		$this->trace(TRACE_FINE, 'simulateServer()');
		$page = 'partition';
		$button = '';
		#$button = '';
		if (! empty($button))
			$_POST[$button] = 'x';
		#$_POST[$button] = 'x';

		$_SERVER = array();

		$virtualHost = 'sidu-installer';
		$rootDir = "/home/wsl6/php/$virtualHost";

		$_SERVER['PATH_TRANSLATED'] = $rootDir . '/home';
		$_SERVER['HTTP_USER_AGENT'] = 'Opera/9.80 (x11; Linux86_64; U; de) Presto/2.9.168 Version/11.51';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,en;q=0.9,fr-CA;q=0.8,ay;q=0.7,de;q=0.6';
		$_SERVER['REMOTE_PORT'] = '50262';
		$_SERVER['SCRIPT_FILENAME'] = $rootDir . '/index.php';
		$_SERVER['SCRIPT_NAME'] = '/index.php';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_HOST'] = $virtualHost . ':8086';
		$_SERVER['PATH_INFO'] = '';
		$_SERVER['SERVER_PORT'] = '8086';
		if (isset($_POST[$button]))
			$_SERVER['QUERY_STRING'] = $_POST[$button] . '=x';
		$_SERVER['DOCUMENT_ROOT'] =$rootDir;
		$_SERVER['SERVER_ADDR'] = '127.0.0.1';
		$_SERVER['REQUEST_URI'] = '/index.php/home?button_next=Weiter';

		if (True){
		$_SERVER['HTTP_HOST'] = $virtualHost;
		$_SERVER['DOCUMENT_ROOT'] = $rootDir;
		$_SERVER['SCRIPT_FILENAME'] = $rootDir . '/index.php';
		$_SERVER['SCRIPT_NAME'] = '/index.php';
		$_SERVER['REQUEST_URI'] = "/index.php/$page?param2=abc";
		$_SERVER['PATH_INFO'] = "";
		if (! empty($page))
			$_SERVER['PATH_INFO'] = "";
		$_SERVER['PHP_SELF'] = "/index.php";
		$_SERVER['HTTP_HOST'] = $virtualHost;
		#$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,de-DE,de;q=0.9,en;q=0.8';
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9,en;q=0.8';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['HTTP_USER_AGENT'] = 'Opera/9.80 (X11; Linux x86_64; U; de) Presto/2.9.168 Version/11.50';
		$_SERVER["REQUEST_METHOD"] = 'get';
		}

		$_POST['one_disk_disk'] = 'sdc';
		$_POST['volume_group'] = 'tescht2';
		$_POST['del_lv_lv'] = 'work5';
		$_POST['create_lv_unit'] = 'KiByte';
		$_POST['root_pass2'] = '123456';
		$_POST['real_name'] = 'a';
		$_POST['name'] = 'b';
		$_POST['pass'] = '123456';
		$_POST['pass2'] = '123456';
		$_POST['host'] = 'ant';
		$_POST['add_dev'] = '-';
		$_POST['add_mount'] = '';
		$_POST['add_label'] = 'sweden';
		$_POST['add_mount2'] = '';
		$_POST['disk'] = 'all';
		$_POST['partman'] = 'gparted';
		foreach ($_POST as $key => $value)
			$_GET[$key] = $value;
	}
	/** Writes an array to the tracefile.
	 *
	 * The output will be be sorted by keys.
	 * @param $flags the trace control flags
	 * @param $header the headline describing the array
	 * @param $array the array to trace
	 */
	function traceArray($flags, $header, $array){
		$this->trace(TRACE_CONFIG, $header);
		$keys = array();
		foreach ($array as $key => $value)
			$keys[] = $key;
		sort($keys);
		foreach ($keys as $ix => $key)
			$this->trace(TRACE_CONFIG, $key . '=' . $array[$key]);
	}
	/** Gets the needed data from the webserver environment.
	 */
	function parseEnvironment(){
		global $_SERVER, $_POST, $_GET;
		if (false)
			$this->traceArray(TRACE_CONFIG, '_SERVER', $_SERVER);
		if (false && ! empty($_SERVER['TRACE_FLAGS']))
			$this->traceFlag = $_SERVER['TRACE_FLAGS'];
		$mode = $_SERVER['REQUEST_METHOD'];
		$this->usePost = strcasecmp($mode, 'post') == 0;

		if ($this->usePost)
			$this->fields = $_POST;
		else
			$this->fields = $_GET;
		$useTrace = $this->usePost;
		if (false)
			$this->traceArray(TRACE_CONFIG, 'Fields:', $this->fields);
		$this->trace(TRACE_FINE, "usePost: $mode $useTrace");
		$this->scriptFile = $_SERVER['SCRIPT_FILENAME'];
		$parts = $this->splitFile($this->scriptFile);
		$this->homeDir = $parts['dir'];
		$this->scriptUrl = $_SERVER['SCRIPT_NAME'];
		$this->requestUri = $_SERVER['REQUEST_URI'];
		if (empty($_SERVER['PATH_INFO'])){
			$pathInfo = substr($this->requestUri, strlen($this->scriptUrl));
			$ix = strpos($pathInfo, '/');
			if (! ($ix === False) && $ix == 0){
				$ix = strpos($pathInfo, '?');
				if ($ix > 0)
					$pathInfo = substr($pathInfo, 0, $ix);
				$_SERVER['PATH_INFO'] = $pathInfo;
			}
		}

		if (isset($_SERVER['PATH_INFO']) && ! empty($_SERVER['PATH_INFO']))
			$this->page = substr($_SERVER['PATH_INFO'], 1);
		elseif (isset($_GET['page']) && ! empty($_GET['page']))
			$this->page = $_GET["page"];
		else {
			$uri = $this->requestUri;
			$ix = strpos($uri, 'page=');
			if ($ix === False)
				$this->page = 'home';
			else
			{
				$ix += 5;
				$length = strlen($uri);
				$ixEnd = strpos($uri, '&', $ix);
				if ($ixEnd === False)
					$ixEnd = $length;
				$this->page = substr($uri, $ix, $ixEnd - $ix);
			}
		}
		$this->domain = $_SERVER['HTTP_HOST'];
		$ix = strpos($this->domain, ':');
		if ($ix > 0)
			$this->domain = substr($this->domain, 0, $ix);
		$baseAddress = 'http://' . $this->domain;
		$port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '';
		if (! empty($port) && strcmp($port, 80) != 0)
			$baseAddress .= ":$port";
		$absScriptUrl = $baseAddress . $this->scriptUrl;

		$parts = $this->splitFile($this->scriptUrl);
		$this->urlStatic = $baseAddress . $parts['dir'];
		$this->urlPublicDir = $this->urlStatic . "public/";

		$this->absScriptUrl = $absScriptUrl;
		$this->urlForm = $absScriptUrl . '/' . $this->page;

		$this->clientAddress = $_SERVER['REMOTE_ADDR'];
		$agent = $_SERVER['HTTP_USER_AGENT'];
		$agent = preg_replace('/\D/', '', $agent);
		$this->sessionId = 's' . $agent . '_' . $this->clientAddress;
		$this->paramString = '';
		$this->params = NULL;
		$ix = strpos($this->requestUri, '?');
		if ($ix > 0)
		{
			$this->paramString = substr($this->requestUri, $ix + 1);
			$this->params = explode('&', $this->paramString);
		}
		$lang = $this->lang = "en";
		if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
			$lang = $_SERVER["HTTP_ACCEPT_LANGUAGE"];
		// de-DE,de;q=0.9,en;q=0.8
		$ix = strpos($lang, ",");
		if ($ix > 0)
			$lang = substr($lang, 0, $ix);
		$ix = strpos($lang, "-");
		if ($ix > 0)
			$lang = substr($lang, 0, $ix);
		if (strlen($lang) == 2)
			$this->language = $lang;
		$this->trace(TRACE_RARE, 'Origin page: ' . $this->page);
	}
	/** Translate a text.
	 *
	 * Searches the key in the configuration.
	 * If not found the default text is returned.
	 *
	 * @param $plugin		name of the plugin. Will be part of the full key
	 * @param $key			the key of the text in the configuration file
	 * @param $defaultText	this text will be returned if the key is not found
	 * @return the translated text or the default text
	 */
	function i18n($plugin, $key, $defaultText = null){
		$key = "$plugin.$key";
		$rc = $this->configuration->getValue($key);
		if (empty($rc)){
			if ($defaultText != null)
				$rc = $defaultText;
			else
				$rc = $key;
		}
		return $rc;
	}
	/** Checks whether a trace is wanted.
	 *
	 * @param $flag Trace class, e.g. TRACE_URL
	 */
	function isTrace($flag){
		$rc = strpos($this->traceFlag, $flag) > 0 || strpos($this->traceFlag, TRACE_ALL) > 0;
		return $rc;
	}
	/** Puts a message into the trace file.
	 *
	 * @param $flag		trace class containing the condition
	 * @param $message	the message to write
	 */
	function trace($flag, $message){
		if ($this->isTrace($flag) && $this->traceFp != NULL)
		{
			fprintf ($this->traceFp, "%s\n", $message);
			fflush($this->traceFp);
		}
	}
	/** Splits a full path into its parts.
	 *
	 * To get the origin name from the result of this method
	 * the parts must be concatenated without conditions:
	 * $full = $rc["dir"] . $rc["name"] . $rc["ext"];
	 *
	 * Note: pathinfo() does the same with an ugly interface:
	 * Its complicated to join the full filename from the result.
	 *
	 * @param $name		the full filename
	 * @return an array with the entries "dir", "name" and "ext".
	 */
	function splitFile($name){
		$rc = array();
		$ix = strrpos($name, "/");
		if ($ix === false)
			$rc["dir"] = "";
		else{
			$ix++;
			$rc["dir"] = substr($name, 0, $ix);
			$name = substr($name, $ix);
		}
		$ix = strrpos($name, ".");
		if ($ix === false){
			$rc["ext"] = "";
			$rc ["name"] = $name;
		} else {
			$rc["ext"] = substr($name, $ix);
			$rc ["name"] = substr($name, 0, $ix);
		}
		return $rc;
	}
	/** Reads a file.
	 *
	 * @param $filename the full filename
	 */
	function readFile($filename){
		$this->trace(TRACE_CONFIG, "readFile: " . $filename);
		if (! file_exists($filename))
			$content = '';
		else
			$content = file_get_contents($filename);
		return $content;
	}
	/** Writes a string into a file.
	 *
	 * @param $filename	name of the file
	 * @param $content 	the content to write
	 */
	function writeFile($filename, $content){
		$file = fopen($filename, "w");
		if ($file){
			fwrite($file, $content);
			fclose($file);
		}
	}
	/** Returns an array containing variables from a config file.
	 *
	 * The format of the file is like java configuration files:
	 * Each line contains a definition key=value
	 *
	 * @param $filename		the name of the file
	 * @param $ignoredChar	This character will be ignored if it is found before the '='
	 */
	function readJavaConfig($filename, $ignoredChar = NULL){
		$rc = array();
		if (file_exists($filename)){
			$file = file($filename);
			while (list($key, $line) = each($file)) {
				if (strncmp($line, '#', 1) || strncmp($line, ' ', 1)){
					$ix = strpos($line, '=');
					if ($ix > 0){
						$line = trim($line, "\r\n");
						$key = substr($line, 0, $ix);
						if ($ignoredChar != NULL && strpos($key, $ignoredChar) == strlen($key) - 1)
							$key = substr($key, 0, strlen($key) - 1);
						$val = substr($line, $ix + 1);
						$rc[$key] = $val;
						//$this->trace(TRACE_FINE, "readJavaConfig(): $key=$val");
					}
				}
			}
		}
		return $rc;
	}
	/** Tests whether a given file exists in the base directory.
	 *
	 * @param $name the filename relative to the base directory
	 * @return true: the file exists. false: otherwise
	 */
	function fileExists($name){
		$filename = $this->homeDir . $name;
		$rc = file_exists($filename);
		return $rc;
	}
	/** Builds an absolute filename with a given language code.
	 *
	 * @param $name 	the filename with path
	 * @param $subDir	the subdirectory of the file
	 * @param $lang		the language code
	 * @return the filename completed with the language code
	 */
	function buildNameWithLanguage($name, $subDir, $lang){
		$parts = $this->splitFile($name);
		$dir = $parts['dir'];
		$filename = $this->homeDir . $subDir . $parts['dir']
			. $parts['name'] . "_" . $lang
			. $parts['ext'];
		$this->trace(TRACE_FINE, 'buildNameWithLanguage: ' . $filename);
		return $filename;
	}
	/** Returns a filename for the given language.
	 *
	 * @param $name 	the filename without language part
	 * @param $subDir	the subdirectory of the file
	 * @return the filename with language code (if exists)
	 */
	function findFileByLanguage($name, $subDir){
		// We look for a filename containing the language code:
		$filename = $this->buildNameWithLanguage($name, $subDir, $this->language);
		if (! file_exists($filename))
			$filename = $this->buildNameWithLanguage($name, $subDir, 'en');
		if (! file_exists($filename))
			$filename = $this->homeDir . $subDir . $name;
		return $filename;

	}
	/** Reads a file laying in the base directory.
	 *
	 * @param $name 		the filename without a path
	 * @param $useLanguage	true: the filename can contain the language code.
	 * @return the content of the file
	 */
	function readFileFromBase($name, $useLanguage){
		$this->trace(TRACE_CONFIG, 'readFileFromBase: ' . $name);
		if (! $useLanguage)
			$filename = $this->homeDir . 'base/' . $name;
		else{
			// We look for a filename containing the language code:
			$filename = $this->findFileByLanguage($name, 'base/');
		}
		return $this->readFile($filename);
	}
	/** Reads a file laying in the base directory.
	 *
	 * @param $name 		the filename without a path
	 * @param $useLanguage	true: the filename can contain the language code.
	 * @return the content of the file
	 */
	function readFileFromConfig($name, $useLanguage){
		$this->trace(TRACE_CONFIG, 'readFileFromBase: ' . $name);
		if (! $useLanguage)
			$filename = $this->homeDir . 'config/' . $name;
		else{
			// We look for a filename containing the language code:
			$filename = $this->findFileByLanguage($name, 'config/');
		}
		return $this->readFile($filename);
	}
	/** Reads a file laying in the base directory.
	 *
	 * @param $name 		the filename without a path
	 * @param $useLanguage	true: the filename can contain the language code.
	 * @return the content of the file
	 */
	function readFileFromPlugin($name, $useLanguage){
		$this->trace(TRACE_CONFIG, 'readFileFromPlugin: ' . $name);
		if (! $useLanguage)
			$filename = $this->homeDir . 'plugins/' . $name;
		else{
			// We look for a filename containing the language code:
			$filename = $this->findFileByLanguage($name, 'plugins/');
		}
		return $this->readFile($filename);
	}
	/** Redirects to another URL.
	 *
	 * @param $url	the new url (relative)
	 * @param $from	for debugging: caller id
	 */
	function gotoPage($url, $from){
		$this->userData->write();
		$header = 'Location: ' . $this->absScriptUrl . '/' . $url;
		$this->trace(TRACE_RARE, "gotoPage($from): $url -> $header");
		header($header);
		exit;
	}
	/** Assembles debugging output.
	 *
	 * @param $msg	a debugging message
	 */
	function log($msg){
		$this->message .= "<p>" . $msg . "</p>\n";
	}
	/** Returns the assembled debugging messages.
	 *
	 * @return the debugging messages
	 */
	function getMessage(){
		return $this->message;
	}
	/** Tests whether a button has been clicked.
	 *
	 * @return "": No button. Otherwise the name of the button.
	 */
	function hasButton(){
		$rc = "";
		foreach ($this->fields as $key => $value){
			//$this->trace(TRACE_RARE, "hasButton: $key -> $value");
			if (strncmp($key, 'button_', 7) == 0){
				$rc = $key;
				break;
			}
		}
		$this->trace(TRACE_RARE, "hasButton: $rc");
		return $rc;
	}
	/** Tests whether a given field exists.
	 *
	 * @param $field	the name of the field to test
	 * @return false: the field does't exist. Otherwise: the value of the field as string
	 */
	function hasField($field){
		$rc = false;
		if (array_key_exists($field, $this->fields))
			$rc = $this->fields;
		$this->trace(TRACE_FINE, "hasField: $rc");
		return $rc;
	}
	/** Returns the value of a given field.
	 *
	 * @param $name	The name of the field.
	 * @return "": $field not found. Otherwise: the value of $_fields[$name]
	 */
	function getField($name){
		$rc = "";
		if (isset($this->fields[$name]))
			$rc = $this->fields[$name];
		return $rc;
	}
	/** Returns the previous page (name of the plugin).
	 *
	 * @param $current	the name of the current plugin.
	 * @return NULL: there is no previous page. Otherwise: the name of the previous plugin
	 */
	function getPrevPage($current){
		$plugins = explode(CONFIG_SEPARATOR, $this->configuration->getValue('.gui.pages'));
		$rc = NULL;
		$last = NULL;
		foreach ($plugins as $key => $name){
			$name = trim($name);
			if (strcmp($name, $current) == 0){
				$rc = $last;
				break;
			}
			$last = $name;
		}
		$this->trace(TRACE_FINE, "getPrevPage(): $rc");
		return $rc;
	}
	/** Returns the next page (name of the plugin).
	 *
	 * @param $current	the name of the current plugin.
	 * @return NULL: there is no next page. Otherwise: the name of the next plugin
	 */
	function getNextPage($current){
		$list = $this->configuration->getValue('.gui.pages');
		$plugins = explode(CONFIG_SEPARATOR, $list);
		$rc = NULL;
		foreach ($plugins as $key => $name){
			if (strcmp(trim($name), $current) == 0){
				if ($key < count($plugins) - 1)
					$rc = trim($plugins[$key + 1]);
				break;
			}
		}
		$this->trace(TRACE_FINE, "getNextPage(): $rc");
		return $rc;
	}
	/** Executes an external command.
	 *
	 * Calls the shell server and wait for the answer.
	 * The answer is a file.
	 *
	 * @param $answer 	the name of the answer file
	 * @param $options	the options for the shell server, e.g. SVOPT_DEFAULT
	 * @param $command	the command to execute
	 * @param $params	NULL or a string or an array of strings
	 * @param $timeout	the maximum count of seconds
	 * @return true: answer file exists. false: timeout reached
	 */
	function exec($answer, $options, $command, $params, $timeout){
		$this->trace(TRACE_RARE, "exec: $command");
		if ($this->executor == NULL){
			include_once 'executor.php';
			$this->executor = new Executor($this);
		}
		$rc = $this->executor->execute($answer, $options, $command, $params, $timeout);
		return $rc;
	}
	/** Returns an unique filename for an answer of the external program.
	 *
	 * @param $prefix	the prefix of the name returned
	 * @param $suffix	the suffix of the name returned
	 * @return an unique filename in the temp directory
	 */
	function getAnswerFileName($prefix, $suffix){
		$rc = $this->tempDir . $prefix . $this->sessionId . $suffix;
		return $rc;
	}
	/** Generates a password hash like mkpassword command in linux.
	 *
	 * @param $clearText	the password to encode
	 * @return a string which can be used as argument for the usermod command
	 */
	function makePasswordHash($clearText){
		if (false){
			// Get the initial hash:
			$hash = pack('H*', hash('sha256', $clearText, false));
			// Get six random bytes:
			srand(time() ^ (ord($clearText) * 0x777));
			$salt = pack('c6', rand(0,255), rand(0,255), rand(0,255), rand(0,255), rand(0,255), rand(0,255));
			// Get the final hash:
			$hash = pack('H*', hash('sha256', $hash . $salt, false));
			// Build the full encoded string: $ <method> $ <salt> $ <hash>
			$output = '$5$' . base64_encode($salt) . '$' . base64_encode($hash);
			$output = str_replace('+', '.', $output);
		} else {
			$handle = popen("/usr/bin/mkpasswd --method=SHA-256 '$clearText'" , 'r');
			if ($handle != NULL){
				$output = chop(fgets($handle));
				fclose($handle);
			}
		}
		if ($this->configuration->getValue('.fixPassword'))
			$output = '$5$aChnRTTCXTG7$h6z1eVHhVrnBzb6gYjDJrT7q/BARtkDTckTaQyDWyF3';
		return $output;
	}
	/** Appends escape sequences for a shell.
	 *
	 * $ will be replaced by '\$'
	 * @param $text the text to work
	 * @return the text with escape characters
	 */
	function escShell($text){
		$rc = str_replace('$', '\\$', $text);
		return $rc;
	}
	/** Forces the reload of the current page.
	 *
	 * @param $wait	The time in seconds of the reload
	 */
	function forceReload($wait){
		if (empty($wait))
			$wait = 3;
		$this->metaDynamic = "<meta http-equiv=\"refresh\" content=\"$wait; URL=" . $this->urlForm . '" />';
	}
	/** Tests wheter the given file exists.
	 *
	 * The file cretion will be forced:
	 * The file doesnt exist and:
	 *   This is the first time
	 *   The timeForCreation is left since the last creation
	 * @param $file				the file to test
	 * @param $id				the key in the user data
	 * @param $wait				the number of seconds until the next reload
	 * @param $timeForCreation	the number of seconds needed to create the file
	 *
	 * @return true: the file must be created. false: otherwise
	 */
	function testFile($file, $id, $timeForCreation){
		$rc = false;
		if (! file_exists($file)){
			$created = $this->userData->getValue('', $id);
			if (empty($created)){
				$rc = true;
				$this->userData->setValue('', $id, (string) time());
			} elseif (time() - (int) $created - 3 >= $timeForCreation) {
				$this->userData->setValue('', $id, '');
			}
			$this->forceReload(3);
		}
		return $rc;
	}
	/** Finds the index of an array item.
	 *
	 * @param $array	here we will search
	 * @param $value	the value to find
	 * @return -1: not found Otherwise: the index
	 */
	function findIndex($array, $value){
		$rc = -1;
		foreach ($array as $ix => $current)
			if (strcmp($current, $value) == 0){
				$rc = $ix;
				break;
			}
		return $rc;
	}
	/**
	 * Returns the greatest x where x = 2**n and x <= $value.
	 *
	 * @param $value the value to round down
	 */
	function roundDownToPowerOf2($value){
		if ($value <= 0)
			$value = 0;
		else{
			$n = 0;
			while($value > 0){
				$n++;
				$value >>= 1;
			}
			$value = 1;
			while(--$n > 0)
				$value *= 2;
		}
		return $value;
	}
	/**
	 * Sets the value of an input field.
	 *
	 * @param $field	an input field
	 * @param $value	the new value
	 */
	function setField($field, $value){
		$_POST[$field] = $_GET[$field] = $value;
	}
	/**
	 * Builds a list from the (fix) configuration with (dynamic) elements.
	 * @param $keyConfig	key in the configuration (with prefix (plugin))
	 * @param $plugin		prefix in user data
	 * @param $keyUser		the extended list will be stored with this key (in user data)
	 * @param $listExtended	the elements to add
	 * @param $separator	separator of the two lists
	 */
	function extendList($keyConfig, $plugin, $keyUserData, $listExtended, $separator = ';' ){
		$list = $this->configuration->getValue($keyConfig);
		if (! empty($listExtended))
				$list .= $separator . $listExtended;
		$this->userData->setValue($plugin,  $keyUserData, $list);
	}
}
?>
