<?php
/**
 * Builds the core content of a static page.
 * Implements a plugin.
 *
 * @author hm
 */
class StaticPage extends Page{

	var $page;

	/** Constructor.
	 *
	 * @param $session
	 */
	function __construct(&$session){
		parent::__construct($session, 'static');
		$this->page = $session->page;
	}
	/**
	 * Builds the name of a static page for a given language.
	 *
	 * @param $language	the language, e.g. "de"
	 * @param $page the page name (without language)
	 *
	 * @return the name of the static page file
	 */
	function buildPageNameWithLanguage($language, $page){
		$rc = $this->session->homeDir . 'static/' . $language . '/' . $page
			. '-' . $language . '.htm';
		return $rc;
	}
	/**
	 * Translate old fashioned links into usable links.
	 *
	 *  The language part and the ".htm" will be thrown away.
	 *
	 *  Example:<br>
	 *  href="page1-en.htm#abc"<br>
	 *  will be translated into<br>
	 *  href="page1#abc"
	 *
	 * @param $line the line containing with (or without) old fashioned links
	 * @return the line with translated links
	 */
	function translateInternalRefs($line){
		$line = preg_replace('/href="([\w-.]*?)-[a-z]{2}[.]htm/',
				'href="$1', $line);
		return $line;
	}
	/**
	 * Reads the content of a static page.
	 *
	 * Extracts the pure content from a full html file.
	 * The header and the menu will be ignored.
	 *
	 * @return the pure content of a HTML file
	 */
	function readContent(){
		$fn = $this->buildPageNameWithLanguage($this->session->language,
				$this->page);
		$found = file_exists($fn);
		if (! $found && strcmp($this->session->language,
				$this->session->languagePure) != 0){
			$fn = $this->buildPageNameWithLanguage(
					$this->session->languagePure, $this->page);
			$found = file_exists($fn);
		}
		if (! $found)
			$fn = $this->buildPageNameWithLanguage('en', $this->page);
		$this->session->trace(TRACE_RARE, "readContent($fn)");
		$file = file($fn);
		$marker = $this->getConfiguration('content.start');
		if (empty($marker))
			$marker = 'id="main-page"';
		$rc = '';
		$ignore = true;
		while ( list($key, $line) = each($file)){
			if ($ignore && ! strpos($line, $marker) === false)
				$ignore = false;
			if (! $ignore){
				if (! strpos($line, '</body>') === false)
					break;
				if (! (strpos($line, 'href="') === false))
					$line = $this->translateInternalRefs($line);
				$rc .= $line;
			}
		}
		$countDivs = $this->getConfiguration('content.end.ignoreddivs');
		while($countDivs-- > 0){
			$ix = strrpos($rc, '</div>');
			if (! ($ix === false))
				$rc = substr($rc, 0, $ix);
		}
		return $rc;
	}
	/** Builds the core content of the page.
	 *
	 * Overwrites the method in the baseclass.
	 */
	function build(){
		$this->readContentTemplate();
		$this->readHtmlTemplates($this->session->homeDir . 'config/menu.parts.txt');
		$this->content = $this->readContent();
	}
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
		$rc = $menu->buildHtml($this->parts);
		return $rc;
	}

	/** Will be called on a button click.
	 *
	 * @param $button	the name of the button
	 * @return false: a redirection will be done. true: the current page will be redrawn
	 */
	function onButtonClick($button){
		$rc = true;
		if (strcmp($button, 'button_next') == 0){
			$rc = $this->navigation(false);
		} elseif (strcmp($button, 'button_start') == 0){
			$answer = NULL;
			$options = SVOPT_BACKGROUND;
			$command = 'startgui';
			//APPL, ARGS, USER, OPTS
			$params = array('echo', '#!PROJ!#', 'root', 'console');
			$this->session->exec($answer, $options, $command, $params, 0);
		} else {
			$this->session->log("unknown button: $button");
		}
		return $rc;
	}
}