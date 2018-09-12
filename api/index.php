<?php
session_start();
if (isset($_REQUEST['debug'])) error_reporting(-1);
else error_reporting(0);

include_once(dirname(dirname(__FILE__)).'/inc/config.php');
include_once(dirname(dirname(__FILE__)).'/inc/functions.php');
include_once(dirname(dirname(__FILE__)).'/inc/settings.php');
include_once(dirname(dirname(__FILE__)).'/inc/icdb.php');
$icdb = new ICDB(DB_HOST, DB_HOST_PORT, DB_NAME, DB_USER, DB_PASSWORD, TABLE_PREFIX);

install();
get_options();

$url_base = $options['website_url'];
if (substr($url_base, strlen($url_base)-1, 1) != '/') $ulr_base .= '/';

if ($options['disable_api'] == 'yes') {
	$return_object = new stdClass();
	$return_object->status = 'ERROR';
	$return_object->message = 'API Disabled.';
	echo json_encode($return_object);
	exit;
}

$error = '';
if (isset($_GET['key'])) {
	$api_key = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['key']);
	$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE api_key = '".$icdb->escape_string($api_key)."' AND deleted = '0' AND blocked = '0' AND activated = '1'");
	if (!$user_details) $error = 'Invalid API Key.';
} else $error = 'Invalid API Key.';
if (!empty($error)) {
	$return_object = new stdClass();
	$return_object->status = 'ERROR';
	$return_object->message = $error;
	echo json_encode($return_object);
	exit;
}

$error = '';
if (isset($_GET['key'])) {
	$url = rawurldecode($_GET['url']);
//	if (substr(strtolower($url), 0, 7) != "http://" && substr(strtolower($url), 0, 8) != "https://") $url = 'http://'.$url;
	if ($url == '') $error = 'Invalid URL.';
	else if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url)) $error = 'Invalid URL.';
	else if (sizeof($url) > 255) $error = 'URL is too long.';
} else $error = 'Invalid URL.';
if (!empty($error)) {
	$return_object = new stdClass();
	$return_object->status = 'ERROR';
	$return_object->message = $error;
	echo json_encode($return_object);
	exit;
}

$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE url = '".$icdb->escape_string($url)."' AND deleted = '0' AND user_id = '".$user_details['id']."'");
if ($url_details) {
	$icdb->query("UPDATE ".$icdb->prefix."urls SET created = '".time()."' WHERE id = '".$url_details['id']."'");
	$url_code = $url_details['url_code'];
} else {
	$icdb->query("INSERT INTO ".$icdb->prefix."urls (user_id, url, url_code, redirects, created, blocked, deleted) VALUES ('".$user_details['id']."', '".$icdb->escape_string($url)."', '', '0', '".time()."', '0', '0')");
	$url_code = url_code($icdb->insert_id);
	$icdb->query("UPDATE ".$icdb->prefix."urls SET url_code = '".$url_code."' WHERE id = '".$icdb->insert_id."'");
}

$htaccess = url_rewrite();
$return_object = new stdClass();
$return_object->status = 'OK';
$return_object->url = $url_base.($htaccess ? '' : '?u=').$url_code;
echo json_encode($return_object);
?>