<?php
session_start();
if (isset($_REQUEST['debug'])) error_reporting(-1);
else error_reporting(0);
if (!file_exists(dirname(__FILE__).'/inc/config.php')) {
	header('Location: admin/install.php');
	exit;
}
include_once(dirname(__FILE__).'/inc/config.php');
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASSWORD')) {
	header('Location: admin/install.php');
	exit;
}
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

$url_code = '';
if (isset($_GET['u'])) $url_code = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['u']);
else {
	$url_code = $_SERVER['REQUEST_URI'];
	if (($pos = strpos($url_code, '?')) !== false) $url_code = substr($url_code, 0, $pos);
	if (($pos = strrpos($url_code, '/')) !== false) $url_code = substr($url_code, $pos+1);
	$url_code = str_replace(basename(__FILE__), '', $url_code);
	$url_code = preg_replace('/[^a-zA-Z0-9]/', '', $url_code);
}
if (!empty($url_code)) {
	$url_details = $icdb->get_row("SELECT t1.*, t2.deleted AS user_deleted, t2.blocked AS user_blocked FROM ".$icdb->prefix."urls t1 LEFT JOIN ".$icdb->prefix."users t2 ON t2.id = t1.user_id WHERE t1.url_code = '".$url_code."' AND t1.deleted = '0' AND t1.blocked = '0'");
	if ($url_details && intval($url_details['user_deleted']) != 1 && intval($url_details['user_blocked']) != 1) {
		$icdb->query("UPDATE ".$icdb->prefix."urls SET redirects = redirects + 1 WHERE id = '".$url_details['id']."'");
		$time = time();
		$icdb->query("INSERT INTO ".$icdb->prefix."log (url_id, options, created_day, created, deleted) VALUES ('".$url_details['id']."', '', '".date('Ymd', $time)."', '".$time."', '0')");
		header('Location: '.$url_details['url']);
		exit;
	}
}

if (isset($_GET['activate'])) {
	$activation_code = $_GET['activate'];
	$activation_code = preg_replace('/[^a-zA-Z0-9]/', '', $activation_code);
	$user_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."users WHERE activation_code = '".$icdb->escape_string($activation_code)."' AND deleted = '0'");
	if ($user_details && $user_details['activated'] == 1) {
		$_SESSION['info'] = 'This account has been already activated.';
	} else if ($user_details && $user_details['activated'] != 1) {
		$icdb->query("UPDATE ".$icdb->prefix."users SET activated = '1' WHERE id = '".$user_details['id']."'");
		$_SESSION['success'] = 'Account successfully activated. Now you can sign in.';
	} else {
		$_SESSION['error'] = 'Hm... Account not found.';
	}
	header('Location: '.$url_base);
	exit;
}

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

$pages = array ();
$deafult_page = '';
$private_pages = array ('profile', 'urls', 'statistics');
$public_pages = array ('api', 'terms');

if ($options['disable_api'] == 'yes') {
	if (($key = array_search('api', $public_pages)) !== false) {
		unset($public_pages[$key]);
	}
}

if ($active_user) {
	if (isset($_GET['page'])) {
		$page = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['page']);
		if (!in_array($page, array_merge($private_pages, $public_pages))) $page = 'urls';
	} else $page = 'urls';
} else {
	if (isset($_GET['page'])) {
		$page = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['page']);
		if (!in_array($page, $public_pages)) $page = 'home';
	} else $page = 'home';
}

