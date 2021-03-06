<?php
/**
 * The main module. Initializes the session, chooses and starts the needed plugin.
 */
#set_magic_quotes_runtime(0);
error_reporting(E_ALL);
include "base/session.php";
include "base/page.php";
include "base/userdata.php";
include "base/configuration.php";

$session = new Session();
$wait = $session->userData->getValue('wait', 'answer');
if (! empty ($wait) && file_exists($wait)){
	if (strcmp($session->page, 'wait') != 0)
		$session->gotoPage('wait', 'install.wait');
}
$pagename = $session->page;
if (empty($pagename)){
	$home = $session->configuration->getValue('static.home');
	if (! empty($home))
		$session->gotoPage($home, 'install.no_page_def');
	else {
		$home = 'home';
		$session->trace(TRACE_RARE, 'No page found');
	}
}
$isStatic = $session->isStaticPage($pagename);
$subdir = $isStatic || strcmp($pagename, 'wait') == 0 ? 'base/' : 'plugins/';
$session->pageDir = $session->homeDir . $subdir;
if ($isStatic){
	$pageDefinition = $session->pageDir . 'staticpage.php';
	$pagename = 'static';
} else {
	$pageDefinition = $session->pageDir . $pagename . 'page.php';
}

if (! file_exists($pageDefinition)){
	$session->trace(TRACE_RARE, "Not found: $pageDefinition");
	$home = $session->configuration->getValue('static.home');
	if (empty($home))
		$home = 'home';
	$session->gotoPage('home', 'install.no_page_def');
} else {
	include_once $pageDefinition;
	$classname = strtoupper(substr($pagename, 0, 1)) . substr($pagename, 1) . 'Page';
	$session->trace(TRACE_RARE, 'main: ' . $classname);
	$page = new $classname($session);

	$page->clearAllFieldErrors();

	$button = $session->hasButton();
	if (! empty($button)){
		$session->trace(TRACE_RARE, "button: $button");
		if ($page->onButtonClick($button))
			$button = "";
	}
	$session->trace(TRACE_RARE, "button: $button");
	if (empty($button)){
		$template = $page->getTemplateName();
		$pageText = $session->readFileFromConfig($template, true);
		$pageText = replaceTextMarkers($session, $pageText, $pagename);
		$pageText = replaceInTemplate($session, $pagename, $pageText);

		$page->build();
		$page->replaceTextMarkers();
		$page->replaceMarkers();
		$core = $page->getContent();
		$errors = $session->getErrorMessage();
		if (! empty($errors))
			$core .= $errors;
		$pageText = str_replace('###CONTENT###', $core, $pageText);
		$menu = $session->configuration->getValue('.menu_always');
		if ($isStatic || ! empty($menu)){
			include "base/menu.php";
			$text = $page->buildMenu();
			$pageText = str_replace('###MENU###', $text, $pageText);
		}

		$pageText = replaceGlobalMarkers($session, $pageText);
		echo $pageText;
		$session->userData->write();
	}
}
/** Replaces the markers in the page template.
 *
 * @param $session 	the session info
 * @param $pagename	the current page name
 * @param $pageText	the template text
 * @return the template with expanded markers
 */
function replaceInTemplate(&$session, $pagename, $pageText){
	$prevPage = $session->getPrevPage($pagename);
	if ($prevPage == NULL)
		$button = '&nbsp;';
	else
		$button = $session->configuration->getValue('.gui.button.prev');
	$pageText = str_replace('###BUTTON_PREV###', $button, $pageText);

	// Is this the last page?
	$nextPage = $session->getNextPage($pagename);
	if ($nextPage == NULL)
		$button = '&nbsp;';
	else
		$button = $session->configuration->getValue('.gui.button.next');
	$pageText = str_replace('###BUTTON_NEXT###', $button, $pageText);
	$msg = $session->getMessage();
	$pageText = str_replace("###INFO###", $msg, $pageText);
	$value = $session->configuration->getValue('.TXT_TITLE');
	$pageText = str_replace('###TXT_TITLE###', $value, $pageText);
	return $pageText;
}
/** Replaces the markers in the page template.
 *
 * @param $session 	the session info
 * @param $pagename	the current page name
 * @return the template with expanded markers
 */
function replaceGlobalMarkers(&$session, $pageText){
	$pageText = str_replace('###URL_STATIC###', $session->urlStatic, $pageText);
	$pageText = str_replace('###URL_DYNAMIC###', $session->absScriptUrl, $pageText);
	$pageText = str_replace('###URL_FORM###', $session->urlForm, $pageText);
	$pageText = str_replace('###META_DYNAMIC###', $session->metaDynamic, $pageText);
	$pageText = str_replace('###LANGUAGE###', $session->language, $pageText);

	return $pageText;
}
	/** Replaces the text markers in the content with the translated text from the configuration.
	 */
function replaceTextMarkers(&$session, $pageText, $plugin){
	$start = 0;
	$end = 0;
	$rc = '';
	$session->trace(TRACE_RARE, 'replaceTextMarkers:');
	while ( ($start = strpos($pageText, '###txt_', $start)) > 0){
		$rc .= substr($pageText, $end, $start - $end);
		$end = $start + 7 + strspn($pageText, '_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXZ01234567890', $start + 7);
		$end += strspn($pageText, '#', $end, 3);
		$marker = substr($pageText, $start, $end - $start);
		$key = trim($marker, '#');
		$value = $session->i18n($plugin, $key, '?%!');
		if (strcmp($value, '?%!') == 0)
			$value = $session->i18n('', $key, $key);
		#$session->trace(TRACE_FINE, "Makro $key=$value");
		if (strncmp($value, '<xml>', 5) == 0)
			$rc .= substr($value, 5);
		else
			$rc .= htmlentities($value, ENT_NOQUOTES, $session->charset);
		$start = $end;
	}
	$rc .= substr($pageText, $end);
	return $rc;
}

?>