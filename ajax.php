<?php
session_start();
if (isset($_REQUEST['debug'])) error_reporting(-1);
else error_reporting(0);

include_once(dirname(__FILE__).'/inc/config.php');
include_once(dirname(__FILE__).'/inc/functions.php');
include_once(dirname(__FILE__).'/inc/settings.php');
include_once(dirname(__FILE__).'/inc/icdb.php');
$icdb = new ICDB(DB_HOST, DB_HOST_PORT, DB_NAME, DB_USER, DB_PASSWORD, TABLE_PREFIX);

install();
get_options();

$url_base = $options['website_url'];
if (substr($url_base, strlen($url_base)-1, 1) != '/') $ulr_base .= '/';
if (substr($url_base, 0, 2) == '//') $url_base = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off' ? 'http:' : 'https:').$url_base;
else {
	if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') $url_base = str_replace('https://', 'http://', $url_base);
	else $url_base = str_replace('http://', 'https://', $url_base);
}

$active_user = array();
$session_id = '';
if (isset($_COOKIE['url-user'])) {
	$session_id = preg_replace('/[^a-zA-Z0-9]/', '', $_COOKIE['url-user']);
	$session_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."sessions WHERE session_id = '".$session_id."' AND created + valid_period > '".time()."'");
	if ($session_details) {
		$active_user = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE id = '".$session_details['user_id']."' AND deleted = '0' AND blocked = '0' AND activated = '1'");
		if ($active_user) {
			$icdb->query("UPDATE ".$icdb->prefix."sessions SET created = '".time()."', ip = '".$_SERVER['REMOTE_ADDR']."' WHERE session_id = '".$session_id."'");
			$tmp = unserialize($active_user['options']);
			if (is_array($tmp)) $active_user_options = array_merge($user_options, $tmp);
			else $active_user_options = $user_options;
		}
	}
}
if (!isset($active_user['id'])) unset($active_user);