if ($active_user) {
	if (isset($_GET['action'])) {
		switch ($_GET['action']) {
			case 'logout':
				if (!empty($session_id)) {
					$icdb->query("UPDATE ".$icdb->prefix."sessions SET valid_period = '0' WHERE session_id = '".$session_id."'");
				}
				$_SESSION['info'] = 'You are signed out. See you later.';
				header('Location: '.$url_base);
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
	<title><?php echo $options["website_name"]; ?></title>
	<meta name="description" content="<?php echo $options["website_slogan"]; ?>">
	<link rel="stylesheet" href="<?php echo $url_base; ?>css/style.css" type="text/css" />
	<link rel="stylesheet" href="<?php echo $url_base; ?>css/jNotify.jquery.css" type="text/css" />
	<link rel="stylesheet" href="<?php echo $url_base; ?>css/dark-hive/jquery-ui.css" type="text/css" />
	<link rel="stylesheet" href="<?php echo $url_base; ?>css/morris.css">
	<script src="<?php echo $url_base; ?>js/jquery-1.10.1.min.js" type="text/javascript"></script>
	<script src="<?php echo $url_base; ?>js/jquery-ui-1.10.3.custom.min.js" type="text/javascript"></script>
	<script src="<?php echo $url_base; ?>js/jNotify.jquery.js" type="text/javascript"></script>
	<script src="<?php echo $url_base; ?>js/script.js" type="text/javascript"></script>
	<script src="<?php echo $url_base; ?>js/raphael.js"></script>
	<script src="<?php echo $url_base; ?>js/morris.min.js"></script>	
	<script type="text/javascript">
		var url_base = "<?php echo $url_base; ?>";
	</script>
</head>
<body>
	<div class="topbar">
		<div class="topbar-center">
			<h1><a href="<?php echo $url_base; ?>"><?php echo $options['website_name']; ?></a></h1>
<?php 
if ($active_user) {
?>
			<ul class="pull-right">
				<li class="account-id">
					<a href="<?php echo $url_base; ?>?page=profile"><img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower($active_user['email'])); ?>?s=25" alt="" height="25" border="0"> <?php echo $active_user['email']; ?> ▼</a>
					<div class="topbar-profile-box">
						<div class="topbar-profile-box-content">
							<table>
								<tr>
									<td>
										<img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower($active_user['email'])); ?>?s=64" alt="" height="64">
									</td>
									<td>
										<?php echo $active_user['email']; ?>
										<ul>
											<li><a href="<?php echo $url_base; ?>?page=profile" title="Edit my profile">Edit my profile</a></li>
											<li><a href="<?php echo $url_base; ?>?action=logout" title="Sign out">Log out</a></li>
										</ul>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</li>
			</ul>
<?php
}
?>
		</div>
	</div>
	<div class="front-container">
		<div class="front-bg">
			<img class="front-image" src="<?php echo $url_base; ?>img/bg2.jpg">
		</div>
<?php
switch ($page) {
	case 'profile':
			echo '
		<div class="front-page">
		<div class="front-content">
			<h2><strong>Profile Details:</strong> '.$active_user['email'].'</h2>
			<div class="front-settings">
			<form action="#" id="form-profile" class="profile" method="post" onsubmit="return save_profile();">
				<table class="table-settings">
					'.($options['disable_api'] == 'yes' ? '' : '<tr>
						<th>API Key</th>
						<td>
							<input type="text" id="profile-api-key" class="widefat" onclick="this.focus();this.select();" readonly="readonly" value="'.$active_user['api_key'].'" title="Your API Key">
							<br /><em>Use this key to shorten URLs through our <a href="'.$url_base.'?page=api" title="Our API">API</a>.</em>
						</td>
						<td style="width: 20px; vertical-align: top; padding-top: 12px;">
							<a href="#" title="Update API Key" onclick="return update_api_key();"><img src="'.$url_base.'img/refresh.png" alt="Update API Key" border="0"></a>
						</td>
					</tr>
					<tr><td colspan="3"><hr></td></tr>').'
					<tr>
						<th>New password</th>
						<td colspan="2"><input type="password" id="profile-new-password" name="new_password" class="widefat" placeholder="New password" tabindex="2" title="New password"></td>
					</tr>
					<tr>
						<th>Confirm password</th>
						<td colspan="2"><input type="password" id="profile-new-password2" name="new_password2" class="widefat" placeholder="Confirm new password" tabindex="3" title="Confirm new password"></td>
					</tr>
					<tr><td colspan="3"><hr></td></tr>
					<tr>
						<th>Current password</th>
						<td colspan="2"><input type="password" id="profile-old-password" name="old_password" class="widefat" placeholder="Current password" tabindex="4" title="Current password"></td>
					</tr>
					<tr>
						<th></th>
						<td colspan="2" class="align-right">
							<input type="hidden" name="action" value="save_profile">
							<button type="submit" class="signup-btn-dark" tabindex="5">Save changes</button>
						</td>
					</tr>
				</table>
			</form>
			<div class="loading-dark"></div>
			</div>
		</div>
		'.inner_footer().'
		</div>';
			break;

	case 'urls':
		if (isset($_GET["s"])) {
			$search_query = urldecode(trim(stripslashes($_GET["s"])));
			$search_query = str_replace($url_base.'?u=', '', $search_query);
			$search_query = str_replace($url_base, '', $search_query);
		} else $search_query = "";
		if (isset($_GET["p"])) $p = intval($_GET["p"]);
		else $p = 1;
	
		$content = urls_page($search_query, $p);
		echo '
		<div class="front-page">
			<div class="front-url-logged" id="front-url">
				<form action="#" id="form-add-url" class="profile" method="post" onsubmit="return add_url();">
					<table class="table-settings" style="width: 100%;">
						<tr>
							<td><input type="text" id="urls-url" name="url" class="widefat-main" placeholder="Paste a link to shorten it..." tabindex="1" title="Your long URL"></td>
							<td style="width: 150px; padding-left: 0px;">
								<input type="hidden" name="action" value="add_url">
								<button type="submit" class="button-main" tabindex="3">Shorten</button>
							</td>
						</tr>
					</table>
				</form>
				<div class="loading-dark"></div>
			</div>
			<div class="front-content" id="urls-page">
				<h2><strong>My Shortened URLs</strong></h2>
				<div class="front-table" id="urls-page-content">
				'.$content.'
				</div>
			</div>
			'.inner_footer().'
		</div>';
		break;

	case 'statistics':
		if (isset($_GET["id"])) $id = intval($_GET["id"]);
		else $id = 0;
		$url_details = $icdb->get_row("SELECT * FROM ".$icdb->prefix."urls WHERE id = '".$id."' AND deleted = '0' AND user_id = '".$active_user['id']."'");
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
					<form action="'.$url_base.'?page=statistics&id='.$url_details['id'].'" id="form-statistics-filter" class="profile" method="get" onsubmit="return get_statistics();">
						<table class="table-search">
							<tr>
								<td><input type="text" id="start_period" name="sd" class="widefat" value="'.htmlspecialchars($start_period, ENT_QUOTES).'" placeholder="Start period" tabindex="1" title="Start period"></td>
								<td><input type="text" id="end_period" name="ed" class="widefat" value="'.htmlspecialchars($end_period, ENT_QUOTES).'" placeholder="End period" tabindex="2" title="End period"></td>
								<td>
									<input type="hidden" name="page" value="statistics">
									<input type="hidden" name="id" value="'.$url_details['id'].'">
									<button type="submit" class="signin-btn" tabindex="3">Update statistics</button>
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
			'.inner_footer().'
		</div>';
		}
		break;
		
	case 'api':
?>
			<div class="front-page">
				<div class="front-content">
					<h2><strong>Our API</strong></h2>
					<div class="text-content">
						<h3>General Info</h3>
						<p>We offer an API that allows applications to automatically create short URLs. This is done by simply reading the result returned from:
						<br /><code><font style="color: #FAA;"><?php echo $url_base ?>api/?key=YOUR-API-KEY&url=SOURCE-URL</font></code>
						<br /><code><font style="color: #FAA;">YOUR-API-KEY</font></code> - your personal API Key, taken from profile page.
						<br /><code><font style="color: #FAA;">SOURCE-URL</font></code> - URL that must be shortened. 
						This is URL-encoded string (most programming languages have appropriate function for that, ex. 
						<code><a href="http://php.net/manual/en/function.rawurlencode.php" target="_blank">PHP</a></code>, 
						<code><a href="http://www.perlhowto.com/encode_and_decode_url_strings" target="_blank">Perl</a></code>,
						<code><a href="http://docs.oracle.com/javase/6/docs/api/java/net/URLEncoder.html" target="_blank">Java</a></code>,
						<code><a href="http://www.w3schools.com/jsref/jsref_encodeuricomponent.asp" target="_blank">JavaScript</a></code>, etc.)</p>
						<p>Result is a JSON-encoded string.</p>
						<h3>Successful Shortening</h3>
						If everything is OK, our API returns JSON-encoded string like that:
						<pre>{
  <font style="color: #AAF">"status"</font>:<font style="color: #AFA">"OK"</font>,
  <font style="color: #AAF">"url"</font>:<font style="color: #AFA">"<?php echo $url_base; ?>s6Bf"</font>
}</pre>
						<h3>Failed Call</h3>
						In case of any problems, the result will be like that:
						<pre>{
  <font style="color: #AAF">"status"</font>:<font style="color: #AFA">"ERROR"</font>,
  <font style="color: #AAF">"message"</font>:<font style="color: #AFA">"Invalid API Key!"</font>
}</pre>
						<p>Enjoy using our API. :-)</p>
					</div>
				</div>
				<?php echo inner_footer(); ?>
			</div>
