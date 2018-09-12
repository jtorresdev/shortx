<?php
session_start();
if (isset($_REQUEST['debug'])) error_reporting(-1);
else error_reporting(0);

$url_base = ((empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] == 'off') ? 'http://' : 'https://').$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
if (($pos = strpos($url_base, '?')) !== false) $url_base = substr($url_base, 0, $pos);
if (($pos = strrpos($url_base, basename(__FILE__))) !== false) $url_base = substr($url_base, 0, $pos);
if (($pos = strrpos($url_base, basename(dirname(__FILE__)))) !== false) $url_base = substr($url_base, 0, $pos);
$url_admin = $url_base.basename(dirname(__FILE__));

if (file_exists(dirname(dirname(__FILE__)).'/inc/config.php')) {
	include_once(dirname(dirname(__FILE__)).'/inc/config.php');
	if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD')) {
		header('Location: '.$url_admin);
		exit;
	}
}
include_once(dirname(dirname(__FILE__)).'/inc/functions.php');
include_once(dirname(dirname(__FILE__)).'/inc/settings.php');
include_once(dirname(dirname(__FILE__)).'/inc/icdb.php');

if (isset($_POST['action'])) {
	switch ($_POST['action']) {
		case 'save_dbparams':
			$db_host = trim(stripslashes($_POST['db_host']));
			$db_host_port = trim(stripslashes($_POST['db_host_port']));
			$db_user = trim(stripslashes($_POST['db_user']));
			$db_password = trim(stripslashes($_POST['db_password']));
			$db_name = trim(stripslashes($_POST['db_name']));
			if (strpos($db_password, '"') !== false) {
				$return_object = new stdClass();
				$return_object->message = 'Double quote symbol is not allowed. Please use another password.';
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			try {
				$icdb = new ICDB($db_host, $db_host_port, $db_name, $db_user, $db_password, TABLE_PREFIX);
				$options['website_url'] = $url_base;
				install();
			} catch (Exception $e) {
				$return_object = new stdClass();
				$return_object->message = $e->getMessage();
				$return_object->status = 'ERROR';
				echo json_encode($return_object);
				exit;
			}
			$config_content = '<?php
/** The name of the database */
define("DB_NAME", "'.$db_name.'");

/** MySQL database username */
define("DB_USER", "'.$db_user.'");

/** MySQL database password */
define("DB_PASSWORD", "'.$db_password.'");

/** MySQL hostname */
define("DB_HOST", "'.$db_host.'");

/** MySQL hostname port */
define("DB_HOST_PORT", "'.$db_host_port.'");
?>';
			$size = file_put_contents(dirname(dirname(__FILE__)).'/inc/config.php', $config_content);
			$return_object = new stdClass();
			if ($size === false || $size == 0) {
				$return_object->message = 'We are almost finished. Unfortunately, we could not create file <code>inc/config.php</code>. Please create it manually and put the following content inside:<br />
				<textarea class="widefat" style="height: 280px; margin-top: 10px;" onclick="this.focus();this.select();" readonly="readonly">'.htmlspecialchars($config_content, ENT_QUOTES).'</textarea>
				Full path: <code>'.dirname(dirname(__FILE__)).'/inc/config.php</code>';
			} else {
				$return_object->message = '<p style="padding: 50px 10px; text-align: center;">Installation complete. Now you can go to <a href="'.$url_base.'">website</a> or visit <a href="'.$url_admin.'">admin panel</a> (login: admin, password: admin).</p>';
			}
			$return_object->status = 'OK';
			echo json_encode($return_object);
			exit;
			break;
		
		default:
			break;
	}
	exit;
}
?>
<!DOCTYPE html>
<head>
	<meta charset="utf-8">
	<title>Installation</title>
	<meta name="description" content="Install the script.">
	<link rel="stylesheet" href="<?php echo $url_base; ?>css/admin.css" type="text/css">
	<link rel="stylesheet" href="<?php echo $url_base; ?>css/jNotify.jquery.css" type="text/css">
	<script src="<?php echo $url_base; ?>js/jquery-1.10.1.min.js" type="text/javascript"></script>
	<script src="<?php echo $url_base; ?>js/jNotify.jquery.js" type="text/javascript"></script>
	<style type="text/css">
	a {color: #88F; text-decoration: none;}
	a:hover, a:active {color: #44F;}
	</style>
	<script type="text/javascript">
		function save_dbparams() {
			jQuery(".front-settings .loading-dark").fadeIn(200);
			jQuery.post("install.php", jQuery("#form-settings").serialize(),
				function(return_data) {
					jQuery(".front-settings .loading-dark").fadeOut(200);
					data = jQuery.parseJSON(return_data);
					var status = data.status;
					if (status == "OK") {
						jQuery("#front-settings").html(data.message);
					} else if (status == "ERROR") {
						show_notification("error", data.message, 3000);
					} else {
						show_notification("error", "Internal error. Please contact developer.", 3000);
					}
				}
			);
			return false;
		}
		function show_notification(type, message, delay) {
			if (type == "error") {
				jError(message, {
					HorizontalPosition : 'center',
					ShowTimeEffect : 1000,
					HideTimeEffect : 1000,
					VerticalPosition : 'top',
					ShowOverlay : false,
					TimeShown : delay
				});
			}
		}		
	</script>
</head>
<body>
	<div class="front-container">
		<div class="front-bg">
			<img class="front-image" src="<?php echo $url_base; ?>img/bg3.jpg">
		</div>
		<div class="front-page">
		<div class="front-content">
			<h2><strong>Quick Install</strong></h2>
			<div class="front-settings" id="front-settings">
			<form action="#" id="form-settings" class="settings" method="post" onsubmit="return save_dbparams();">
				<table class="table-settings">
					<tr>
						<th style="width: 200px;">MySQL hostname</th>
						<td><input type="text" id="settings-db-host" name="db_host" value="localhost" class="widefat" placeholder="MySQL hostname" tabindex="1" title="MySQL hostname"></td>
					</tr>
					<tr>
						<th>MySQL port</th>
						<td><input type="text" id="settings-db-port" name="db_host_port" value="" class="widefat" placeholder="MySQL port" tabindex="2" title="MySQL port"></td>
					</tr>
					<tr>
						<th>MySQL database username</th>
						<td><input type="text" id="settings-db-user" name="db_user" class="widefat" placeholder="MySQL database username" tabindex="3" title="MySQL database username"></td>
					</tr>
					<tr>
						<th>MySQL database password</th>
						<td><input type="text" id="settings-db-password" name="db_password" class="widefat" placeholder="MySQL database password" tabindex="4" title="MySQL database password"></td>
					</tr>
					<tr>
						<th>Database name</th>
						<td><input type="text" id="settings-db-name" name="db_name" class="widefat" placeholder="The name of the database" tabindex="5" title="MySQL database name"></td>
					</tr>
					<tr>
						<th></th>
						<td class="align-right">
							<input type="hidden" name="action" value="save_dbparams">
							<button type="submit" class="signup-btn-dark" tabindex="100">Install script</button>
						</td>
					</tr>
				</table>
			</form>
			<div class="loading-dark"></div>
			</div>
		</div>
		</div>
	</div>
</body>
</html>