if (isset($_POST['action'])) {
	switch ($_POST['action']) {
		case 'signup':
			if ($active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You are already signed in.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if ($options['close_registration'] == 'yes') {
				$return_object = new stdClass();
				$return_object->message = 'Registration is not allowed.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}

			$email = trim(stripslashes($_POST['email']));
			$password = trim(stripslashes($_POST['password']));
			$password2 = trim(stripslashes($_POST['password2']));

			$error = '';
			if ($email == '') {
				$error = 'E-mail address is required.';
			} else if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $email)) {
				$error = 'You have entered an invalid e-mail address.';
			} else if (sizeof($email) > 64) {
				$error = 'Your email is too long.';
			} else if ($password == '') {
				$error = 'Password is required.';
			} else if ($password != $password2) {
				$error = 'Password and its confirmation must be equal.';
			}
			if (!empty($error)) {
				$return_object = new stdClass();
				$return_object->message = $error;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE email = '".$icdb->escape_string($email)."' AND deleted = '0'");
			if ($user_details && $user_details['activated'] == 1) {
				$return_object = new stdClass();
				$return_object->message = 'Account <strong>'.htmlspecialchars($email, ENT_QUOTES).'</strong> already registered.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$activation_code = random_string(16);
			$password_hash = md5($password.$options['salt']);

			if ($user_details) {
				$icdb->query("UPDATE ".$icdb->prefix."users SET activation_code = '".$activation_code."', password = '".$password_hash."', created = '".time()."' WHERE id = '".$user_details['id']."'");
			} else {
				$api_key = random_string(16);
				$icdb->query("INSERT INTO ".$icdb->prefix."users (name, email, password, activation_code, api_key, options, created, blocked, activated, deleted) VALUES ('', '".$icdb->escape_string($email)."', '".$password_hash."', '".$activation_code."', '".$api_key."', '".serialize($user_options)."', '".time()."', '0', '0', '0')");
			}


			$keys = array('{email}', '{e-mail}', '{activation_link}', '{website_name}');
			$vals = array($email, $email, $url_base.'?activate='.$activation_code, $options['website_name']);
			$body = str_replace($keys, $vals, $options['activation_email_body']);
			try {
				ic_mail($email, $options['activation_email_subject'], $body);
			} catch (Exception $e) {
				$return_object = new stdClass();
				$return_object->message = $e->getMessage();
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}

			$return_object = new stdClass();
			$return_object->message = 'Account successfully created. Check e-mail to complete registration.';
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'signin':
			if ($active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You are already signed in.';
				$return_object->redirect_url = $url_base;
				$return_object->status = 'OK';
				echo json_encode($return_object);
				exit;
			}
			
			$email = trim(stripslashes($_POST['email']));
			$password = trim(stripslashes($_POST['password']));

			$error = '';
			if ($email == '') {
				$error = 'E-mail address is required.';
			} else if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $email)) {
				$error = 'You have entered an invalid e-mail address.';
			} else if (sizeof($email) > 64) {
				$error = 'Your email is too long.';
			} else if ($password == '') {
				$error = 'Password is required.';
			}
			if (!empty($error)) {
				$return_object = new stdClass();
				$return_object->message = $error;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$password_hash = md5($password.$options['salt']);
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE email = '".$icdb->escape_string($email)."' AND password = '".$password_hash."' AND deleted = '0'");
			if (!$user_details) {
				$return_object = new stdClass();
				$return_object->message = 'Invalid e-mail or password. Try again.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			} else if ($user_details && $user_details['blocked'] == 1) {
				$return_object = new stdClass();
				$return_object->message = 'Account <strong>'.htmlspecialchars($email, ENT_QUOTES).'</strong> was blocked by administrator.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			} else if ($user_details && $user_details['activated'] == 0) {
				$return_object = new stdClass();
				$return_object->message = 'Account <strong>'.htmlspecialchars($email, ENT_QUOTES).'</strong> was not activated.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$session_id = random_string(16);
			$icdb->query("INSERT INTO ".$icdb->prefix."sessions (ip, user_id, session_id, created, valid_period) VALUES ('".$_SERVER['REMOTE_ADDR']."', '".$user_details['id']."', '".$session_id."', '".time()."', '3600')");
			setcookie('url-user', $session_id, time()+3600*24*180);
			
			$return_object = new stdClass();
			$return_object->message = 'Welcome to our service!';
			$return_object->redirect_url = $url_base;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'remind':
			if ($active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You are already signed in.';
				$return_object->redirect_url = $url_base;
				$return_object->status = 'OK';
				echo json_encode($return_object);
				exit;
			}
			$email = trim(stripslashes($_POST['email']));

			$error = '';
			if ($email == '') {
				$error = 'E-mail address is required.';
			} else if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $email)) {
				$error = 'You have entered an invalid e-mail address.';
			} else if (sizeof($email) > 64) {
				$error = 'Your email is too long.';
			}
			if (!empty($error)) {
				$return_object = new stdClass();
				$return_object->message = $error;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (DEMO_MODE && strtolower($email) == 'demo@website.com') {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled for demo user.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE email = '".$icdb->escape_string($email)."' AND deleted = '0'");
			if (!$user_details) {
				$return_object = new stdClass();
				$return_object->message = 'Account <strong>'.htmlspecialchars($email, ENT_QUOTES).'</strong> does not exist.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			} else if ($user_details && $user_details['blocked'] == 1) {
				$return_object = new stdClass();
				$return_object->message = 'Account <strong>'.htmlspecialchars($email, ENT_QUOTES).'</strong> was blocked by administrator.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$password = random_string(12);
			$password_hash = md5($password.$options['salt']);
			$icdb->query("UPDATE ".$icdb->prefix."users SET password = '".$password_hash."' WHERE id = '".$user_details['id']."'");
			
			$keys = array('{email}', '{e-mail}', '{password}', '{website_name}');
			$vals = array($email, $email, $password, $options['website_name']);
			$body = str_replace($keys, $vals, $options['resetpassword_email_body']);
			
			try {
				ic_mail($email, $options['resetpassword_email_subject'], $body);
			} catch (Exception $e) {
				$icdb->query("UPDATE ".$icdb->prefix."users SET password = '".$user_details['password']."' WHERE id = '".$user_details['id']."'");
				$return_object = new stdClass();
				$return_object->message = $e->getMessage();
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}

			$return_object = new stdClass();
			$return_object->message = 'New password was sent to <strong>'.$email.'</strong>.';
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'save_profile':
			if (!$active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in to compete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (DEMO_MODE && strtolower($active_user['email']) == 'demo@website.com') {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled for demo user.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$active_user_options = unserialize($active_user['options']);
			$new_password = trim(stripslashes($_POST['new_password']));
			$new_password2 = trim(stripslashes($_POST['new_password2']));
			$old_password = trim(stripslashes($_POST['old_password']));
			
			$password_hash = md5($old_password.$options['salt']);
			if ($password_hash != $active_user['password']) {
				$error = 'You must submit correct current password to update profile details.';
			} else if ($new_password != '' && $new_password != $new_password2) {
				$error = 'New password and its confirmation must be equal.';
			}

			if (!empty($error)) {
				$return_object = new stdClass();
				$return_object->message = $error;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if ($new_password != '') $password_hash = md5($new_password.$options['salt']);
			
			$icdb->query("UPDATE ".$icdb->prefix."users SET password = '".$password_hash."', options = '".serialize($active_user_options)."' WHERE id = '".$active_user['id']."'");

			$return_object = new stdClass();
			$return_object->message = 'Profile details successfully updated.';
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'update_api_key':
			if (!$active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in to compete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (DEMO_MODE && strtolower($active_user['email']) == 'demo@website.com') {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled for demo user.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$api_key = random_string(16);
			$icdb->query("UPDATE ".$icdb->prefix."users SET api_key = '".$api_key."' WHERE id = '".$active_user['id']."'");

			$return_object = new stdClass();
			$return_object->message = 'API Key successfully updated.';
			$return_object->api_key = $api_key;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'add_url':
			if ($options['only_registered'] == 'yes' && !$active_user) {
				$return_object = new stdClass();
				$return_object->message = 'URL shortening is available for registerd users only.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$url = trim(stripslashes($_POST['url']));
			if (substr(strtolower($url), 0, 7) != "http://" && substr(strtolower($url), 0, 8) != "https://") $url = 'http://'.$url;
			$error = '';
			if ($url == '') {
				$error = 'Hey, seems you forgot to paste a link.';
			} else if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url)) {
				$error = 'Are you sure you submitted the correct URL?';
			} else if (sizeof($url) > 255) {
				$error = 'Hey, seems URL is too long.';
			}

			if (!empty($error)) {
				$return_object = new stdClass();
				$return_object->message = $error;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (substr(strtolower($url), 0, strlen($url_base)) == strtolower($url_base)) {
				$return_object = new stdClass();
				$return_object->message = 'Hey. Seems this URL is short enough. ;-)';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}

			if ($active_user) $user_id = $active_user['id'];
			else $user_id = 0;
			$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE url = '".$icdb->escape_string($url)."' AND deleted = '0' AND user_id = '".$user_id."'");

			if ($url_details) {
				$icdb->query("UPDATE ".$icdb->prefix."urls SET created = '".time()."' WHERE id = '".$url_details['id']."'");
				$url_code = $url_details['url_code'];
			} else {
				$icdb->query("INSERT INTO ".$icdb->prefix."urls (user_id, url, url_code, redirects, created, blocked, deleted) VALUES ('".$user_id."', '".$icdb->escape_string($url)."', '', '0', '".time()."', '0', '0')");
				$url_code = url_code($icdb->insert_id);
				$icdb->query("UPDATE ".$icdb->prefix."urls SET url_code = '".$url_code."' WHERE id = '".$icdb->insert_id."'");
			}

			$htaccess = url_rewrite();

			$return_object = new stdClass();
			if ($active_user) {
				$return_object->status = 'OK2';
			} else $return_object->status = 'OK';
			$return_object->url = $url_base.($htaccess ? '' : '?u=').$url_code;
			
			echo json_encode($return_object);
			
			exit;
			break;
		
		case 'reload_urls':
			if (!$active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in to compete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (isset($_POST["s"])) {
				$search_query = urldecode(trim(stripslashes($_POST["s"])));
				$search_query = str_replace($url_base.'?u=', '', $search_query);
				$search_query = str_replace($url_base, '', $search_query);
			} else $search_query = "";
			if (isset($_POST["p"])) $p = intval($_POST["p"]);
			else $p = 1;
			$return_object = new stdClass();
			$return_object->status = 'OK';
			$return_object->content = urls_page($search_query, $p);
	
			echo json_encode($return_object);
			exit;
			break;

		case 'delete':
			if (!$active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in to compete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (DEMO_MODE && strtolower($active_user['email']) == 'demo@website.com') {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled for demo user.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE id = '".$id."' AND deleted = '0' AND user_id = '".$active_user['id']."'");
			if (!$url_details) {
				$return_object = new stdClass();
				$return_object->message = 'Monitoring URL not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."urls SET deleted = '1' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully deleted.';
			//$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'block':
			if (!$active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in to compete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE id = '".$id."' AND deleted = '0' AND user_id = '".$active_user['id']."'");
			if (!$url_details) {
				$return_object = new stdClass();
				$return_object->message = 'Monitoring URL not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."urls SET blocked = '1' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully blocked.';
			//$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'unblock':
			if (!$active_user) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in to compete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			
			$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE id = '".$id."' AND deleted = '0' AND user_id = '".$active_user['id']."'");
			if (!$url_details) {
				$return_object = new stdClass();
				$return_object->message = 'Monitoring URL not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."urls SET blocked = '0' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully unblocked.';
			//$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		default:
			break;
	}
}
?>