<?php
		break;

	case 'terms':
?>
			<div class="front-page">
				<div class="front-content">
					<h2><strong>Terms of Service</strong></h2>
					<div class="text-content">
						<p>Please read these Terms of Service fully and carefully before using <?php echo $url_base; ?> (the “Site”) and the services, features, content or applications offered by <?php echo $options['website_name']; ?> (“we”, “us”) (collectively with the Site, the “Services”). These Terms of Service set forth the legally binding terms and conditions for your use of the Site and the Services.</p>
<h3>1. Acceptance of Terms.</h3>
<p>a. By registering for and/or using the Services in any manner, including but not limited to visiting or browsing the Site and using the Services to shorten a uniform resource locator (“URL”), you agree to these Terms of Service and all other operating rules, policies and procedures that may be published from time to time on the Sit, each of which is incorporated by reference and each of which may be updated from time to time without notice to you.
<br />b. Certain of the Services may be subject to additional terms and conditions from time to time; your use of such Services is subject to those additional terms and conditions, which are incorporated into these Terms of Service by this reference.
<br />c. These Terms of Service apply to all users of the Services, including, without limitation, users who are contributors of content, information, and other materials or services, registered or otherwise.</p>
<h3>2. Modification.</h3>
<p>We reserves the right, at sole discretion, to modify or replace any of these Terms of Service, or change, suspend, or discontinue the Services (including without limitation, the availability of any feature, database, or content) at any time by posting a notice on the Site or by sending you notice through the Services or by e-mail. We may also impose limits on certain features of the Services or restrict your access to parts or all of the Services without notice or liability. It is your responsibility to check these Terms of Service periodically for changes. Your use of the Services following the posting of any changes to these Terms of Service constitutes acceptance of those changes.</p>
<h3>3. Eligibility.</h3>
<p>You represent and warrant that you are at least 13 years of age. If you are under age 13, you may not, under any circumstances, use the Services. We may, in sole discretion, refuse to offer the Services to any person or entity and change its eligibility criteria at any time. You are solely responsible for ensuring that these Terms of Service are in compliance with all laws, rules and regulations applicable to you and the right to access the Services is revoked where these Terms of Service or use of the Services is prohibited or to the extent offering, sale or provision of the Services conflicts with any applicable law, rule or regulation. Further, the Services are offered only for your use, and not for the use or benefit of any third party.</p>
<h3>4. Registration.</h3>
<p>While some features of the Service are available to unregistered users, for broader access to the Services you must register with us (creating an “Account”). In order to register, you must provide an accurate e-mail address. </p>
<h3>5. Account Security.</h3>
<p>You are solely responsible for the activity that occurs on your Account, and for keeping your Account secure. You are not permitted to use another Account without permission. You must notify us immediately of any breach of security or other unauthorized use of your Account. You should never publish, distribute or post login information for your Account.</p>
<h3>6. Our Services.</h3>
<p>The Services allow you to shorten and track URLs using our domain as the link.</p>
<h3>7.Content.</h3>
<p>a. Definition.
<br />For purposes of these Terms of Service, the term “Content” includes, without limitation, URLs, shortened URLs, curated URLs, bundles or packages of URLs, videos, audio clips, written posts and comments, information, data, text, photographs, software, scripts, graphics, and interactive features generated, provided, or otherwise made accessible on or through the Services.
<br />b. User Content.
<br />All Content added, created, uploaded, submitted, distributed, or posted to the Services by users, whether publicly posted or privately transmitted (collectively “User Content”), is the sole responsibility of the user who originated it. You acknowledge that all Content accessed by you using the Services is at your own risk and you will be solely responsible for any damage or loss to you or any other party resulting therefrom. When you delete your User Content, it will be removed from the Services. However, you understand that (i) certain User Content (e.g., previously shortened URLs and related <?php echo $options['website_name']; ?> Metrics) will remain available and (ii) any removed User Content may persist in backup copies for a reasonable period of time (but will not following removal be shared with others).
<br />c. Our Content.
<br />The Services contain Content specifically provided by  or its partners and such Content is protected by copyrights, trademarks, service marks, patents, trade secrets or other proprietary rights and laws. Such Content includes, but is not limited to, <?php echo $options['website_name']; ?> Metrics. You shall abide by and maintain all copyright notices, information, and restrictions contained in any Content accessed through the Services.
<br />d. Use License.
<br />Subject to these Terms of Service, <?php echo $options['website_name']; ?> grants each user of the Services a worldwide, non-exclusive, non-sublicensable and non-transferable license to use the Content, solely for personal, non-commercial use as part of using the Services. Use, reproduction, modification, distribution or storage of any Content for other than personal, non-commercial use is expressly prohibited without prior written permission from <?php echo $options['website_name']; ?>, or from the copyright holder identified in such Content’s copyright notice. If you would like to use the Services for commercial purposes, consider purchasing a license for our <?php echo $options['website_name']; ?> Enterprise services, or contact us regarding other
<br />e. Types of uses.
<br />f. License Grants.
<br />i. License to <?php echo $options['website_name']; ?>.
<br />By submitting User Content through the Services, you hereby do and shall grant <?php echo $options['website_name']; ?> a worldwide, non-exclusive, royalty-free, fully paid, sublicensable and transferable license to use, edit, modify, reproduce, distribute, prepare derivative works of, display, perform, and otherwise fully exploit the User Content in connection with the Site, the Services and <?php echo $options['website_name']; ?>’s (and its successors and assigns’) business, including without limitation for promoting and redistributing part or all of the Site (and derivative works thereof) or the Services in any media formats and through any media channels (including, without limitation, third party websites and feeds).
<br />ii. License to Users.
<br />You also hereby do and shall grant each user of the Site and/or the Services a non-exclusive license to access your User Content through the Site and the Services, and to use, edit, modify, reproduce, distribute, prepare derivative works of, display and perform such User Content.
<br />iii. No Infringement.
<br />You represent and warrant that you have all rights to grant such licenses without infringement or violation of any third party rights, including without limitation, any privacy rights, publicity rights, copyrights, contract rights, or any other intellectual property or proprietary rights.
<br />g. Availability of Content.
<?php echo $options['website_name']; ?> does not guarantee that any Content will be made available on the Site or through the Services. Further, <?php echo $options['website_name']; ?> has no obligation to monitor the Site or the Services. However, <?php echo $options['website_name']; ?> reserves the right to (i) remove, edit or modify any Content in its sole discretion, at any time, without notice to you and for any reason (including, but not limited to, upon receipt of claims or allegations from third parties or authorities relating to such Content or if <?php echo $options['website_name']; ?> is concerned that you may have violated these Terms of Service), or for no reason at all and (ii) remove or block any Content from the Services.</p>
<h3>8. Rules of Conduct.</h3>
<p>a. You promise not to use the Services for any purpose that is prohibited by these Terms of Service. You are responsible for all of your activity in connection with the Services.
<br />b. You shall not, and shall not permit any third party to, either (a) take any action or (b) upload, download, post, submit or otherwise distribute or facilitate distribution of any Content (including User Content) on or through the Service that:
<br />i. infringes any patent, trademark, trade secret, copyright, right of publicity or other right of any other person or entity or violates any law or contractual duty.;
<br />ii. is unlawful, such as content that is threatening, abusive, harassing, defamatory, libelous, fraudulent, invasive of another’s privacy, or tortuous;
<br />iii. constitutes unauthorized or unsolicited advertising, junk or bulk e-mail (“spamming”);
<br />iv. contains software viruses or any other computer codes, files, or programs that are designed or intended to disrupt, damage, limit or interfere with the proper function of any software, hardware, or telecommunications equipment or to damage or obtain unauthorized access to any system, data, password or other information of <?php echo $options['website_name']; ?> or any third party;
<br />v. impersonates any person or entity, including any employee or representative of <?php echo $options['website_name']; ?>;
<br />vi. includes anyone’s identification documents or sensitive financial information; or
<br />vii. is otherwise determined by <?php echo $options['website_name']; ?> to be inappropriate at its sole discretion.
<br />c. You shall not: (i) take any action that imposes or may impose (as determined by <?php echo $options['website_name']; ?> in its sole discretion) an unreasonable or disproportionately large load on <?php echo $options['website_name']; ?>’s (or its third party providers’) infrastructure; (ii) interfere or attempt to interfere with the proper working of the Services or any activities conducted on the Services; (iii) bypass any measures <?php echo $options['website_name']; ?> may use to prevent or restrict access to the Services (or other accounts, computer systems or networks connected to the Services); (iv) run any form of auto-responder or “spam” on the Services; (v) use manual or automated software, devices, or other processes to “crawl” or “spider” any page of the Site; (vi) harvest or scrape any Content from the Services; or (vii) otherwise take any action in violation of <?php echo $options['website_name']; ?>’s guidelines and policies.
<br />d. You shall not (directly or indirectly): (i) decipher, decompile, disassemble, reverse engineer or otherwise attempt to derive any source code or underlying ideas or algorithms of any aspect, feature or part of the Services, except to the limited extent applicable laws specifically prohibit such restriction; (ii) modify, translate, or otherwise create derivative works of any part of the Services; or (iii) copy, rent, lease, distribute, or otherwise transfer any of the rights that you receive hereunder. You shall abide by all applicable local, state, national and international laws and regulations.
<br />e. <?php echo $options['website_name']; ?> also reserves the right to access, read, preserve, and disclose any information as it reasonably believes is necessary to (i) satisfy any applicable law, regulation, legal process or governmental request; (ii) enforce these Terms of Service, including investigation of potential violations hereof; (iii) detect, prevent, or otherwise address fraud, security or technical issues; (iv) respond to user support requests; or (v) protect the rights, property or safety of <?php echo $options['website_name']; ?>, its users and the public. This includes exchanging information with other companies and organizations for fraud protection and spam prevention.</p>
<h3>9.	Termination.</h3>
<p><?php echo $options['website_name']; ?> may terminate your access to all or any part of the Services at any time, with or without cause, with or without notice, effective immediately, which may result in the forfeiture and destruction of all information associated with your Account. If you wish to terminate your Account, you may do so by following the instructions on the Site. All provisions of these Terms of Service which by their nature should survive termination shall survive termination, including without limitation, ownership provisions, warranty disclaimers, indemnity and limitations of liability.</p>
<h3>10. Warranty Disclaimer.</h3>
<p>a. <?php echo $options['website_name']; ?> has no special relationship with or fiduciary duty to you. You acknowledge that <?php echo $options['website_name']; ?> has no control over, and no duty to take any action regarding:
<br />i.	which users gains access to the Services;
<br />ii.	what Content you access via the Services;
<br />iii.	what effects the Content may have on you;
<br />iv.	how you may interpret or use the Content; or
<br />v.	what actions you may take as a result of having been exposed to the Content.
<br />b.	You release <?php echo $options['website_name']; ?> from all liability for you having acquired or not acquired Content through the Services. The Services may contain, or direct you to websites containing, information that some people may find offensive or inappropriate. <?php echo $options['website_name']; ?> makes no representations concerning any Content contained in or accessed through the Services, and it will not be responsible or liable for the accuracy, copyright compliance, legality or decency of material contained in or accessed through the Services.
<br />c. THE SERVICES AND CONTENT ARE PROVIDED “AS IS”, “AS AVAILABLE” AND WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF TITLE, NON-INFRINGEMENT, MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE, AND ANY WARRANTIES IMPLIED BY ANY COURSE OF PERFORMANCE OR USAGE OF TRADE, ALL OF WHICH ARE EXPRESSLY DISCLAIMED. <?php echo strtoupper($options['website_name']); ?>, AND ITS DIRECTORS, EMPLOYEES, AGENTS, SUPPLIERS, PARTNERS AND CONTENT PROVIDERS DO NOT WARRANT THAT: (I) THE SERVICES WILL BE SECURE OR AVAILABLE AT ANY PARTICULAR TIME OR LOCATION; (II) ANY DEFECTS OR ERRORS WILL BE CORRECTED; (III) ANY CONTENT OR SOFTWARE AVAILABLE AT OR THROUGH THE SERVICES IS FREE OF VIRUSES OR OTHER HARMFUL COMPONENTS; OR (IV) THE RESULTS OF USING THE SERVICES WILL MEET YOUR REQUIREMENTS. YOUR USE OF THE SERVICES IS SOLELY AT YOUR OWN RISK. SOME STATES DO NOT ALLOW LIMITATIONS ON IMPLIED WARRANTIES, SO THE FOREGOING LIMITATIONS MAY NOT APPLY TO YOU.
<br />d. ELECTRONIC COMMUNICATIONS PRIVACY ACT NOTICE (18 USC 2701-2711): WE MAKE NO GUARANTY OF CONFIDENTIALITY OR PRIVACY OF ANY COMMUNICATION OR INFORMATION TRANSMITTED ON THE SERVICES OR ANY WEBSITE LINKED TO THE SERVICES. <?php echo $options['website_name']; ?> will not be liable for the privacy of e-mail addresses, registration and identification information, disk space, communications, confidential or trade-secret information, or any other Content stored on <?php echo $options['website_name']; ?>’s equipment, transmitted over networks accessed by the Services, or otherwise connected with your use of the Services.</p>
<h3>11. Limitation of Liability.</h3>
<p>IN NO EVENT SHALL <?php echo strtoupper($options['website_name']); ?>, ITS AFFILIATES NOR ANY OF THEIR RESPECTIVE DIRECTORS, EMPLOYEES, CONTRACTORS, AGENTS, PARTNERS, SUPPLIERS, REPRESENTATIVES OR CONTENT PROVIDERS, BE LIABLE UNDER CONTRACT, TORT, STRICT LIABILITY, NEGLIGENCE OR ANY OTHER LEGAL OR EQUITABLE THEORY WITH RESPECT TO THE SERVICES (I) FOR ANY LOST PROFITS, DATA LOSS, COST OF PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES, OR SPECIAL, DIRECT, INDIRECT, INCIDENTAL, PUNITIVE, OR CONSEQUENTIAL DAMAGES OF ANY KIND WHATSOEVER, SUBSTITUTE GOODS OR SERVICES (HOWEVER ARISING); OR (II) FOR ANY BUGS, VIRUSES, TROJAN HORSES, OR THE LIKE (REGARDLESS OF THE SOURCE OF ORIGINATION). NOTWITHSTANDING THE FOREGOING, UNDER NO CIRCUMSTANCES SHALL SUCH LIABILITY EXCEED ANY DAMAGES IN EXCESS OF ONE HUNDRED U.S. DOLLARS ($100.00) IN THE AGGREGATE. SOME STATES DO NOT ALLOW THE EXCLUSION OR LIMITATION OF INCIDENTAL OR CONSEQUENTIAL DAMAGES, SO THE ABOVE LIMITATIONS AND EXCLUSIONS MAY NOT APPLY TO YOU.</p>
<h3>12. Entire Agreement and Severability.</h3>
<p>These Terms of Service are the entire agreement between you and <?php echo $options['website_name']; ?> with respect to the Services and use of the Site, and supersede all prior or contemporaneous communications and proposals (whether oral, written or electronic) between you and <?php echo $options['website_name']; ?> with respect to the Site. If any provision of these Terms of Service is found to be unenforceable or invalid, that provision will be limited or eliminated to the minimum extent necessary so that these Terms of Service will otherwise remain in full force and effect and enforceable.</p>
<h3>13. Miscellaneous.</h3>
<p>a. Force Majeure.
<br /><?php echo $options['website_name']; ?> shall not be liable for any failure to perform its obligations hereunder where such failure results from any cause beyond <?php echo $options['website_name']; ?>’s reasonable control, including, without limitation, mechanical, electronic or communications failure or degradation.
<br />b. Assignment.
<br />These Terms of Service are personal to you, and are not assignable, transferable or sublicensable by you except with <?php echo $options['website_name']; ?>’s prior written consent. <?php echo $options['website_name']; ?> may assign, transfer or delegate any of its rights and obligations hereunder without consent.
<br />c. Agency.
<br />No agency, partnership, joint venture, or employment relationship is created as a result of these Terms of Service and neither party has any authority of any kind to bind the other in any respect.
<br />d. Notices.
<br />Unless otherwise specified in these Term of Service, all notices under these Terms of Service will be in writing and will be deemed to have been duly given when received, if personally delivered or sent by certified or registered mail, return receipt requested; when receipt is electronically confirmed, if transmitted by facsimile or e-mail; or the day after it is sent, if sent for next day delivery by recognized overnight delivery service.
<br />e. No Waiver.
<br />The failure of <?php echo $options['website_name']; ?> to enforce any part of these Terms of Service shall not constitute a waiver of its right to later enforce that or any other part of these Terms of Service. Waiver of compliance in any particular instance, does not mean that we will do so in the future. In order for any waiver of compliance with these Terms of Service to be binding, <?php echo $options['website_name']; ?> must provide you with written notice of such waiver, provided by one of its authorized representatives.
<br />f. Headings.
<br />The section and paragraph headings in these Terms of Service are for convenience only and shall not affect their interpretation.</p>
					</div>
				</div>
				<?php echo inner_footer(); ?>
			</div>
