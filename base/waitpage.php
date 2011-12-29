<?php
/**
 * Waits for the end of an application.
 * 
 * Implements a plugin.
 * 
 * @author hm
 */
class WaitPage extends Page{
	/// Translation texts. Key: english text Value: local texts.
	var $translations;
	
	/** Constructor.
	 * 
	 * @param $session
	 */
	function __construct(&$session){
		parent::__construct($session, 'wait');
		$sleep = (int) $this->getConfiguration('refresh');
		$session->forceReload($sleep);
		$this->translations = null;
	}
	/** Reads a table containing englisch texts and translations.
	 * 
	 * This is used for translation external texts in progress messages.
	 * Convention (format of the configuration file):
	 * <name>.count=<count>
	 * <name.1=<english>|<translation>
	 * 
	 * @param $name The name of the translation part in the configuration file
	 */
	function readTranslationTable($name){
		$this->translations = array();
		$count = $this->session->configuration->getValue("$name.count");
		for ($ii = 1; $ii <= $count; $ii++){
			$line = $this->session->configuration->getValue("$name.$ii");
			$cols = explode('|', $line);
			$key = $cols[0];
			$value = $cols[1];
			$this->session->trace(TRACE_FINE, "trans: $line Key: $key Val: $value");
			$this->translations[$key] = $value;
		}		
	}
	/** Search for a key into the translation array which is a prefix of $text.
	 * 
	 * @param $text the text to inspect
	 * @return 	null: no prefix found. 
	 * 			Otherwise: the key in the translation table which is a prefix of $text
	 */
	function findTranslationPrefix($text){
		$rc = null;
		$this->session->trace(TRACE_FINE, "findTranslationPrefix: $text");
		foreach ($this->translations as $key => $value){
			if (strncmp($text, $key, strlen($key)) == 0){
				$rc = $key;
				$this->session->trace(TRACE_FINE, "findTranslationPrefix: rc: $rc");
				break;
			}
		}
		return $rc;
	}
	/** Translate an English text if it is in the translation table. 
	 * 
	 * @param $key the key to translate
	 * @return $key: no translation found. Otherwise: translation
	 */
	function translate($key){
		if ($this->translations != NULL && isset($this->translations[$key])){
			$key = $this->translations[$key];
			$this->session->trace(TRACE_FINE, "translate(): $key");
		}
		return $key;
	}
	/** Reads one entry of the progress file.
	 * 
	 * One entry have 3 lines:
	 * PERC=30
	 * CURRENT=<b>Partition created</b>
	 * COMPLETE=completed 3 of 20
	 * @param $session
	 * @param $file		Name of the progress file 
	 */ 
	function readProgress(&$session, $file){
		$fp = fopen($file, "r");
		if ($fp){
			$line = "";
			while($line = fgets($fp, 16000)){
				$line = chop($line);
				if (empty($line))
					break;
				$cols = explode('=', $line, 2);
				if (strcasecmp($cols[0], 'PERC') == 0){
					$procent = $cols[1];
						// fll-installer returns a factor: 0.95 == 95%
					if (strncmp($procent, '0.', 2) == 0)
						$procent = strval(intval(floatval($procent) * 100));
					$this->setUserData('progress.procent', $procent);
				} elseif (strcasecmp($cols[0], 'CURRENT') == 0){
					$text = $cols[1];
					$search = str_replace('<b>', '', $text);
					$search = str_replace('</b>', '', $search);
					$prefix = $this->findTranslationPrefix($search);
					if ($prefix != NULL){
						$trans = $this->translate($prefix);
						$text = str_replace($prefix, $trans, $text);
					}
					$this->setUserData('progress.description', $text);
				} elseif (strcasecmp($cols[0], 'COMPLETE') == 0){
					$cols = explode(' ', $cols[1]);
					$this->setUserData('progress.ix', $cols[1]);
					$this->setUserData('progress.max', $cols[3]);
				} elseif (strcasecmp($cols[0], 'end') == 0){
					$this->setUserData('progress.procent', '100');
				} elseif (count($cols) > 1) {
					$this->progressText = "unknown content of $file: $line";
				}
			}
			fclose($fp);
		}
	}
	/** Builds the core content of the page.
	 * 
	 * Overwrites the method in the baseclass.
	 */
	function build(){
		$this->session->trace(TRACE_RARE, 'WaitPage.build()');
		$this->readHtmlTemplates();
		$demoText = '';
		$file = $this->getUserData('answer');
		$value = $this->getUserData('blocked');
		if (! empty($value))
			$this->stop('blocked is set');
		elseif (file_exists($file)){
			$this->setUserData('file.answer', $file);
			$this->stop('wait.ready');
		} else{
			$translationName = $this->getUserData('translations');
			if (! empty($translationName))
				$this->readTranslationTable($translationName);
				
			$this->readContentTemplate();
			$intro = $this->getUserData('intro');
			$description = $this->getUserData('description');
			$this->replaceMarker('txt_intro', $intro);
			if (empty($description))
				$this->clearPart('DESCRIPTION');
			else{
				$this->replacePartWithTemplate('DESCRIPTION');
				$this->replaceMarker('txt_description', $description);
			}
			$procent = -1;
			$state = "";
			if (! file_exists('/etc/inosid/demo_progress')){
				$file = $this->getUserData('progress');
				if (! file_exists($file))
					$this->clearPart('PROGRESS');
				else {
					$this->replacePartWithTemplate('PROGRESS');
					$this->readProgress($session, $file);
					$procent = (int) $this->getUserData('progress.procent');
					$state = $this->getUserData('progress.description');
					if (empty($state))
						$this->clearPart('PROGRESS_STATE');
					else{
						$this->replacePartWithTemplate('PROGRESS_STATE');
						$prefix = $this->getConfiguration('PROGRESS_STATE');
						$this->replaceMarker('PROGRESS_STATE', $prefix . ' ' . $state);
					}				
				}
			} else {
				$demoText = $this->getConfiguration('txt_demotext');
				$value = $this->getUserData('demo.progress');
				$this->session->trace(TRACE_FINE, 'WaitPage.build() Progress: ' . $value);
				$procent = (int) $value; 
				$this->setUserData('demo.progress', strval ($procent + 10));
			}				 
			$this->replaceMarker('PROCENT', strval($procent) . '%');
			$this->replaceMarker('WIDTH', strval($procent));
			$this->replaceMarker('DEMO_TEXT', $demoText);
		}
	}
	/** Stops the waiting.
	 * 
	 * @param $from		the reason of the stop (for tracing)
	 * @return false
	 */
	function stop($from){
			$this->session->trace(TRACE_RARE, 'WaitPage.stop()');
			$this->setUserData('answer', '');
			$this->setUserData('program', '');
			$caller = $this->getUserData('caller');
			
			$this->setUserData('description', '');
			$this->setUserData('progress', '');
			$this->session->gotoPage($caller, $from);
			$this->session->userData->write();
			$this->setUserData('demo_progress', '');
			return false;
	}		
	/** Returns an array containing the input field names.
	 * 
	 * @return an array with the field names
	 */
	function getInputFields(){
		$rc = array();
		return $rc;
	}
	/** Will be called on a button click.
	 * 
	 * @param $button	the name of the button
	 * @return false: a redirection will be done. true: the current page will be redrawn
	 */
	function onButtonClick($button){
		$this->session->trace(TRACE_RARE, 'WaitPage.onButtonClick() ' . $button);
		$redraw = true;
		if (strcmp($button, "button_cancel") == 0){
			$redraw = $this->stop('wait.cancel');
		}
		return $redraw;
	} 
}
