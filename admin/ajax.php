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

$url_admin = ((empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] == 'off') ? 'http://' : 'https://').$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
if (($pos = strpos($url_admin, '?')) !== false) $url_admin = substr($url_admin, 0, $pos);
if (($pos = strrpos($url_admin, basename(__FILE__))) !== false) $url_admin = substr($url_admin, 0, $pos);

$session_id = '';
$is_admin = false;
if (isset($_COOKIE['url-admin'])) {
	$session_id = preg_replace('/[^a-zA-Z0-9]/', '', $_COOKIE['url-admin']);
	$session_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."sessions WHERE session_id = '".$session_id."' AND user_id = '0' AND created + valid_period > '".time()."'");
	if ($session_details) {
		$icdb->query("UPDATE ".$icdb->prefix."sessions SET created = '".time()."', ip = '".$_SERVER['REMOTE_ADDR']."' WHERE session_id = '".$session_id."'");
		$is_admin = true;
	}
}

if (isset($_POST['action'])) {
	switch ($_POST['action']) {
		case 'signin':
			if ($is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You are already signed in.';
				$return_object->redirect_url = $url_admin;
				$return_object->status = 'OK';
				echo json_encode($return_object);
				exit;
			}
			$login = trim(stripslashes($_POST['login']));
			$password = trim(stripslashes($_POST['password']));

			$error = '';
			if ($login == '') {
				$error = 'Login is required.';
			} else if (sizeof($login) > 64) {
				$error = 'Your login is too long.';
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
			$password_hash = md5($password);
			if ($login != $options['login'] || $password_hash != $options['password']) {
				$return_object = new stdClass();
				$return_object->message = 'Invalid login or password. Try again.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$session_id = random_string(16);
			$icdb->query("INSERT INTO ".$icdb->prefix."sessions (ip, user_id, session_id, created, valid_period) VALUES ('".$_SERVER['REMOTE_ADDR']."', '0', '".$session_id."', '".time()."', '900')");
			setcookie('url-admin', $session_id, time()+3600*24*180);
			
			$return_object = new stdClass();
			if (DEMO_MODE) $return_object->message = 'Admin panel operates in demo mode!';
			else $return_object->message = 'Welcome to admin panel!';
			$_SESSION['info'] = $return_object->message;
			$return_object->redirect_url = $url_admin;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'save_settings':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			populate_options();
			$errors = check_options();
			if (isset($_POST['password'])) $old_password = trim(stripslashes($_POST['password']));
			else $old_password = '';			
			if (md5($old_password) != $options['password']) {
				$errors[] = 'You must submit correct current password to update settings.';
			}
			if (isset($_POST['new_password'])) $password = trim(stripslashes($_POST['new_password']));
			else $password = '';
			if (isset($_POST['new_password2'])) $confirm_password = trim(stripslashes($_POST['new_password2']));
			else $confirm_password = '';
			if (!empty($password)) {
				if ($password == $confirm_password) {
					$options['password'] = md5($password);
				} else {
					$errors[] = 'New password and its confirmation are not equal.';
				}
			}
			$login = trim(stripslashes($_POST['login']));
			if (!empty($login)) $options['login'] = $login;
			
			if (!empty($errors)) {
				$return_object = new stdClass();
				$return_object->message = implode('<br />', $errors);
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			update_options();
			
			$return_object = new stdClass();
			$return_object->message = 'Settings successfully updated.';
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'add_user':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
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
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE email = '".$icdb->escape_string($email)."' AND deleted = '0'");
			if ($user_details) {
				$return_object = new stdClass();
				$return_object->message = 'Account <strong>'.htmlspecialchars($email, ENT_QUOTES).'</strong> already registered.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$activation_code = random_string(16);
			$api_key = random_string(16);
			$password_hash = md5($password.$options['salt']);
			$icdb->query("INSERT INTO ".$icdb->prefix."users (name, email, password, activation_code, api_key, options, created, blocked, activated, deleted) VALUES ('', '".$icdb->escape_string($email)."', '".$password_hash."', '".$activation_code."', '".$api_key."', '".serialize($user_options)."', '".time()."', '0', '1', '0')");
			
			$return_object = new stdClass();
			$return_object->message = 'Account successfully created and activated.';
			$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'save_profile':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE id = '".$id."' AND deleted = '0'");
			if (!$user_details) {
				$return_object = new stdClass();
				$return_object->message = 'User not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$tmp = unserialize($user_details['options']);
			if (is_array($tmp)) $user_options = array_merge($user_options, $tmp);
			
			$new_password = trim(stripslashes($_POST['new_password']));
			$new_password2 = trim(stripslashes($_POST['new_password2']));
			
			if (empty($new_password)) {
				$error = 'Empty password is not allowed.';
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
			else $password_hash = $user_details['password'];
			
			$icdb->query("UPDATE ".$icdb->prefix."users SET password = '".$password_hash."', options = '".serialize($user_options)."' WHERE id = '".$user_details['id']."'");

			$return_object = new stdClass();
			$return_object->message = 'Profile details successfully updated.';
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;
			
		case 'update_api_key':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE id = '".$id."' AND deleted = '0'");
			if (!$user_details) {
				$return_object = new stdClass();
				$return_object->message = 'User not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$api_key = random_string(16);
			$icdb->query("UPDATE ".$icdb->prefix."users SET api_key = '".$api_key."' WHERE id = '".$user_details['id']."'");

			$return_object = new stdClass();
			$return_object->message = 'API Key successfully updated.';
			$return_object->api_key = $api_key;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'add_url':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			if (isset($_POST['email'])) {
				$email = trim(stripslashes($_POST['email']));
				if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $email) && $email != '') {
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
				$id = 0;
				$sql = "SELECT * FROM ".$icdb->prefix."users WHERE email = '".$icdb->escape_string($email)."' AND deleted = '0'";
			} else {
				$email = '';
				$id = intval($_POST['id']);
				$sql = "SELECT * FROM ".$icdb->prefix."users WHERE id = '".intval($_POST['id'])."' AND deleted = '0'";
			}
			$user_details = $icdb->get_row($sql);
			if (!$user_details) $id = 0;
			else $id = $user_details['id'];
			
			$url = trim(stripslashes($_POST['url']));
			if (substr(strtolower($url), 0, 7) != "http://" && substr(strtolower($url), 0, 8) != "https://") $url = 'http://'.$url;
			$error = '';
			if ($url == '') {
				$error = 'URL is required.';
			} else if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url)) {
				$error = 'You have entered an URL.';
			} else if (sizeof($url) > 250) {
				$error = 'URL is too long.';
			} else {
				$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE url = '".$icdb->escape_string($url)."' AND deleted = '0' AND user_id = '".$id."'");
				if ($url_details) {
					$icdb->query("UPDATE ".$icdb->prefix."urls SET created = '".time()."' WHERE id = '".$url_details['id']."'");
					$return_object = new stdClass();
					$return_object->message = 'URL already exists. Find it on the top of the list.';
					$_SESSION['success'] = $return_object->message;
					$return_object->status = 'OK';
					echo json_encode($return_object);
					exit;
				}
			}

			if (!empty($error)) {
				$return_object = new stdClass();
				$return_object->message = $error;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("INSERT INTO ".$icdb->prefix."urls (user_id, url, url_code, redirects, created, blocked, deleted) VALUES ('".$id."', '".$icdb->escape_string($url)."', '', '0', '".time()."', '0', '0')");
			$code = url_code($icdb->insert_id);
			$icdb->query("UPDATE ".$icdb->prefix."urls SET url_code = '".$code."' WHERE id = '".$icdb->insert_id."'");

			$return_object = new stdClass();
			$return_object->message = 'URL successfully added and shortened.';
			$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);
			
			exit;
			break;

		case 'delete_user':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$_SESSION['error'] = $return_object->message;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE id = '".$id."' AND deleted = '0'");
			if (!$user_details) {
				$return_object = new stdClass();
				$return_object->message = 'User not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."users SET deleted = '1' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully deleted.';
			$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'block_user':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$_SESSION['error'] = $return_object->message;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE id = '".$id."' AND deleted = '0'");
			if (!$user_details) {
				$return_object = new stdClass();
				$return_object->message = 'User not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."users SET blocked = '1' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully blocked.';
			$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'unblock_user':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$_SESSION['error'] = $return_object->message;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE id = '".$id."' AND deleted = '0'");
			if (!$user_details) {
				$return_object = new stdClass();
				$return_object->message = 'User not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."users SET blocked = '0' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully unblocked.';
			$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'delete_url':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$_SESSION['error'] = $return_object->message;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE id = '".$id."' AND deleted = '0'");
			if (!$url_details) {
				$return_object = new stdClass();
				$return_object->message = 'Shortened URL not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."urls SET deleted = '1' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully deleted.';
			$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'block_url':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$_SESSION['error'] = $return_object->message;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE id = '".$id."' AND deleted = '0'");
			if (!$url_details) {
				$return_object = new stdClass();
				$return_object->message = 'Shortened URL not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."urls SET blocked = '1' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully blocked.';
			$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		case 'unblock_url':
			if (DEMO_MODE) {
				$return_object = new stdClass();
				$return_object->message = 'Operation disabled in DEMO mode.';
				$_SESSION['error'] = $return_object->message;
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			if (!$is_admin) {
				$return_object = new stdClass();
				$return_object->message = 'You must be signed in as administrator to complete this action.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$id = intval($_POST['id']);
			
			$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE id = '".$id."' AND deleted = '0'");
			if (!$url_details) {
				$return_object = new stdClass();
				$return_object->message = 'Shortened URL not found.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			
			$icdb->query("UPDATE ".$icdb->prefix."urls SET blocked = '0' WHERE id = '".$id."'");

			$return_object = new stdClass();
			$return_object->message = 'Record successfully unblocked.';
			$_SESSION['success'] = $return_object->message;
			$return_object->status = 'OK';
			echo json_encode($return_object);

			exit;
			break;

		default:
			break;
	}
}
?>