<?php
		break;

	default:
?>
		<div class="front-card">
			<div class="front-welcome">
				<div class="front-welcome-text"<?php echo $options['only_registered'] == "yes" ? ' style="top: 100px;"' : ''; ?>>
					<h1><?php echo $options['website_header']; ?></h1>
					<p><?php echo $options['website_slogan']; ?></p>
				</div>
			</div>
<?php
		if ($options['only_registered'] != "yes" ) {
?>
			<div class="front-url" id="front-url">
				<form action="#" id="form-add-url" class="profile" method="post" onsubmit="return add_url();">
					<table class="table-settings" style="width: 100%;">
						<tr>
							<td><input type="text" id="urls-url" name="url" class="widefat-main" placeholder="Paste a link to shorten it..." tabindex="1" title="Your long URL"></td>
							<td style="width: 150px; padding-left: 0px;">
								<input type="hidden" name="action" value="add_url">
								<button type="submit" class="button-main" tabindex="3">Shorten</button>
							</td>
						</tr>
					</table>
				</form>
				<div class="front-url-label"><em><a href="#" onclick="return switch_mainpage('signin');">Sign in</a> <?php echo $options['disable_api'] == 'yes' ? ' and enjoy extended statistics.' : ' and enjoy more features such as extended statistics and using our <a href="'.$url_base.'?page=api">API</a>.'; ?></em></div>
				<div class="loading-dark"></div>
			</div>
<?php
		}
