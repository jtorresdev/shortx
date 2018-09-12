<?php
session_start();
if (isset($_REQUEST['debug'])) error_reporting(-1);
else error_reporting(0);
if (!file_exists(dirname(dirname(__FILE__)).'/inc/config.php')) {
	header('Location: install.php');
	exit;
}
include_once(dirname(dirname(__FILE__)).'/inc/config.php');
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASSWORD')) {
	header('Location: install.php');
	exit;
}
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

$notification = array();
if (isset($_SESSION['error']) && !empty($_SESSION['error'])) {
	$notification = array('type' => 'error', 'message' => $_SESSION['error']);
	unset($_SESSION['error']);
} else if (isset($_SESSION['success']) && !empty($_SESSION['success'])) {
	$notification = array('type' => 'success', 'message' => $_SESSION['success']);
	unset($_SESSION['success']);
} else if (isset($_SESSION['info']) && !empty($_SESSION['info'])) {
	$notification = array('type' => 'info', 'message' => $_SESSION['info']);
	unset($_SESSION['info']);
}

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

$pages = array ();
$deafult_page = '';
$admin_pages = array (
	'settings' => array('caption' => 'Settings', 'title' => 'General Settings', 'menu' => true),
	'users' => array('caption' => 'Users', 'title' => 'Registered Users', 'menu' => true),
	'urls' => array('caption' => 'URLs', 'title' => 'Shortened URLs', 'menu' => true),
	'profile' => array('caption' => 'Profile', 'title' => 'Profile', 'menu' => false),
	'statistics' => array('caption' => 'Statistics', 'title' => 'Statistics', 'menu' => false)
);
$deafult_admin_page = 'settings';

if ($is_admin) {
	if (isset($_GET['page'])) {
		$page = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['page']);
		if (!array_key_exists($_GET['page'], $admin_pages)) $page = $deafult_admin_page;
	} else $page = $deafult_admin_page;
} else {
	$page = 'home';
}

if ($is_admin) {
	if (isset($_GET['action'])) {
		switch ($_GET['action']) {
			case 'logout':
				if (!empty($session_id)) {
					$icdb->query("UPDATE ".$icdb->prefix."sessions SET valid_period = '0' WHERE session_id = '".$session_id."'");
				}
				$_SESSION['info'] = 'You are signed out. See you later.';
				header('Location: '.$url_admin);
				exit;
				break;
				
			default:
				break;
		}
	}
}

?>
<!DOCTYPE html>
<head>
	<meta charset="utf-8">
	<title>Admin Panel - <?php echo $options["website_name"]; ?></title>
	<meta name="description" content="Check if your website is up.">
	<link rel="stylesheet" href="<?php echo ($url_base ? $url_base : '../'); ?>css/admin.css" type="text/css">
	<link rel="stylesheet" href="<?php echo ($url_base ? $url_base : '../'); ?>css/jNotify.jquery.css" type="text/css">
	<link rel="stylesheet" href="<?php echo ($url_base ? $url_base : '../'); ?>css/dark-hive/jquery-ui.css" type="text/css" />
	<link rel="stylesheet" href="<?php echo ($url_base ? $url_base : '../'); ?>css/morris.css">
	<script src="<?php echo ($url_base ? $url_base : '../'); ?>js/jquery-1.10.1.min.js" type="text/javascript"></script>
	<script src="<?php echo ($url_base ? $url_base : '../'); ?>js/jquery-ui-1.10.3.custom.min.js" type="text/javascript"></script>
	<script src="<?php echo ($url_base ? $url_base : '../'); ?>js/jNotify.jquery.js" type="text/javascript"></script>
	<script src="<?php echo ($url_base ? $url_base : '../'); ?>js/admin.js" type="text/javascript"></script>
	<script src="<?php echo ($url_base ? $url_base : '../'); ?>js/raphael.js"></script>
	<script src="<?php echo ($url_base ? $url_base : '../'); ?>js/morris.min.js"></script>	
</head>
<body>
	<div class="topbar">
		<div class="topbar-center">
			<h1>Admin Panel</h1>
			<?php echo $is_admin ? '<ul class="pull-right"><li><a class="pull-right" href="'.$url_admin.'?action=logout">Sign out</a></li></ul>' : ''; ?>
			<ul class="nav">
<?php
				if ($is_admin) {
					foreach ($admin_pages as $key => $value) {
						if ($value['menu']) echo '
				<li'.($key == $page ? ' class="active"' : '').'><a href="'.$url_admin.'?page='.$key.'">'.$value['caption'].'</a></li>';
					}
				}
