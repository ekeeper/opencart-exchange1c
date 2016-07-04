<?php

$remote_user = !empty($_SERVER["REMOTE_USER"]) 
? $_SERVER["REMOTE_USER"] : $_SERVER["REDIRECT_REMOTE_USER"];
$strTmp = base64_decode(substr($remote_user,6));
if ($strTmp)
list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $strTmp);

// Configuration
require_once('../admin/config.php');

// Version
$version = 'UNKNOWN';
$handle = fopen(DIR_APPLICATION.'/index.php','r');
if ($handle){
    while (($buffer = fgets($handle,4096)) !== false) if(preg_match('/VERSION/',$buffer) && !(preg_match('/^[\\t ]*\/\//',$buffer)) && (preg_match('/\d+(\.\d+)*/', $buffer, $version) > 0)) break;
    if (is_array($version)) {
        define('VERSION', $version[0]);
    }else die("Version is $version");
}

if (file_exists('../vqmod/vqmod.php')) {
	require_once('../vqmod/vqmod.php');
	VQMod::bootup();

	require_once(VQMod::modCheck(DIR_SYSTEM . 'startup.php'));
	require_once(VQMod::modCheck(DIR_SYSTEM . 'library/currency.php'));
	require_once(VQMod::modCheck(DIR_SYSTEM . 'library/user.php'));
	require_once(VQMod::modCheck(DIR_SYSTEM . 'library/weight.php'));
	require_once(VQMod::modCheck(DIR_SYSTEM . 'library/length.php'));
}
else {
	require_once(DIR_SYSTEM . 'startup.php');
	require_once(DIR_SYSTEM . 'library/currency.php');
	require_once(DIR_SYSTEM . 'library/user.php');
	require_once(DIR_SYSTEM . 'library/weight.php');
	require_once(DIR_SYSTEM . 'library/length.php');
}

// Registry
$registry = new Registry();

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);

// Event
$event = new Event($registry);
$registry->set('event', $event);

// Settings
$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting");
 
foreach ($query->rows as $setting) {
	if (!$setting['serialized']) {
		$config->set($setting['key'], $setting['value']);
	} else {
		$setting['value'] = str_replace( array("\r", "\n"), array('\r', '\n'), $setting['value'] );
		$setting_unserialized = json_decode($setting['value'], true);
		$config->set($setting['key'], $setting_unserialized === NULL ? unserialize($setting['value']) : $setting_unserialized);
	}
}

// Log 
$log = new Log($config->get('config_error_filename'));
$registry->set('log', $log);

// Error Handler
function error_handler($errno, $errstr, $errfile, $errline) {
	global $config, $log;

	if (0 === error_reporting()) return TRUE;
	switch ($errno) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Notice';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Warning';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Fatal Error';
			break;
		default:
			$error = 'Unknown';
			break;
	}

	if ($config->get('config_error_display')) {
		echo '<b>' . $error . '</b>: ' . $errstr . ' in <b>' . $errfile . '</b> on line <b>' . $errline . '</b>';
	}
	
	if ($config->get('config_error_log')) {
		$log->write('PHP ' . $error . ':  ' . $errstr . ' in ' . $errfile . ' on line ' . $errline);
	}

	return TRUE;
}

// Error Handler
set_error_handler('error_handler');

// Request
$request = new Request();
$registry->set('request', $request);

// Response
$response = new Response();
$response->addHeader('Content-Type: text/html; charset=utf-8');
$registry->set('response', $response); 

// Session
$registry->set('session', new Session());

// Cache
$registry->set('cache', new Cache('file'));

// Document
$registry->set('document', new Document());

// Language
$languages = array();

$query = $db->query("SELECT * FROM " . DB_PREFIX . "language"); 

foreach ($query->rows as $result) {
	$languages[$result['code']] = array(
		'language_id'	=> $result['language_id'],
		'name'		=> $result['name'],
		'code'		=> $result['code'],
		'locale'	=> $result['locale'],
		'directory'	=> $result['directory'],
		'filename'	=> (array_key_exists('filename',$result) ? $result['filename'] : false)
	);
}

$config->set('config_language_id', $languages[$config->get('config_admin_language')]['language_id']);

$language = new Language($languages[$config->get('config_admin_language')]['directory']);
$language->load($languages[$config->get('config_admin_language')]['filename']);	
$registry->set('language', $language);

// Currency
$registry->set('currency', new Currency($registry));

// Weight
$registry->set('weight', new Weight($registry));

// Length
$registry->set('length', new Length($registry));

// User
$registry->set('user', new User($registry));

// Front Controller
$controller = new Front($registry);


// Router
if (isset($request->get['mode']) && $request->get['type'] == 'catalog') {

	switch ($request->get['mode']) {
		case 'checkauth':
			$action = new Action('module/exchange1c/modeCheckauth');
		break;
		
		case 'init':
			$action = new Action('module/exchange1c/modeCatalogInit');
		break;

		case 'file':
			$action = new Action('module/exchange1c/modeFile');
		break;

		case 'import':
			$action = new Action('module/exchange1c/modeImport');
		break;

		default:
			echo "success\n";
	}
	
} else if (isset($request->get['mode']) && $request->get['type'] == 'sale') {
	
	switch ($request->get['mode']) {
		case 'checkauth':
			$action = new Action('module/exchange1c/modeCheckauth');
		break;
		
		case 'init':
			$action = new Action('module/exchange1c/modeSaleInit');
		break;

		case 'query':
			$action = new Action('module/exchange1c/modeQueryOrders');
		break;

		case 'success':
			$action = new Action('module/exchange1c/modeOrdersChangeStatus');
		break;

		default:
			echo "success\n";
	}

} else {
	echo "success\n";
	exit;
}

// Dispatch
if (isset($action)) {
	$controller->dispatch($action, new Action('error/not_found'));
}

// Output
$response->output();
?>
