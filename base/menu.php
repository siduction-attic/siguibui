<?php
/**
 * Defines a base class for a menu builder.
 *
 * A menu builder composes  reads a menu description file
 * and builds the menu in HTML.
 *
 * @author hm
 */
class MenuItem {
	var $level;
	var $id;
	var $title;
	var $link;
	/// null or array of MenuItem
	var $submenus;
	/// MenuItem
	var $parent;
	var $active;
	function __construct($level, $id, $title, $link){
		$this->level = $level;
		$this->id = $id;
		$this->title = $title;
		$this->link = $link;
		$this->submenus = null;
		$this->active = false;
		$this->parent = null;
	}
	function addItem($item){
		if ($this->submenus == null)
			$this->submenus = array($item);
		else
			array_push($this->submenus, $item);
		$item->parent = $this;
	}
	function findLink($link){
		$rc = null;
		if (strcmp($this->link, $link) == 0)
			$rc = $this;
		else if ($this->submenus != null){
			while($rc == null && (list($key, $item) = each($this->submenus))){
				$rc = $this->findLink($item);
			}
		}
		return $rc;
	}
}

class Menu{
	var $name;
	var $session;
	var $menuDefinition;
	var $prefixPartMenu;
	var $menu;
	var $expanded;
	/// the HTML definitions of the menu items
	var $parts;

	/** Constructor.
	 *
	 * @param $session			the session info
	 * @param $name				the name of the menu
	 * @param $menuDefinition	prefix of the name of the menu definition file
	 * @param $prefixPartMenu	prefix of page snippets for html code
	 * 							of the menu
	 * @param $expanded			true: all members of the menu tree are visible<br>
	 * 							false: only the current members and its siblings
	 * 							are visible.
	 */
	function __construct(&$session, $name, $menuDefinition, $prefixPartMenu,
			$expanded){
		$this->name = $name;
		$this->session = $session;
		$this->menuDefinition = $menuDefinition;
		$this->prefixPartMenu = $prefixPartMenu;
		$this->menu = array();
		$this->parts = null;
	}
	/**
	 * Reads the menu from the definition file.
	 *
	 * The result is in $this->menu.
	 */
	function read(){
		$currentLink = $this->session->pageAndBookmark;
		$fn = $this->session->homeDir . 'config/' . $this->menuDefinition
			. '_' . $this->session->language . '.conf';
		if (! file_exists($fn))
			$fn = $this->session->homeDir . 'config/' . $this->menuDefinition
			. '_en.conf';
		$file = File($fn);
		if ($file == null)
			$this->session->trace(TRACE_CONFIG, "not found: " . $fn);
		else{
			// maximal 3 menu levels:
			$maxLevel = 3;
			$menuStack = array();
			for ($ii = 0; $ii < $maxLevel; $ii++)
				array_push($menuStack, null);
			$lastLevel = 0;
			$lineNo = 0;
			while (list($key, $line) = each($file)) {
				$lineNo++;
				if (substr($line, 0, 1) == '*'){
					list($indent, $id, $link, $title) = preg_split('/\s+/', $line, 4);
					$title = trim($title);
					$level = strlen($indent) - 1;
					if ($level >= $maxLevel)
						$this->session->logError("$fn-$lineNo: indent level too large: $level<br>$line");
					elseif ($indent > $lastLevel + 1){
						$this->session->logError("$fn-$lineNo: indent level incorrect: $level/$lastLevel<br>$line");
					} else {
						$item = new MenuItem($level, $id, $title, $link);
						$menuStack[$level] = $item;
						if ($level == 0)
							array_push($this->menu, $item);
						else
							$menuStack[$level - 1]->addItem($item);
						for ($ii = $level + 1; $ii < $maxLevel; $ii++)
							$menuStack[$ii] = null;
						if (strcasecmp($currentLink, $link) == 0){
							for ($ii = 0; $ii <= $level; $ii++)
								$menuStack[$ii]->active = true;
						}

					}
					$lastLevel = $level;
				}
			}
		}
	}
	/**
	 * Builds the html code of a menu in one level.
	 *
	 * @param $level	the level of the menu items: 0..N-1
	 * @param $items	the array with the items of this level
	 */
	function buildOneLevel($level, $items, $parentIsActive){
		$rc = '';
		if ($items != null && count($items) > 0){
			$entries = '';
			$name = $this->prefixPartMenu . 'LEVEL_' . $level;
			$template = $this->parts[$name];
			foreach ($items as $item){
				$name = $this->prefixPartMenu . 'ENTRY_' . $level;
				$templateEntry = $this->parts[$name];
				$templateEntry = str_replace("###link###", $item->link, $templateEntry);
				$templateEntry = str_replace("###title###", $item->title, $templateEntry);

				$classCurrent = $item->active ? rtrim($this->parts['CLASS_CURRENT']) : '';
				$templateEntry = str_replace("###class_current###", $classCurrent, $templateEntry);

				$submenus = '';
				if ($parentIsActive
						|| ($item->active || $this->expanded) && count($item->submenus) > 0){
					$submenus = $this->buildOneLevel($level + 1, $item->submenus, $item->active);
				}
				$templateEntry = str_replace("###SUBMENUS###", $submenus, $templateEntry);

				$templateId = strcmp($item->id, '-') == 0 ? '' : rtrim($this->parts['ID']);
				if (!empty ($templateId))
					$templateId = str_replace('###id###', $item->id, $templateId);
				$templateEntry = str_replace("###id###", $templateId, $templateEntry);

				$entries .= $templateEntry;
			}
			$rc = str_replace('###ENTRIES###', $entries, $template);
		}
		return $rc;
	}
	/**
	 * Builds the HTML code of the menu.
	 *
	 * @return the html code of the menu
	 */
	function buildHtml(&$parts){
		$this->parts = $parts;
		$rc = $this->buildOneLevel(0, $this->menu, false);
		return $rc;
	}

}