?>
			</ul>
		</div>
	</div>
	<div class="front-container">
		<div class="front-bg">
			<img class="front-image" src="<?php echo ($url_base ? $url_base : '../'); ?>img/bg.jpg">
		</div>
<?php
if ($is_admin) {
?>
<?php
	switch ($page) {
		case 'settings':
			if (DEMO_MODE) {
				$options['smtp_server'] = '<hidden>';
				$options['smtp_username'] = '<hidden>';
				$options['smtp_password'] = '<hidden>';
				$options['mail_from_email'] = '<hidden>';
			}
			echo '
		<div class="front-page">
		<div class="front-content">
			<h2><strong>'.$admin_pages[$page]['title'].'</strong></h2>
			<div class="front-settings">
			<form action="#" id="form-settings" class="settings" method="post" onsubmit="return save_settings();">
				<table class="table-settings">
					<tr>
						<th style="width: 200px;">Website title</th>
						<td><input type="text" id="settings-website-name" name="website_name" class="widefat" placeholder="Website title" tabindex="1" title="Website title" value="'.htmlspecialchars($options['website_name'], ENT_QUOTES).'"></td>
					</tr>
					<tr>
						<th style="width: 200px;">Website URL</th>
						<td><input type="text" id="settings-website-url" name="website_url" class="widefat" placeholder="Website URL" tabindex="1" title="Website URL" value="'.htmlspecialchars($options['website_url'], ENT_QUOTES).'"></td>
					</tr>
					<tr>
						<th>Website header</th>
						<td><input type="text" id="settings-website-header" name="website_header" class="widefat" placeholder="Website header" tabindex="2" title="Website header" value="'.htmlspecialchars($options['website_header'], ENT_QUOTES).'"></td>
					</tr>
					<tr>
						<th>Website slogan</th>
						<td><input type="text" id="settings-website-slogan" name="website_slogan" class="widefat" placeholder="Website slogan" tabindex="3" title="Website slogan" value="'.htmlspecialchars($options['website_slogan'], ENT_QUOTES).'"></td>
					</tr>
					<tr>
						<th>Close registration</th>
						<td class="line-height-28"><input type="hidden" id="settings-close-registration" name="close_registration" value="'.$options['close_registration'].'"><label for="settings-close-registration" class="checkbox">Disable new user registration</label></td>
					</tr>
					<tr>
						<th>Disable anonymous shortening</th>
						<td class="line-height-28"><input type="hidden" id="settings-only-registered" name="only_registered" value="'.$options['only_registered'].'"><label for="settings-only-registered" class="checkbox">Shortening is allowed only for registered users</label></td>
					</tr>
					<tr>
						<th>Disable API</th>
						<td class="line-height-28"><input type="hidden" id="settings-disable-api" name="disable_api" value="'.$options['disable_api'].'"><label for="settings-disable-api" class="checkbox">Disable URL shortening API</label></td>
					</tr>
					<tr><td colspan="2"><hr></td></tr>
					<tr>
						<th>Mailing method</th>
						<td>
							<select id="settings-mail-method" name="mail_method" class="widefat" tabindex="5" title="Mailing method" onchange="switch_mail_settings();">';
			foreach ($mail_methods as $key => $value) {
				echo '
								<option value="'.$key.'"'.($key == $options['mail_method'] ? ' selected="selected"' : '').'>'.htmlspecialchars($value, ENT_QUOTES).'</option>';
			}
			echo '
							</select>
							<br /><em>All messages to users are sent using this mailing method.</em>
						</td>
					</tr>
					<tr class="mail-method-mail">
						<th>Sender name</th>
						<td>
							<input type="text" id="settings-mail-from-name" name="mail_from_name" class="widefat" placeholder="Sender name" tabindex="6" title="Sender name" value="'.htmlspecialchars($options['mail_from_name'], ENT_QUOTES).'">
							<br /><em>All messages to users are sent using this name as "FROM:" header value.</em>
						</td>
					</tr>
					<tr class="mail-method-mail">
						<th>Sender e-mail</th>
						<td>
							<input type="text" id="settings-mail-from-email" name="mail_from_email" class="widefat" placeholder="Sender e-mail" tabindex="7" title="Sender e-mail" value="'.htmlspecialchars($options['mail_from_email'], ENT_QUOTES).'">
							<br /><em>All messages to users are sent using this e-mail as "FROM:" header value. It is recommended to set existing e-mail address.</em>
						</td>
					</tr>
					<tr class="mail-method-smtp">
						<th>Encryption</th>
						<td>
							<select id="settings-smtp-secure" name="smtp_secure" class="widefat" style="width: 80px;" tabindex="5" title="SMTP Connection security">';
			foreach ($smtp_secures as $key => $value) {
				echo '
								<option value="'.$key.'"'.($key == $options['smtp_secure'] ? ' selected="selected"' : '').'>'.htmlspecialchars($value, ENT_QUOTES).'</option>';
			}
			echo '
							</select>
							<br /><em>SMTP connection encryption system.</em>
						</td>
					</tr>
					<tr class="mail-method-smtp">
						<th>SMTP server</th>
						<td>
							<input type="text" id="settings-smtp-server" name="smtp_server" class="widefat" placeholder="SMTP server" tabindex="8" title="SMTP server" value="'.htmlspecialchars($options['smtp_server'], ENT_QUOTES).'">
							<br /><em>Hostname of the mail server.</em>
						</td>
					</tr>
					<tr class="mail-method-smtp">
						<th>SMTP port number</th>
						<td>
							<input type="text" id="settings-smtp-port" name="smtp_port" class="number" placeholder="Port #" tabindex="9" title="SMTP port number" value="'.htmlspecialchars($options['smtp_port'], ENT_QUOTES).'">
						</td>
					</tr>
					<tr class="mail-method-smtp">
						<th>SMTP username</th>
						<td>
							<input type="text" id="settings-smtp-username" name="smtp_username" class="widefat" placeholder="SMTP username" tabindex="10" title="SMTP server" value="'.htmlspecialchars($options['smtp_username'], ENT_QUOTES).'">
							<br /><em>Username to use for SMTP authentication.</em>
						</td>
					</tr>
					<tr class="mail-method-smtp">
						<th>SMTP password</th>
						<td>
							<input type="text" id="settings-smtp-password" name="smtp_password" class="widefat" placeholder="SMTP password" tabindex="11" title="SMTP server" value="'.htmlspecialchars($options['smtp_password'], ENT_QUOTES).'">
							<br /><em>Password to use for SMTP authentication.</em>
						</td>
					</tr>
					<tr><td colspan="2"><hr></td></tr>
					<tr>
						<th>Account activation e-mail</th>
						<td>
							<input type="text" id="settings-activation-email-subject" name="activation_email_subject" class="widefat" placeholder="Subject of account activation e-mail" tabindex="17" title="Subject of account activation e-mail" value="'.htmlspecialchars($options['activation_email_subject'], ENT_QUOTES).'">
							<br /><em>Subject of account activation e-mail. The e-mail message is sent to active newly registered account.</em>
						</td>
					</tr>
					<tr>
						<th></th>
						<td>
							<textarea style="height: 120px;" id="settings-activation-email-body" name="activation_email_body" class="widefat" placeholder="Body of account activation e-mail" tabindex="18" title="Body of account activation e-mail">'.htmlspecialchars($options['activation_email_body'], ENT_QUOTES).'</textarea>
							<br /><em>Body of account activation e-mail. Allowed keywords: {e-mail}, {activation_link}, {website_name}.</em>
						</td>
					</tr>
					<tr>
						<th>Reset passsword e-mail</th>
						<td>
							<input type="text" id="settings-resetpassword-email-subject" name="resetpassword_email_subject" class="widefat" placeholder="Subject of reset password e-mail" tabindex="19" title="Subject of reset password e-mail" value="'.htmlspecialchars($options['resetpassword_email_subject'], ENT_QUOTES).'">
							<br /><em>Subject of reset password e-mail. The e-mail message is sent if user forgot password.</em>
						</td>
					</tr>
					<tr>
						<th></th>
						<td>
							<textarea style="height: 120px;" id="settings-resetpassword-email-body" name="resetpassword_email_body" class="widefat" placeholder="Body of account activation e-mail" tabindex="20" title="Body of account activation e-mail">'.htmlspecialchars($options['resetpassword_email_body'], ENT_QUOTES).'</textarea>
							<br /><em>Body of reset password e-mail. Allowed keywords: {e-mail}, {password}, {website_name}.</em>
						</td>
					</tr>
					<tr><td colspan="2"><hr></td></tr>
					<tr>
						<th>Admin login</th>
						<td><input type="text" id="settings-login" name="login" class="widefat" placeholder="Admin login" tabindex="96" title="Admin login" value="'.htmlspecialchars($options['login'], ENT_QUOTES).'"></td>
					</tr>
					<tr>
						<th>New admin password</th>
						<td>
							<input type="password" id="settings-new-password" name="new_password" class="widefat" placeholder="New admin password" tabindex="97" title="New admin password">
							<br /><em>Leave the field blank if you do not want to change the password.</em>
						</td>
					</tr>
					<tr>
						<th>Confirm password</th>
						<td><input type="password" id="settings-new-password2" name="new_password2" class="widefat" placeholder="Confirm new password" tabindex="98" title="Confirm new password"></td>
					</tr>
					<tr><td colspan="2"><hr></td></tr>
					<tr>
						<th>Current password</th>
						<td><input type="password" id="settings-old-password" name="password" class="widefat" placeholder="Current password" tabindex="99" title="Current password"></td>
					</tr>
					<tr>
						<th></th>
						<td class="align-right">
							<input type="hidden" name="action" value="save_settings">
							<button type="submit" class="signup-btn-dark" tabindex="100">Save changes</button>
						</td>
					</tr>
				</table>
			</form>
			<div class="loading-dark"></div>
			</div>
		</div>
		</div>
		<script>
			switch_mail_settings();
		</script>
		';
			break;

		case 'users':
			$total = $icdb->get_var("SELECT COUNT(*) AS total FROM ".$icdb->prefix."users WHERE deleted = '0'");
			$totalpages = ceil($total/RECORDS_PER_PAGE);
			if ($totalpages == 0) $totalpages = 1;
			if (isset($_GET["p"])) $p = intval($_GET["p"]);
			else $p = 1;
			if ($p < 1 || $p > $totalpages) $p = 1;
			$switcher = page_switcher($url_admin.'?page=users', $p, $totalpages);
			$rows = $icdb->get_rows("SELECT t1.*, t2.total_urls FROM ".$icdb->prefix."users t1 LEFT JOIN (SELECT COUNT(*) AS total_urls, user_id FROM ".$icdb->prefix."urls WHERE deleted = '0' GROUP BY user_id) t2 ON t2.user_id = t1.id WHERE t1.deleted = '0' ORDER BY t1.created DESC LIMIT ".(($p-1)*RECORDS_PER_PAGE).", ".RECORDS_PER_PAGE);
			echo '
		<div class="front-page">
		<div class="front-content">
			<h2><strong>'.$admin_pages[$page]['title'].'</strong></h2>
			<div class="front-table">
				<form action="#" id="form-add-user" class="profile" method="post" onsubmit="return add_user();">
					<table class="table-settings" style="width: 70%;">
						<tr>
							<th class="align-right">Add user</th>
							<td><input type="text" id="users-email" name="email" class="widefat" placeholder="E-mail" tabindex="1" title="E-mail"></td>
							<td><input type="text" id="users-password" name="password" class="widefat" placeholder="Password" tabindex="2" title="Password"></td>
							<td>
								<input type="hidden" name="action" value="add_user">
								<button type="submit" class="signup-btn-dark" tabindex="3">Add user</button>
							</td>
						</tr>
					</table>
				</form>
				'.($totalpages > 1 ? '<div class="paginator-top">'.$switcher.'</div>' : '').'
				<table class="table-listing">
					<tr>
						<th>E-mail</th>
						<th>Registered</th>
						<th style="width: 100px;">URLs</th>
						<th style="width: 100px;">Status</th>
						<th style="width: 80px;">Actions</th>
					</tr>';
			if (sizeof($rows) > 0) {
				foreach ($rows as $row) {
					if ($row['blocked'] == 1) {
						$status = 'BLOCKED';
						$color = '#F00';
					} else if ($row['activated'] != 1) {
						$status = 'NOT ACTIVE';
						$color = '#44F';
					} else {
						$color = '#0F0';
						$status = 'ACTIVE';
					}
					$email = $row['email'];
					if (DEMO_MODE) {
						if (($pos = strpos($email, "@")) !== false) {
							$name = substr($email, 0, strpos($email, "@"));
							$email = substr($name, 0, 1).'*****'.substr($email, $pos);
						}
					}
					$active_urls = $icdb->get_var("SELECT COUNT(*) AS total_urls FROM ".$icdb->prefix."urls WHERE deleted = '0' AND blocked = '0' AND user_id = '".$row['id']."'");
					echo '
					<tr>
						<td>'.htmlspecialchars($email, ENT_QUOTES).'</td>
						<td>'.date('Y-m-d H:i:s', $row['created']).'</td>
						<td>'.intval($active_urls).' / '.intval($row['total_urls']).'</td>
						<td id="status-'.$row['id'].'"><span style="color: '.$color.';">'.$status.'</em></td>
						<td>
							<a href="'.$url_admin.'?page=profile&id='.$row['id'].'" title="Edit user profile"><img src="'.$url_base.'img/edit.png" alt="Edit user profile" border="0"></a>
							<a href="'.$url_admin.'?page=urls&id='.$row['id'].'" title="Show shortened URLs"><img src="'.$url_base.'img/urls.png" alt="Show shortened URLs" border="0"></a>
							'.($row['blocked'] == 0 ? '<a href="#" title="Block user" onclick="return block_user('.$row['id'].');"><img src="'.$url_base.'img/block.png" alt="Block user" border="0"></a>' : '<a href="#" title="Unblock user" onclick="return unblock_user('.$row['id'].');"><img src="'.$url_base.'img/unblock.png" alt="Unblock user" border="0"></a>').'
							<a href="#" title="Delete user" onclick="return delete_user('.$row['id'].');"><img src="'.$url_base.'img/delete.png" alt="Delete user" border="0"></a>
						</td>
					</tr>';
				}
			} else {
				echo '
					<tr>
						<td colspan="5" class="no-records">No records found.</td>
					</tr>';
			}
			echo '
				</table>
				'.($totalpages > 1 ? '<div class="paginator-bottom">'.$switcher.'</div>' : '').'
				<div class="legend">
					<ul>
						<li><img src="'.$url_base.'img/edit.png" alt="Edit profile" border="0" style="vertical-align: middle;"> Edit profile</li>
						<li><img src="'.$url_base.'img/urls.png" alt="Show shortened URLs" border="0" style="vertical-align: middle;"> Show shortened URLs</li>
						<li><img src="'.$url_base.'img/block.png" alt="Block record" border="0" style="vertical-align: middle;"> Block user</li>
						<li><img src="'.$url_base.'img/unblock.png" alt="Unblock record" border="0" style="vertical-align: middle;"> Unblock user</li>
						<li><img src="'.$url_base.'img/delete.png" alt="Delete record" border="0" style="vertical-align: middle;"> Delete user</li>
					</ul>
				</div>
				<div class="loading-dark"></div>
			</div>
		</div>
		</div>';
			break;

		case 'profile':
			if (isset($_GET["id"])) $id = intval($_GET["id"]);
			else $id = 0;
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE id = '".$id."' AND deleted = '0'");
			if ($user_details) {
				$tmp = unserialize($user_details['options']);
				if (is_array($tmp)) $user_options = array_merge($user_options, $tmp);
				$email = $user_details['email'];
				$api_key = $user_details['api_key'];
				if (DEMO_MODE) {
					if (($pos = strpos($email, "@")) !== false) {
						$name = substr($email, 0, strpos($email, "@"));
						$email = substr($name, 0, 1).'*****'.substr($email, $pos);
					}
					$api_key = '<hidden>';
				}
				echo '
		<div class="front-page">
		<div class="front-content">
			<h2><strong>'.$admin_pages[$page]['title'].':</strong> '.htmlspecialchars($email, ENT_QUOTES).'</h2>
			<div class="front-settings">
			<form action="#" id="form-profile" class="profile" method="post" onsubmit="return save_profile();">
				<table class="table-settings" style="width: 60%;">
					<tr>
						<th>API Key</th>
						<td>
							<input type="text" id="profile-api-key" class="widefat" onclick="this.focus();this.select();" readonly="readonly" value="'.$api_key.'" title="API Key">
							<br /><em>This key is used to shorten URLs through API.</em>
						</td>
						<td style="width: 20px; vertical-align: top; padding-top: 12px;">
							<a href="#" title="Update API Key" onclick="return update_api_key('.$user_details['id'].');"><img src="'.$url_base.'img/refresh.png" alt="Update API Key" border="0"></a>
						</td>
					</tr>
					<tr><td colspan="3"><hr></td></tr>
					<tr>
						<th>New password</th>
						<td><input type="password" id="profile-new-password" name="new_password" class="widefat" placeholder="New password" tabindex="2" title="New password"></td>
					</tr>
					<tr>
						<th>Confirm password</th>
						<td><input type="password" id="profile-new-password2" name="new_password2" class="widefat" placeholder="Confirm new password" tabindex="3" title="Confirm new password"></td>
					</tr>
					<tr><td colspan="2"><hr></td></tr>
					<tr>
						<th></th>
						<td class="align-right">
							<input type="hidden" name="action" value="save_profile">
							<input type="hidden" name="id" value="'.$id.'">
							<button type="submit" class="signup-btn-dark" tabindex="5">Save changes</button>
						</td>
					</tr>
				</table>
			</form>
			<div class="loading-dark"></div>
			</div>
		</div>
		</div>';
			} else {
				echo '
			<div class="front-page">
			<div class="front-content">
				<h2><strong>'.$admin_pages[$page]['title'].'</strong></h2>
				<div class="front-table">
					<table class="table-listing">
						<tr>
							<td class="no-records">Invalid URL.</td>
						</tr>
					</table>
				</div>
			</div>
			</div>';
			}
			break;

		case 'urls':
			if (isset($_GET["id"])) $id = intval($_GET["id"]);
			else $id = 0;
			$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE id = '".$id."' AND deleted = '0'");
			if ($user_details) {
				$tmp = unserialize($user_details['options']);
				if (is_array($tmp)) $user_options = array_merge($user_options, $tmp);
			}
			$total = $icdb->get_var("SELECT COUNT(*) AS total FROM ".$icdb->prefix."urls WHERE deleted = '0'".($user_details ? " AND user_id = '".$user_details['id']."'" : ""));
			$totalpages = ceil($total/RECORDS_PER_PAGE);
			if ($totalpages == 0) $totalpages = 1;
			if (isset($_GET["p"])) $p = intval($_GET["p"]);
			else $p = 1;
			if ($p < 1 || $p > $totalpages) $p = 1;
			$switcher = page_switcher($url_admin.'?page=urls'.($user_details ? '&id='.$user_details['id'] : ''), $p, $totalpages);
			$rows = $icdb->get_rows("SELECT t1.*, t2.email AS user_email FROM ".$icdb->prefix."urls t1 LEFT JOIN ".$icdb->prefix."users t2 ON t2.id = t1.user_id WHERE t1.deleted = '0'".($user_details ? " AND t1.user_id = '".$user_details['id']."'" : "")." ORDER BY t1.created DESC LIMIT ".(($p-1)*RECORDS_PER_PAGE).", ".RECORDS_PER_PAGE);
			if ($user_details) {
				$email = $user_details['email'];
				if (DEMO_MODE) {
					if (($pos = strpos($email, "@")) !== false) {
						$name = substr($email, 0, strpos($email, "@"));
						$email = substr($name, 0, 1).'*****'.substr($email, $pos);
					}
				}
			}
			$htaccess = url_rewrite();
			echo '
		<div class="front-page">
		<div class="front-content">
			<h2><strong>'.$admin_pages[$page]['title'].($user_details ? ':' : '').'</strong>'.($user_details ? ' '.htmlspecialchars($email, ENT_QUOTES) : '').'</h2>
			<div class="front-table">
				<form action="#" id="form-add-url" class="profile" method="post" onsubmit="return add_url();">
					<table class="table-settings" style="width: 80%;">
						<tr>
							<th class="align-right">Add URL</th>
							'.($user_details ? '' : '<td><input type="text" id="urls-email" name="email" class="widefat" placeholder="User\'s e-mail" tabindex="1" title="User\'s e-mail"></td>').'
							<td><input type="text" id="urls-url" name="url" class="widefat" placeholder="New URL" tabindex="2" title="New shortened URL"></td>
							<td>
								'.($user_details ? '<input type="hidden" name="id" value="'.$user_details['id'].'">' : '').'
								<input type="hidden" name="action" value="add_url">
								<button type="submit" class="signup-btn-dark" tabindex="3">Add URL</button>
							</td>
						</tr>
					</table>
				</form>
				'.($totalpages > 1 ? '<div class="paginator-top">'.$switcher.'</div>' : '').'
				<table class="table-listing">
					<tr>
						<th>URL</th>
						'.($user_details ? '' : '<th>User</th>').'
						<th style="width: 80px; text-align: right; padding-right: 20px;">Redirects</th>
						<th style="width: 70px;">Actions</th>
					</tr>';
			if (sizeof($rows) > 0) {
				foreach ($rows as $row) {
					if ($user_details) $url_label = cut_string($row['url'], 80);
					else $url_label = cut_string($row['url'], 60);
					if ($row['blocked'] == 1) {
						$color = '#888';
					}
					if (!$user_details) {
						$email = $row['user_email'];
						if (DEMO_MODE) {
							if (($pos = strpos($email, "@")) !== false) {
								$name = substr($email, 0, strpos($email, "@"));
								$email = substr($name, 0, 1).'*****'.substr($email, $pos);
							}
						}
					}
					if (empty($email)) $email = '---';
					echo '
					<tr'.($row['blocked'] == 1 ? ' style="color: #888;"' : '').'>
						<td>'.$url_base.($htaccess ? '' : '?u=').$row['url_code'].'<br /><em><a href="'.$row['url'].'" target="_blank">'.htmlspecialchars($url_label, ENT_QUOTES).'</a></em></td>
						'.($user_details ? '' : '<td>'.htmlspecialchars($email, ENT_QUOTES).'</td>').'
						<td style="text-align: right;"><span style="padding-right: 20px;">'.$row['redirects'].'</span></td>
						<td>
							<a href="'.$url_admin.'?page=statistics&id='.$row['id'].'" title="Show statistics"><img src="'.$url_base.'img/chart.png" alt="Show statistics" border="0"></a>
							'.($row['blocked'] == 0 ? '<a href="#" title="Block shortened URL" onclick="return block_url('.$row['id'].');"><img src="'.$url_base.'img/block.png" alt="Block shortened URL" border="0"></a>' : '<a href="#" title="Unblock shortened URL" onclick="return unblock_url('.$row['id'].');"><img src="'.$url_base.'img/unblock.png" alt="Unblock shortened URL" border="0"></a>').'
							<a href="#" title="Delete shortened URL" onclick="return delete_url('.$row['id'].');"><img src="'.$url_base.'img/delete.png" alt="Delete shortened URL" border="0"></a>
						</td>
					</tr>';
				}
			} else {
				echo '
					<tr>
						<td colspan="'.($user_details ? '3' : '4').'" class="no-records">No records found.</td>
					</tr>';
			}
			echo '
				</table>
				'.($totalpages > 1 ? '<div class="paginator-bottom">'.$switcher.'</div>' : '').'
				<div class="legend">
					<ul>
						<li><img src="'.$url_base.'img/chart.png" alt="Show statistics" border="0" style="vertical-align: middle;"> Show statistics</li>
						<li><img src="'.$url_base.'img/block.png" alt="Block shortened URL" border="0" style="vertical-align: middle;"> Block record</li>
						<li><img src="'.$url_base.'img/unblock.png" alt="Unblock shortened URL" border="0" style="vertical-align: middle;"> Unblock record</li>
						<li><img src="'.$url_base.'img/delete.png" alt="Delete shortened URL" border="0" style="vertical-align: middle;"> Delete record</li>
					</ul>
				</div>
				<div class="loading-dark"></div>
			</div>
		</div>
		</div>';
			break;
		case 'statistics':
			if (isset($_GET["id"])) $id = intval($_GET["id"]);
			else $id = 0;
			$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE id = '".$id."' AND deleted = '0'");
			if (!$url_details) {
				echo '
				<div class="front-page">
				<div class="front-content">
					<h2><strong>Statistics</strong></h2>
					<div class="front-table">
						<table class="table-listing">
							<tr>
								<td class="no-records">Invalid URL.</td>
							</tr>
						</table>
					</div>
				</div>
				</div>';
			} else {
				if (isset($_GET['sd']) && isset($_GET['ed'])) {
					$start_period = $_GET['sd'];
					$end_period = $_GET['ed'];
				} else {
					$start_period = date('Y-m-d', time()-3600*24*30);
					$end_period = date('Y-m-d');
				}
				
				$start_time = mktime("00", "00", "00", substr($start_period,5,2), substr($start_period,8,2), substr($start_period,0,4));
				$end_time = mktime("23", "59", "59", substr($end_period,5,2), substr($end_period,8,2), substr($end_period,0,4));
				if (intval($start_time) <= 0 || intval($end_time) <= 0 || $end_time < $start_time) {
					$start_period = date('Y-m-d', time()-3600*24*30);
					$end_period = date('Y-m-d');
					$start_time = mktime("00", "00", "00", substr($start_period,5,2), substr($start_period,8,2), substr($start_period,0,4));
					$end_time = mktime("23", "59", "59", substr($end_period,5,2), substr($end_period,8,2), substr($end_period,0,4));
				} else {
					$start_period = date('Y-m-d', $start_time);
					$end_period = date('Y-m-d', $end_time);
				}
				
				$rows = $icdb->get_rows("SELECT COUNT(*) AS redirects, created_day FROM ".$icdb->prefix."log WHERE deleted = '0' AND url_id = '".$url_details['id']."' AND created >= '".$start_time."' AND created <= '".$end_time."' GROUP BY created_day");
				$stat_data = array();
				foreach ($rows as $row) {
					$stat_data[$row['created_day']] = $row['redirects'];
				}
				$htaccess = url_rewrite();
				echo '
			<div class="front-page">
				<div class="front-content">
					<h2><strong>Statistics:</strong> '.$url_base.($htaccess ? '' : '?u=').$url_details['url_code'].'</h2>
					<div class="front-table">
						<form action="'.$url_admin.'?page=statistics&id='.$url_details['id'].'" id="form-statistics-filter" class="profile" method="get" onsubmit="return get_statistics();">
							<table class="table-search">
								<tr>
									<td><input type="text" id="start_period" name="sd" class="widefat" value="'.htmlspecialchars($start_period, ENT_QUOTES).'" placeholder="Start period" tabindex="1" title="Start period"></td>
									<td><input type="text" id="end_period" name="ed" class="widefat" value="'.htmlspecialchars($end_period, ENT_QUOTES).'" placeholder="End period" tabindex="2" title="End period"></td>
									<td>
										<input type="hidden" name="page" value="statistics">
										<input type="hidden" name="id" value="'.$url_details['id'].'">
										<button type="submit" class="signup-btn-dark" tabindex="3">Update statistics</button>
									</td>
								</tr>
							</table>
						</form>
						<script>
							jQuery("#start_period").datepicker({
								defaultDate: "+1m",
								numberOfMonths: 2,
								dateFormat: "yy-mm-dd",
								maxDate: "+0",
								onClose: function(selectedDate) {
									jQuery("#end_period").datepicker("option", "minDate", selectedDate);
								}
							});
							jQuery("#end_period").datepicker({
								defaultDate: "+1m",
								numberOfMonths: 2,
								dateFormat: "yy-mm-dd",
								maxDate: "+0",
								onClose: function(selectedDate) {
									jQuery("#start_period").datepicker("option", "maxDate", selectedDate);
								}
							});
						</script>
						<div id="tabs" style="display: none;">
							<ul>
								<li><a href="#graph">Graph</a></li>
								<li><a href="#table">Table</a></li>
							</ul>
							<div id="graph">
								<div id="chartbox"></div>
							</div>
							<div id="table">
								<table class="table-listing">
									<tr>
										<th>Date</th>
										<th style="width: 80px; text-align: right;">Redirects</th>
									</tr>';
				for ($i=$start_time; $i<$end_time; $i=$i+24*3600) {
					$key = date("Ymd", $i);
					if (array_key_exists($key, $stat_data)) echo '<tr><td>'.date('Y-m-d', $i).'</td><td style="text-align: right;">'.$stat_data[$key].'</td></tr>';
					else echo '<tr><td>'.date('Y-m-d', $i).'</td><td style="text-align: right;">0</td></tr>';
				}
				echo '
								</table>
							</div>
						</div>
						<script>
							jQuery("#tabs").tabs();
							jQuery("#tabs").fadeIn(200);
							new Morris.Line({
							  element: "chartbox",
							  data: [';
				$data_array = array();
				for ($i=$start_time; $i<$end_time; $i=$i+24*3600) {
					$key = date("Ymd", $i);
					if (array_key_exists($key, $stat_data)) $data_array[] = '{day: "'.date('Y-m-d', $i).'", value: '.$stat_data[$key].'}';
					else $data_array[] = '{day: "'.date('Y-m-d', $i).'", value: 0}';
				}
				echo implode(',', $data_array);
				echo '
							  ],
							  smooth: false,
							  xkey: "day",
							  ykeys: ["value"],
							  labels: ["Redirects"]
							});
						</script>
					</div>
				</div>
			</div>';
			}
		break;

		default:
			break;
	
	}
?>
<?php
} else {
?>
		<div class="front-card">
			<div class="front-welcome">
				<div class="front-welcome-text">
					<h1>Admin Panel</h1>
					<p>Welcome to admin panel</p>
				</div>
			</div>
			<div class="front-signin">
				<form action="#" id="form-signin" class="signin" method="post" onsubmit="return signin();">
					<input type="text" id="signin-login" class="text-input" name="login" title="Login" autocomplete="off" tabindex="1" placeholder="Login">
					<input type="password" id="signin-password" class="text-input" name="password" title="Password" autocomplete="off" tabindex="2" placeholder="Password">
					<table>
						<tr>
							<td class="align-right">
								<button type="submit" class="signin-btn" tabindex="4">Sign in</button>
							</td>
						</tr>
					</table>
				</form>
				<div class="loading"></div>
			</div>
		</div>
		<div class="footer">
			<ul>
				<li><span class="copyright">© <?php echo date("Y"); ?> <?php echo $options['website_name']; ?></span></li>
			</ul>
		</div>
<?php
}
?>
	</div>
<?php
if (!empty($notification)) {
?>
	<script type="text/javascript">
		show_notification("<?php echo $notification['type']; ?>", "<?php echo $notification['message']; ?>", 5000);
	</script>
<?php
}
?>
</body>
</html>