?>
			<div class="front-signin"<?php echo $options['close_registration'] == 'yes' ? ' style="top: 70px;'.($options['only_registered'] == "yes" ? ' left: 536px;' : '').'"' : ($options['only_registered'] == "yes" ? ' style="left: 536px;"' : ''); ?>>
				<form action="#" id="form-signin" class="signin" method="post" onsubmit="return signin();">
					<input type="text" id="signin-email" class="widefat" name="email" title="E-mail" autocomplete="off" tabindex="1" placeholder="E-mail">
					<input type="password" id="signin-password" class="widefat" name="password" title="Password" autocomplete="off" tabindex="2" placeholder="Password">
					<table>
						<tr>
							<td>
								<a class="forgot" href="#" onclick="return switch_forgot();">Forgot password?</a>
							</td>
							<td class="align-right">
								<button type="submit" class="signin-btn" tabindex="4">Sign in</button>
							</td>
						</tr>
					</table>
				</form>
				<form action="#" id="form-forgot" class="remind" method="post" onsubmit="return remind();">
					<input type="text" id="remind-email" class="widefat" name="email" title="E-mail" autocomplete="off" tabindex="1" placeholder="E-mail">
					<table>
						<tr>
							<td>
								<a class="forgot" href="#" onclick="return switch_signin();">Sign in</a>
							</td>
							<td class="align-right">
								<button type="submit" class="signin-btn" tabindex="4">Reset password</button>
							</td>
						</tr>
					</table>
				</form>
				<div class="loading-dark"></div>
			</div>
