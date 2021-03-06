<?php
/**
 * Defines a base class for a page builder plugin.
 *
 * A page builder composes the core content of an html page.
 *
 * @author hm
 */
abstract class Page{
	/// the content of the page as string
	var $content;
	/// the session info
	var $session;
	/// an array with marker-value pairs
	var $replacements;
	/// name of the plugin
	var $name;
	/// parts of the html which are optional. It's an array: key: name value: html text
	var $parts;

	/** Constructor.
	 *
	 * @param $session		the session info
	 * @param $name			name of the plugin
	 */
	function __construct(&$session, $name){
		$this->name = $name;
		$this->session = $session;
		$this->content = "";
		$this->replacements = array();
		$this->parts = array();
		if ($session->hasButton())
			$this->setFieldsFromHeader();
		else
			$this->setFieldsFromUserData();
	}
	/**
	 * Returns the field value.
	 * First the GET variables will be inspected.
	 * If not found the user data will be inspected.
	 *
	 * @param $field
	 * @return 	'': Field not found<br/>
	 * 			otherwise: the field value
	 */
	function getField($field){
		$rc = $this->session->getField($field);
		if (empty($rc))
			$rc = $this->getUserData($field);
		return $rc;
	}
	/** Returns the name of the html template.
	 *
	 * Can be overwritten by sub classes.
	 *
	 * @return the name of the html template without path
	 */
	function getTemplateName(){
		return $this->session->domain . '.html';
	}
	/** Builds the core content of the page.
	 *
	 * This method must be overwritten by the derived classes.
	 */
	abstract function build();
	/**
	 * Builds the main menu of the page.
	 *
	 * @return the HTML code of the menu
	 */
	function buildMenu(){
		$expanded = $this->getConfiguration('menu.expanded');
		$menu = new Menu($this->session, 'main', 'menu', '',
				strcmp($expanded, 'true'));
		$menu->read();
		$parts = array();
		$filename = $this->session->homeDir . 'config/menu.parts.txt';
		$this->readParts($filename, $parts);
		$rc = $menu->buildHtml($parts);
		return $rc;
	}
		/** Returns an array containing the input field names.
	 *
	 * Must be overwritten if keys exist.
	 *
	 * @return an array with the field names
	 */
	function getInputFields(){
		return array();
	}
	/** Returns the composed core content.
	 *
	 * @return the content of the page
	 */
	function getContent(){
		return $this->content;
	}
	/** Will be called on a button click.
	 *
	 * @param $button	the name of the button
	 * @return false: a redirection will be done. true: the current page will be redrawn
	 */
	abstract function onButtonClick($button);
	/** Replaces the markers in the page content.
	 */
	function replaceMarkers(){
		foreach($this->replacements as $key => $value){
			$this->content = str_replace($key, $value, $this->content);
		}
	}
	/** Stores a marker-value pair.
	 *
	 * It is necessary because of the loading of the template is done
	 * after the validation of the input fields.
	 *
	 * @param $marker	the marker in the template
	 * @param $value		the value which will replace the marker
	 * @param $encode	true: html characters will be encoded. false: No encoding
	 */
	function setReplacement($marker, $value, $encode = false){
		//$this->session->trace(TRACE_FINE, "setReplacement: $marker $value");
		if ($encode && strncmp($value, '<xml>', 5) == 0){
			$encode = false;
			$value = substr($value, 5);
		}
		if ($encode)
			$value = htmlentities($value, ENT_NOQUOTES, $this->session->charset);
		$this->replacements[$marker] = $value;
	}
	/** Sets the value of an input field.
	 *
	 * @param $field	the field name
	 * @param $value	the value to set
	 */
	function setField($field, $value){
		$this->session->trace(TRACE_RARE, "Page.setField($field, $value)");
		$marker = '###VAL_' . strtoupper($field) . '###';
		$this->setReplacement($marker, $value, true);
	}
	/** Sets the value of an input field and in the user data.
	 *
	 * @param $field	the field name
	 * @param $value	the value to set
	 */
	function setFieldAndUserData($field, $value){
		$this->setField($field, $value);
		$this->setUserData($field, $value);
	}
	/** Sets the fields from http header and store them in the user data.
	 */
	function setFieldsFromHeader(){
		$this->session->trace(TRACE_RARE, 'Page.setFieldsFromHeader()');
		foreach($this->session->fields as $field => $value){
			if (strncmp('button_', $field, 7) != 0)
			{
				$this->setField($field, $value);
				$this->setUserData($field, $value);
			}
		}
	}
	/** Tests whether a string is in an array.
	 *
	 * @param $array	the array to inspect
	 * @param $name		the name to search for
	 * @return true: the name is in the array
	 */
	function contains(&$array, $name){
		$rc = false;
		foreach($array as $key => $value){
			if (strcmp($name, $value) == 0){
				$rc = true;
				break;
			}
		}
		return $rc;
	}
	/** Sets the fields from the user data.
	 */
	function setFieldsFromUserData(){
		$this->session->trace(TRACE_RARE, 'Page.setFieldsFromUserData()');
		$plugin = $this->name;
		$fields = $this->getInputFields();
		if ($fields != null)
			foreach($fields as $key => $name){
			$val = $this->session->userData->getValue($plugin, $name);
			$this->setField($name, $val);
		}
	}
	/** Sets an error message for a input field.
	 *
	 * @param $field	the name of the field
	 * @param $message	the error message
	 * @return false
	 */
	function setFieldError($field, $message){
		$marker = '###ERROR_' . strtoupper($field) . '###';
		if (! empty($message))
			$message = '<div class="error_message">'
			. htmlentities($message, ENT_NOQUOTES, $this->session->charset)
			. '</div>';
		$this->replacements[$marker] = $message;
		return false;
	}
	/** Sets an error message for a input field.
	 *
	 * @param $field	the name of the field
	 * @param $key		the key of the error message (in the configuration)
	 * @return false
	 */
	function setFieldErrorByKey($field, $key){
		$message = $this->i18n($key, $key);
		$this->setFieldError($field, $message);
		return false;
	}
	/** Clear all validation errors.
	 *
	 * Sets the replacement of the marker for the error message to ''.
	 * If an error is detected later the replacement will be set to the error message.
	 */
	function clearAllFieldErrors(){
		// Clear errors
		$fields = $this->getInputFields();
		if ($fields != null)
			foreach($fields as $key => $val){
			$this->setFieldError($val, '');
		}
		$this->setErrorMessage('');
	}
	/**
	 * Sets an error message to a standard error position.
	 *
	 * @param $message the message to display. If empty the messag will be cleared
	 * @param $marker the marker of the error message used in the html code
	 */
	function setErrorMessage($message, $marker = '###ERROR_MESSAGE###'){
		if (! empty($message))
			$message = '<div class="error_message">'
			. htmlentities($message, ENT_NOQUOTES, $this->session->charset)
			. '</div>';
		$this->setReplacement($marker, $message);
	}
	/** Translate a text.
	 *
	 * Searches the key in the configuration.
	 * If not found the default text is returned.
	 *
	 * @param $key			the key of the text in the configuration file
	 * @param $defaultText	this text will be returned if the key is not found
	 * @return the translated text or the default text
	 */
	function i18n($key, $defaultText = null){
		$rc = $this->session->i18n($this->name, $key, $defaultText);
		return $rc;
	}
	/** Replaces the text markers in the content with the translated text from the configuration.
	 */
	function replaceTextMarkers(){
		$start = 0;
		while ( ($start = strpos($this->content, '###txt_', $start)) > 0){
			$end = $start + 7 + strspn($this->content, '_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXZ01234567890', $start + 7);
			$end = $end + strspn($this->content, '#', $end, 3);
			$marker = substr($this->content, $start, $end - $start);
			$key = trim($marker, '#');
			$value = $this->i18n($key, $key);
			$this->setReplacement($marker, $value, true);
			$start = $end;
		}
	}
	/** Builds the options of a selection field.
	 *
	 * @param $field			the name of the selection field
	 * @param $selected			the value of the selected option
	 * @param $fromUserData		true: the values of the options come from user data. false: the values of the options come from configuration
	 * @return the options of the selection field
	 */
	function buildSelectionField($field, $selected, $fromUserData){
		if ($fromUserData){
			$key = 'opt_' . $field;
			$value = $this->getUserData($key);
		}else{
			$key = $this->name . '.opt_' . $field;
			$value = $this->getConfiguration("opt_$field");
		}
		$list = explode(OPT_SEPARATOR, $value);
		$rc = '';
		foreach($list as $no => $value){
			$rc .= '<option';
			if (strcmp($value, $selected) == 0)
				$rc .= OPTION_SELECTED;
			$rc .= '>' . $value . "</option>\n";
		}
		return $rc;
	}
	/** Replaces the marker OPT_x by the options described in the configuration.
	 *
	 * @param $field			the field name
	 * @param $fromUserData		true: the values of the options come from user data. false: the values of the options come from configuration
	 */
	function fillOptions($field, $fromUserData = false){
		$selected = $this->getUserData($field);
		$options = $this->buildSelectionField($field, $selected, $fromUserData);
		$marker = '###OPT_' . strtoupper($field) . '###';
		$this->content = str_replace($marker, $options, $this->content);
	}
	/** Sets the selected option of a selectio field in the user data.
	 *
	 * @param $field			the name of the field
	 * @param $index			the index of the default value in the option definition (in configuration)
	 * @param $setOnlyIfEmpty	true: the option will be set only if the user data does not contain this field
	 */
	function setDefaultOption($field, $index, $setOnlyIfEmpty){
		$value = $setOnlyIfEmpty ? $this->getUserData($field) : '';
		if (! $setOnlyIfEmpty || empty($value)){
			$key = 'opt_' . $field;
			$value = $this->getConfiguration($key);
			$list = explode(OPT_SEPARATOR, $value);
			if ($index < count($list))
				$selected = $list[$index];
			elseif (count($list) == 0)
			$selected = '-';
			else
				$selected = $list[0];
			$this->setUserData($field, $selected);
		}
	}
	/** Stores the selected value of a selection field into the user data.
	 *
	 * @param $field	the name of the field
	 */
	function setSelectionField($field){
		$value = $this->session->getField($field);
		$this->setUserData($field, $value);
	}
	/** Sets an empty field to a default value read from the configuration.
	 *
	 * @param $field	the field to set
	 * @param $key		the key in the configuration
	 */
	function setEmptyToDefault($field, $key){
		$value = $this->getUserData($field);
		if (empty($value)){
			$value = $this->session->configuration->getValue($key);
			$this->setUserData($field, $value);
			$this->setField($field, $value);
		}
	}
	/**
	 * Puts the error message to the parameterized place.
	 *
	 * @param $field 					the field where the error has occurred
	 * @param $message 					the message to show
	 * @param $useStandardErrorMessage 	true: the marker '###ERROR_MESSAGE###' will be replaced.
	 * 									false: the field specific error makro will be replaced
	 */
	function putErrorMessage($field, $message, $useStandardErrorMessage){
		if ($useStandardErrorMessage)
			$this->setErrorMessage($message);
		else
			$this->setFieldError($field, $message);
		return false;
	}
	/** Tests whether a field value contains valid character only.
	 *
	 * Tests whether the field is empty. If yes and mandatory an error occurres.
	 * Tests whether the field matches a regular expression.
	 * If no error the field will be stored into the user data.
	 *
	 * @param $field		the name of the field to test
	 * @param $firstChars	the characters which can be the first character of the value
	 * @param $restChars	the characters which can be the not first characters of the value
	 * @param $mandatory	true: an empty field returns an error false: the field may be empty
	 */
	function isValidContent($field, $firstChars, $restChars, $mandatory, $useStandardErrorMessage = false){
		$ok = true;
		$value = $this->session->getField($field);
		if (empty($value)){
			if ($mandatory){
				$ok = $this->putErrorMessage($field,
						$this->session->i18n('', 'EMPTY_FIELD', 'Empty field!'),
						$useStandardErrorMessage);
			}
		} else {
			$pattern = '/^[' . $firstChars . ']/';
			$found = preg_match($pattern, $value);
			if (! $found){
				$ok = $this->putErrorMessage($field,
						$this->session->i18n('', 'WRONG_FIRST', 'Wrong first char!')
						. ' ' . $value.substr(0, 1)
						. $this->session->i18n('', 'ALLOWED', 'Allowed:')
						. ' ' . $firstChars,
						$useStandardErrorMessage);
			} else {
				$pattern = '/^.[' . $restChars . ']*$/';
				$found = preg_match($pattern, $value);
				if (! $found){
					$pattern = '/[' . $restChars . ']/';
					$wrong = preg_replace($pattern, '', substr($value, 1));
					if (! (strpos($wrong, ' ') === false))
						$wrong = $this->session->i18n('', 'BLANK', '<blank>') . $wrong;
					$ok = $this->putErrorMessage($field,
							$this->session->i18n('', 'WRONG_NEXT', 'Wrong char(s)!')
							. ' ' . $wrong . ' '
							. $this->session->i18n('', 'ALLOWED', 'Allowed:')
							. ' ' . $restChars,
							$useStandardErrorMessage);
				}
			}
		}
		if ($ok)
			$this->setUserData($field, $value);

		return $ok;
	}
	/** Tests whether a password is correct.
	 *
	 * @param $field		the name of field to test
	 * @param $minLength	the minimum length of the password
	 * @param $maxLength	the maximum length of the password
	 */
	function validPassword($field, $minLength, $maxLength){
		$ok = true;
		$value = $this->session->getField($field);
		if (empty($value))
			$ok = $this->setFieldError($field,
					$this->session->i18n('', 'EMPTY_FIELD', 'Empty field!'));
		else if (strlen($value) < $minLength)
			$ok = $this->setFieldError($field,
					$this->session->i18n('', 'TOO_SHORT', 'Too short!'));
		else if (strlen($value) > $maxLength)
			$ok = $this->setFieldError($field,
					$this->session->i18n('', 'TOO_LONG', 'Too short!'));
		if ($ok)
			$this->setUserData($field, $value);
		return $ok;
	}
	/** Tests one or two passwords are valid.
	 *
	 * @param $field1		the name of first field to test
	 * @param $field2		the name of the 2nd field to test. The passwords must be equal
	 * @param $minLength	the minimum length of the password
	 * @param $maxLength	the maximum length of the password
	 * @return true: passwords are valid. false: otherwise
	 */
	function validPasswords($field1, $field2, $minLength, $maxLength){
		$val1 = $this->session->getField($field1);
		$val2 = $this->session->getField($field2);
		if (strncmp($val1, '-', 1) == 0)
			$ok = $this->setFieldError($field1,
					$this->session->i18n('', 'NOT_MINUS', '- not allowed at the beginning!'));
		else{
			$ok = $this->validPassword($field1, $minLength, $maxLength);
			if ($ok && strcmp($val1, $val2)	!= 0)
				$ok = $this->setFieldError($field2,
						$this->session->i18n('', 'NOT_EQUAL', 'Not equal!'));
		}
		return $ok;

	}
	/** Navigates to another page depending on prev or next button.
	 *
	 * @param $backNotNext	true: the prev button has been pushed. false: the next button has been pushed
	 * @return false (for comfortable handling)
	 */
	function navigation($backNotNext){
		if ($backNotNext)
			$page = $this->session->getPrevPage($this->name);
		else
			$page = $this->session->getNextPage($this->name);
		$this->session->gotoPage($page, $this->name . ($backNotNext ? '.prev' : '.next'));
		return false;
	}
	/** Reads the template for the content area of the current plugin.
	 */
	function readContentTemplate(){
		$this->content = $this->session->readFile(
				$this->session->pageDir . $this->name . '.content.txt');
	}
	/** Replaces a marker with a text from the configuration
	 *
	 * @param $key	the key of the text in the configuration
	 */
	function replaceInContent($key, $text = null){
		if ($text == null)
			$text = $this->getConfiguration($key);
		$this->content = str_replace("###${key}###", $text, $this->content);
	}
	/** Replaces a marker with a text from the user data.
	 *
	 * @param $key	the key of the text in the userdata
	 */
	function replaceInContentWithUserData($key){
		$text = $this->getUserData($key);
		$this->content = str_replace("###${key}###", $text, $this->content);
	}
/** Replaces a marker with a given text.
 *
 * @param $marker	the marker to replace (without ###)
 * @param $text		the replacement
 */
function replaceMarker($marker, $text){
	$this->content = str_replace("###${marker}###", $text, $this->content);
}
/** Gets the count of rows of a given table.
 *
 * @param $table	the name of the table
 * @return the count of rows
 */
function getRowCount($table)
{
	$key = $table . '.rowcount';
	$count = $this->getUserData($key);
	return (int) $count;
}
/** Gets the count of rows of a given table.
 *
 * @param $table	the name of the table
 * @param $count 	the count of rows
 */
function setRowCount($table, $count)
{
	$key = $table . '.rowcount';
	$this->setUserData($key, $count);
}
/** Replaces or adds a table's row in the user data.
 *
 * @param $table	name of the table
 * @param $cols		the array with the cols or the string with the cols
 * @param $index	index of the row. If -1 the row will be added
 */
function setRow($table, $cols, $index = -1){
	if ($index < 0){
		$index = $this->getRowCount($table);
		$this->setRowCount($table, $index + 1);
	}
	if (is_array($cols))
		$cols = implode('|', $cols);
	$key = $table . '.row' . strval($index);
	$this->setUserData($key, $cols);
}
/** Gets table's row in the user data.
 *
 * @param $table	name of the table
 * @param $index	index of the row. If -1 the row will be added
 * @return the wanted row as string
 */
function getRow($table, $index){
	$key = $table . '.row' . strval($index);
	$rc = $this->getUserData($key);
	return $rc;
}
/** Deletes a row given by an index.
 *
 * @param $table	the table's name
 * @param $index	the index to delete
 */
function delRow($table, $index){
	$count = $this->getRowCount($table);
	for ($ix = $index; $ix < $count - 1; $ix++){
		$row = $this->getRow($table, $ix + 1);
		$this->setRow($table, $row, $ix);
	}
	$key = $table . '.rowcount';
	$rc = $this->setUserData($key, strval($count - 1));
}
/** Builds a dynamic list of rows given by info of user data.
 *
 * @param $table 	name of the table
 */
function fillRows($table){
	$count = $this->getRowCount($table);
	$block = '';
	for ($ix = 0; $ix < $count; $ix++){
		$value = $this->getRow($table, $ix);
		$block .= '<tr>';
		$cols = explode('|', $value);
		foreach ($cols as $no => $col){
			$block .= '<td>';
			if (strncmp($col, 'CHECKBOX_', 9) == 0){
				$name2 = $name = strtolower($col) . '_' . strval($ix);
				$pos = strrpos($name, '_');
				if ($pos > 0)
					$name2 = substr($name, 0, $pos);
				$value = $this->getConfiguration("txt_$name2");
				$block .= "<input type=\"checkbox\" name=\"$name2\" value=\"$name2\" />$value";
			} elseif (strncmp($col, 'BUTTON_', 7) == 0){
				$name2 = $name = strtolower($col);
				$pos = strrpos($name, '_');
				if ($pos > 0)
					$name2 = substr($name, 0, $pos);
				$value = $this->getConfiguration("txt_$name2");
				$block .= '<input type="submit" name="' . $name . '" value="'
				. htmlentities($value, ENT_NOQUOTES, $this->session->charset)
				. '" />';
			} else {
				$block .= htmlentities($col, ENT_NOQUOTES, $this->session->charset);
			}
			$block .= "</td>\n";
		}
		$block .= "</tr>\n";
	}
	$marker = '###ROWS_' . strtoupper($table) . '###';
	$this->content = str_replace($marker, $block, $this->content);
}
/** Gets a value from configuration (for the current plugin).
 *
 * @param $field	name of the field to get
 */
function getConfiguration($field){
	$rc = $this->session->configuration->getValue($this->name . '.' . $field);
	return $rc;
}
/** Gets a value from user data (for the current plugin).
 *
 * @param $field	name of the field to get
 */
function getUserData($field){
	$rc = $this->session->userData->getValue($this->name, $field);
	return $rc;
}
/** Sets a value in the user data (for the current plugin).
 *
 * @param $field	name of the field to set
 * @param $value	the value to set
 */
function setUserData($field, $value){
	$this->session->userData->setValue($this->name, $field, $value);
}
/** Calls an external program and switch to the wait page.
 *
 * @param answer		name of the file which shows the end of the program run
 * @param program		name of the program
 * @param description	the description, what the user should do
 	* @param progress		NULL or the name of a file with the progress value (in %)
 * @param translations	null or name of the translation table in the configuration
 * @return false
 */
function startWait($answer, $program, $description, $progress, $translations = null){
	$this->session->trace(TRACE_RARE, "startWait: prog: $program desc: $description");
	$this->session->userData->setValue('wait', 'answer', $answer);
	if (strpos($program, ' ') > 0){
		$intro =  $program;
		$program = '';
	} else {
		$intro = $this->session->configuration->getValue('wait.txt_intro');
		$intro = str_replace('###PROGRAM###', $program, $intro);
	}
	$this->session->userData->setValue('wait', 'intro', $intro);
	$this->session->userData->setValue('wait', 'program', $program);
	$this->session->userData->setValue('wait', 'caller', $this->name);
	if (strncmp('<xml>', $description, 5) == 0)
		$description = substr($description, 5);
	else
		$description = htmlentities($description, ENT_NOQUOTES, $this->session->charset);
		$this->session->userData->setValue('wait', 'description', $description);
	$this->session->userData->setValue('wait', 'progress', $progress == NULL ? '' : $progress);
	$this->session->userData->setValue('wait', 'demo.progress', $progress == NULL ? '' : $progress);
	$this->session->userData->setValue('wait', 'translations', $translations == NULL ? '' : $translations);
	$this->session->gotoPage('wait', 'startwait');
	return false;
}
/** Gets the index of value in a list.
 *
 * The value and the list are in the user data.
 *
 * @param $page					name of the page
 * @param $field				name of the field
 * @param $keyOfListUserData	NULL or the key of the list in the user data
 * @param $keyOfListConfig		NULL or the key of the list in the configuration
 * @return -1: key is not in the list. Otherwise: the index in the list
 */
function indexOfSelectionField($page, $field, $keyOfListUserData, $keyOfListConfig){
	$value = $this->session->getField($field);
	if (empty($value))
		$value = $this->getUserData($field);
	if (empty($value))
		$rc = 0;
	else {
		if ($keyOfListUserData != NULL)
			$list = $this->session->userData->getValue($page, $keyOfListUserData);
		else
			$list = $this->session->configuration->getValue($page . '.' . $keyOfListConfig);
		$list = explode(OPT_SEPARATOR, $list);
		$rc = -1;
		$count = count($list);
		for ($ix = 0; $ix < $count; $ix++) {
			if (strcmp($value, $list[$ix]) == 0){
				$rc = $ix;
				break;
			}
		}
	}
	return $rc;
}
/** Gets the index of value in a list.
 *
 * The value and the list are in the user data.
 *
 * @param $page					name of the page
 * @param $keyOfCurrent			the key of the value in the user data
 * @param $keyOfListUserData	NULL or the key of the list in the user data
 * @param $keyOfListConfig		NULL or the key of the list in the configuration
 * @return -1: key is not in the list. Otherwise: the index in the list
 */
function indexOfList($page, $keyOfCurrent, $keyOfListUserData, $keyOfListConfig){
	$value = $this->session->userData->getValue($page, $keyOfCurrent);
	if ($keyOfListUserData != NULL)
		$list = $this->session->userData->getValue($page, $keyOfListUserData);
	else
		$list = $this->session->configuration->getValue($page . '.' . $keyOfListConfig);
	$list = explode(OPT_SEPARATOR, $list);
	$rc = -1;
	$count = count($list);
	for ($ix = 0; $ix < $count; $ix++) {
		if (strcmp($value, $list[$ix]) == 0){
			$rc = $ix;
			break;
		}
	}
	return $rc;
}
/** Reads the template file and puts the templates into an array.
 *
 * @param $filename		name of the definition
 * @param $parts		array for the result
 * Format: Sequence of templates. Template: template name (uppercase) ':' template body
 * Example:
 * <pre>
 * SELECTOR:
 *    <input type="submit" name="selector">
 * COMBO:
 *    <select name="mountonboot" size="1">###OPT_MOUNTONBOOT###</select>
 * </pre>
 */
function readParts($filename = null, &$parts){
	$this->session->trace(TRACE_RARE, "readParts: $filename");
	$all = $this->session->readFile($filename);
	if (strpos($all, "\r") > 0)
		$all = str_replace("\r", "", $all);
	$start = 0;
	$lastName = "";
	$pos = 0;
	while(preg_match('/^([-A-Z_0-9]+):$/m', $all, $hit, 0, $start)){
		$name = $hit[1];
		$pos = strpos($all, $name . ':', $start);
		if ($start > 0){
			$len = $pos - 1 - $start;
			// Skip last empty line:
			$tail = substr($all, $pos - 2, 2);
			if (strcmp($tail, "\n\n") == 0)
				$len -= 1;
			elseif (strcmp($tail, "\r\n") && strcmp(substr($all, $pos - 3, 1), "\n") == 0)
				$len -= 2;
			$parts[$lastName] = substr($all, $start, $len);
		}
		$start = $pos + strlen($name) + 2;
		$lastName = $name;
	}
	$parts[$lastName] = substr($all, $start, $pos - 1 - $start);
}
/** Reads the template file and puts the templates into an array.
 *
 * @param $filename		name of the definition
 */
function readHtmlTemplates($filename = null){
	if ($filename == null)
		$filename = $this->session->pageDir . $this->name . '.parts.content.txt';
	$this->readParts($filename, $this->parts);
}
/** Removes a given marker.
 *
 * @param $namePart		the name part to replace
 */
function clearPart($namePart){
	$this->content = str_replace("###PART_${namePart}###", '', $this->content);
}
/** Replaces a given marker by a given template.
 *
 * @param $namePart		the name part to replace
 * @param $nameTemplate the name of the replacement.
 * 						Allowed characters: uppercase, '_' and '-'.
 * 						If NULL $namePart will be taken
 */
function replacePartWithTemplate($namePart, $nameTemplate = NULL){
	if ($nameTemplate == NULL)
		$nameTemplate = $namePart;
	$template = $this->parts[$nameTemplate];
	$this->content = str_replace("###PART_${namePart}###", $template, $this->content);
}
/**
 * Replaces a given merker by a given content.
 *
 * @param $namePart	the name part to replace
 * @param $content	the content as a string
 */
function replacePartWithContent($namePart, $content){
	$template = $this->parts[$nameTemplate];
	$this->content = str_replace("###PART_${namePart}###", $content, $this->content);
}
/**
 * Initializes the method to switch a html area between 2 states by a button.
 *
 * @param $namePart			the name of the part.
 * @param $defaultPart		the part which will be displayed at first.
 */
function initializeButtonSwitchedParts($namePart, $defaultPart){
	$val = $this->getUserData($namePart);
	if (empty($val))
		$this->setUserData($namePart, $defaultPart);
}
/** Handles a part of the html page which can switched between 2 states with a button.
 *
 * Conventions:
 * The button's name must be button_$namePart
 * The button's text must be a configuration variable named
 * 'txt_button_$nameTemplate1' or 'txt_button_$nameTemplate2'
 * The marker of the button's text must be ###txt_button_$namePart###.
 *
 * @param $namePart			the name of the part.
 * 							Allowed characters: uppercase, '-', '_'
 * @param $nameTemplate1	the name of the first replacement
 * @param $nameTemplate2	the name of the second replacement
 */
function setButtonSelectedPart($namePart, $nameTemplate1, $nameTemplate2){
	$nameTemplate = $this->getUserData($namePart);
	if (empty($nameTemplate))
		$nameTemplate = $nameTemplate1;
	$this->setUserData($namePart, $nameTemplate);
	$this->replacePartWithTemplate($namePart, $nameTemplate);
	if (strcmp($nameTemplate, $nameTemplate1) == 0)
		$key = "txt_button_${nameTemplate2}";
	else
		$key = "txt_button_${nameTemplate1}";
	$value = $this->getConfiguration($key);
	$this->content = str_replace("###txt_button_${namePart}###", $value, $this->content);
}
/** Handles a button push event of a part selector button.
 *
 * Conventions:
 * The button's name must be button_$namePart
 * The button's text must be a configuration variable named
 * 'txt_button_$nameTemplate1' or 'txt_button_$nameTemplate2'
 * The marker of the button's text must be ###txt_button_$namePart###.
 *
 * @param $namePart			the name of the part.
 * 							Allowed characters: uppercase, '-', '_'
 * @param $nameTemplate1	the name of the first replacement
 * @param $nameTemplate2	the name of the second replacement
 */
function switchPartByButton($namePart, $nameTemplate1, $nameTemplate2){
	$value = $this->getUserData($namePart);
	if (empty($value) || strcmp($value, $nameTemplate1) == 0)
		$value = $nameTemplate2;
	else
		$value = $nameTemplate1;
	$this->setUserData($namePart, $value);
}
/**
 * Copies the $body and replaces the $macro in each instance
 * by the value extracted from the $values.
 *
 * @param $body the text which is copied
 * @param $makro the placeholder for the values in body without ###
 * @param $values a list of values starting with the separator, e.g. ';Paris;F;France'
 */
function replaceMakroList($body, $makro, $values){
	$array = explode(substr($values, 0, 1), $values);
	$rc = '';
	for($ii = 1; $ii < count($array); $ii++){
		$value = $array[$ii];
		$body2 = str_replace($makro, $value, $body);
		$rc .= $body2;
	}
	return $rc;
}
/**
 * Builds a XML table using templates, macros and values.
 *
 * @param $headers a string of the header columns
 * @param $rows a string of the data rows
 * @param $prefixMakro this is the part of all makros defining the table: X_TABLE X_HEADERS X_HEADER, X_ROWS, X_ROW, X_COL, X_VAL
 *
 * @return the text of the resulting table
 */
function buildTable($headers, $rows, $prefixMakro, $firstRow = 1){
	$rc = $this->parts[$prefixMakro . '_TABLE'];

	$headerBody = $this->replaceMakroList($this->parts[$prefixMakro . '_HEADER'], '###' . $prefixMakro . '_VAL###', $headers);
	$rc = str_replace('###' . $prefixMakro . '_HEADERS###', $headerBody, $rc);

	$arrayRows = explode(substr($rows, 0, 1), $rows);
	$rowBody = '';
	$bodyRow = $this->parts[$prefixMakro . '_ROW'];
	for ($ii = $firstRow; $ii < count($arrayRows); $ii++){
		$colVals = $arrayRows[$ii];
		$colsBody = $this->replaceMakroList($this->parts[$prefixMakro . '_COL'], '###' . $prefixMakro . '_VAL###', $colVals);
		$rowBody .= str_replace('###' . $prefixMakro . '_COLS###', $colsBody, $bodyRow);
	}
	$rc = str_replace('###' . $prefixMakro . '_ROWS###', $rowBody, $rc);
	return $rc;
}

}