<?php
		if ($options['close_registration'] != 'yes') {
?>
			<div class="front-signup"<?php echo $options['only_registered'] == "yes" ? ' style="left: 536px;"' : ''; ?>>
				<h2><strong>New to our service?</strong> Sign up</h2>
				<form action="#" class="signup" method="post" onsubmit="return signup();">
					<input type="text" id="signup-email" class="widefat" name="email" title="E-mail" autocomplete="off" tabindex="11" placeholder="E-mail">
					<input type="password" id="signup-password" class="widefat" name="password" title="Password" autocomplete="off" tabindex="12" placeholder="Password">
					<input type="password" id="signup-password2" class="widefat" name="password2" title="Confirm password" autocomplete="off" tabindex="13" placeholder="Confirm password">
					<table>
						<tr>
							<td>
								<?php echo $options['only_registered'] == "yes" ? '' : '<a class="forgot" href="#" onclick="return switch_mainpage(\'home\');">No, thanks!</a>'; ?>
							</td>
							<td class="align-right">
								<button type="submit" class="signup-btn-dark">Sign up now</button>
							</td>
						</tr>
					</table>
				</form>
				<div class="loading-dark"></div>
			</div>
<?php
		}
?>
		</div>
		<div class="footer">
			<ul>
				<li><span class="copyright">© <?php echo date("Y"); ?> <?php echo $options['website_name']; ?></span></li>
				<?php echo $options['disable_api'] == 'yes' ? '' : '<li><a href="'.$url_base.'?page=api" title="Our API">API</li>'; ?>
				<li><a href="<?php echo $url_base; ?>?page=terms" title="Terms of Service">Terms</li>
			</ul>
		</div>
<?php
		break;
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