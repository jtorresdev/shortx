<?php
function get_options() {
	global $icdb, $options;
	$rows = $icdb->get_rows("SELECT * FROM ".$icdb->prefix."options");
	foreach ($rows as $row) {
		if (array_key_exists($row['options_key'], $options)) $options[$row['options_key']] = $row['options_value'];
	}
}

function update_options() {
	global $icdb, $options;
	foreach ($options as $key => $value) {
		$option = $icdb->get_row("SELECT * FROM ".$icdb->prefix."options WHERE options_key = '".$icdb->escape_string($key)."'");
		if ($option) {
			$icdb->query("UPDATE ".$icdb->prefix."options SET options_value = '".$icdb->escape_string($value)."' WHERE options_key = '".$icdb->escape_string($key)."'");
		} else {
			$icdb->query("INSERT INTO ".$icdb->prefix."options (options_key, options_value) VALUES ('".$icdb->escape_string($key)."', '".$icdb->escape_string($value)."')");
		}
	}
}

function populate_options() {
	global $icdb, $options;
	foreach ($options as $key => $value) {
		if ($key != 'password' && $key != 'login') {
			if (isset($_POST[$key])) {
				$options[$key] = trim(stripslashes($_POST[$key]));
			}
		}
	}
}

function check_options() {
	global $icdb, $options;
	$errors = array();
	if (strlen($options['website_name']) < 3) $errors[] = 'Website name is too short.';
	if (!preg_match('~^((http(s)?://)|(//))[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$~i', $options['website_url']) || strlen($options['website_url']) == 0) $errors[] = "Website URL must be valid URL.";
	if (strlen($options['website_header']) < 3) $errors[] = 'Website header is too short.';
	if (strlen($options['website_slogan']) < 3) $errors[] = 'Website slogan is too short.';
	if ($options['mail_method'] == 'mail') {
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,9})$/i", $options['mail_from_email']) || strlen($options['mail_from_email']) == 0) $errors[] = 'Sender e-mail must be valid e-mail address.';
		if (strlen($options['mail_from_name']) < 3) $errors[] = 'Sender name is too short.';
	} else if ($options['mail_method'] == 'smtp') {
		if (strlen($options['smtp_server']) < 2) $errors[] = 'SMTP server can not be empty.';
		if (intval($options['smtp_port']) != $options['smtp_port'] || intval($options['smtp_port']) < 1 || intval($options['smtp_port']) > 65535) $errors[] = 'SMTP port must be valid integer value in range [1...65535].';
		if (strlen($options['smtp_username']) < 2) $errors[] = 'SMTP username can not be empty.';
		if (strlen($options['smtp_password']) < 1) $errors[] = 'SMTP password can not be empty.';
	}
	if (strlen($options['activation_email_subject']) < 3) $errors[] = 'Subject of account activation e-mail must contain at least 3 characters.';
	else if (strlen($options['activation_email_subject']) > 64) $errors[] = 'Subject of account activation e-mail must contain maximum 64 characters.';
	if (strlen($options['activation_email_body']) < 3) $errors[] = 'Body of account activation e-mail must contain at least 3 characters.';
	if (strlen($options['resetpassword_email_subject']) < 3) $errors[] = 'Subject of reset password e-mail must contain at least 3 characters.';
	else if (strlen($options['resetpassword_email_subject']) > 64) $errors[] = 'Subject of reset password e-mail must contain maximum 64 characters.';
	if (strlen($options['resetpassword_email_body']) < 3) $errors[] = 'Body of reset password e-mail must contain at least 3 characters.';
	return $errors;
}

function url_code($_id) {
	global $options;
	$result = 0;
	for ($i=0; $i<24; $i++) {
		if ($_id & (1<<$i)) $result |= 1<<(23-$i);
	}
	$result |= $_id & (127<<24);
	$url_code = '';
	$alpha = str_split($options['alphabet']);
	for ($i=5; $i>0; $i--) {
		$k = pow(62, $i);
		$tmp = floor($result/$k);
		if ($tmp > 0 || ($tmp == 0 && !empty($url_code))) {
			$url_code .= $alpha[$tmp];
			$result -=  $tmp*$k;
		}
	}
	$url_code .= $alpha[$result];
	return $url_code;
}

function page_switcher ($_urlbase, $_currentpage, $_totalpages, $_ajax = false) {
	$pageswitcher = "";
	if ($_totalpages > 1) {
		$pageswitcher = '<div class="paginator"><span class="pagiation-links">';
		if (strpos($_urlbase,"?") !== false) $_urlbase .= "&amp;";
		else $_urlbase .= "?";
		if ($_currentpage == 1) $pageswitcher .= "<span>1</span> ";
		else $pageswitcher .= ' <a class="page" href="'.$_urlbase.'p=1"'.($_ajax ? ' onclick="return switch_page(1);"' : '').'>1</a> ';

		$start = max($_currentpage-3, 2);
		$end = min(max($_currentpage+3,$start+6), $_totalpages-1);
		$start = max(min($start,$end-6), 2);
		if ($start > 2) $pageswitcher .= " <strong>...</strong> ";
		for ($i=$start; $i<=$end; $i++) {
			if ($_currentpage == $i) $pageswitcher .= " <span>".$i."</span> ";
			else $pageswitcher .= ' <a class="page" href="'.$_urlbase.'p='.$i.'"'.($_ajax ? ' onclick="return switch_page('.$i.');"' : '').'>'.$i.'</a> ';
		}
		if ($end < $_totalpages-1) $pageswitcher .= " <strong>...</strong> ";

		if ($_currentpage == $_totalpages) $pageswitcher .= " <span>".$_totalpages."</span> ";
		else $pageswitcher .= ' <a class="page" href="'.$_urlbase.'p='.$_totalpages.'"'.($_ajax ? ' onclick="return switch_page('.$_totalpages.');"' : '').'>'.$_totalpages.'</a> ';
		$pageswitcher .= "</span></div>";
	}
	return $pageswitcher;
}

function random_string($_length = 16) {
	$symbols = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$string = "";
	for ($i=0; $i<$_length; $i++) {
		$string .= $symbols[rand(0, strlen($symbols)-1)];
	}
	return $string;
}

function cut_string($_string, $_limit=40) {
	if (strlen($_string) > $_limit) return substr($_string, 0, $_limit-3)."...";
	return $_string;
}

function add_url_parameters($_base, $_params) {
	if (strpos($_base, "?")) $glue = "&";
	else $glue = "?";
	$result = $_base;
	if (is_array($_params)) {
		foreach ($_params as $key => $value) {
			$result .= $glue.rawurlencode($key)."=".rawurlencode($value);
			$glue = "&";
		}
	}
	return $result;
}

function url_rewrite() {
	if (file_exists(dirname(dirname(__FILE__)).'/.htaccess')) $htaccess = true;
	else $htaccess = false;
	return $htaccess;
}

function ic_mail($_email, $_subject, $_body) {
	global $options;
	$_body = str_replace(array("\n", "\r"), array("<br />", ""), $_body);
	if ($options['mail_method'] == 'mail') {
		$mail_headers = "Content-Type: text/html; charset=utf-8\r\n";
		$mail_headers .= "From: ".$options['mail_from_name']." <".$options['mail_from_email'].">\r\n";
		$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
		$result = mail($_email, $_subject, $_body, $mail_headers);
		if (!$result) {
			throw new Exception('PHP mail() function seems disabled. Try SMTP.');
		}
	} else if ($options['mail_method'] == 'smtp') {
		include_once(dirname(__FILE__).'/class.phpmailer.php');
		$mail = new PHPMailer();
		$mail->IsSMTP();
		$mail->IsHTML(true);
		$mail->SMTPDebug  = 0;
		$mail->Host       = $options['smtp_server'];
		$mail->Port       = $options['smtp_port'];
		if ($options['smtp_secure'] != 'none') {
			$mail->SMTPSecure = $options['smtp_secure'];
		}
		$mail->SMTPAuth   = true;
		$mail->Username   = $options['smtp_username'];
		$mail->Password   = $options['smtp_password'];
		$mail->SetFrom($options['smtp_username'], $options['website_name']);
		$mail->AddAddress($_email, $_email);
		$mail->Subject = $_subject;
		$mail->CharSet = 'utf-8';
		$mail->Body = $_body;
		$mail->AltBody = $_body;
		if(!$mail->Send()) {
			throw new Exception('Mailer Error: '.$mail->ErrorInfo);
		} 
	} else {
		throw new Exception('Mailing method not found.');
	}
}

function response_code($_url) {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $_url);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_NOBODY, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)');
	curl_setopt($curl, CURLOPT_TIMEOUT, 15);
	curl_exec($curl);
	$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);
	return $code;
}
function install() {
	global $icdb;
	$table_name = $icdb->prefix."users";
	if($icdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
			id int(11) NULL AUTO_INCREMENT,
			name varchar(255) COLLATE utf8_unicode_ci NULL,
			email varchar(255) COLLATE utf8_unicode_ci NULL,
			password varchar(255) COLLATE utf8_unicode_ci NULL,
			activation_code varchar(31) COLLATE latin1_general_cs NULL,
			api_key varchar(31) COLLATE latin1_general_cs NULL,
			options text COLLATE utf8_unicode_ci NULL,
			created int(11) NULL,
			blocked int(11) NULL DEFAULT '0',
			activated int(11) NULL DEFAULT '0',
			deleted int(11) NULL DEFAULT '0',
			UNIQUE KEY id (id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
		$icdb->query($sql);
	}
	$table_name = $icdb->prefix."urls";
	if($icdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
			id int(11) NULL AUTO_INCREMENT,
			user_id int(11) NULL,
			url varchar(255) COLLATE utf8_unicode_ci NULL,
			url_code varchar(31) COLLATE latin1_general_cs NULL,
			redirects bigint(20) NULL DEFAULT '0',
			created int(11) NULL,
			blocked int(11) NULL DEFAULT '0',
			deleted int(11) NULL DEFAULT '0',
			UNIQUE KEY id (id),
			KEY url (url),
			KEY url_code (url_code)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
		$icdb->query($sql);
	}
	$table_name = $icdb->prefix."log";
	if($icdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
			id bigint(20) NULL AUTO_INCREMENT,
			url_id int(11) NULL,
			options text COLLATE utf8_unicode_ci NULL,
			created_day int(11) NULL,
			created int(11) NULL,
			deleted int(11) NULL DEFAULT '0',
			UNIQUE KEY id (id),
			KEY url_id (url_id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
		$icdb->query($sql);
	}
	$table_name = $icdb->prefix."options";
	if($icdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
			id int(11) NULL AUTO_INCREMENT,
			options_key varchar(255) COLLATE utf8_unicode_ci NULL,
			options_value text COLLATE utf8_unicode_ci NULL,
			UNIQUE KEY id (id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
		$icdb->query($sql);
		update_options();
	}
	$table_name = $icdb->prefix."sessions";
	if($icdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
			id int(11) NULL AUTO_INCREMENT,
			user_id int(11) NULL,
			ip varchar(31) COLLATE utf8_unicode_ci NULL,
			session_id varchar(255) COLLATE utf8_unicode_ci NULL,
			created int(11) NULL,
			valid_period int(11) NULL,
			UNIQUE KEY id (id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
		$icdb->query($sql);
	}
}

function urls_page($search_query = '', $p = 1) {
	global $options, $active_user, $icdb, $url_base, $user_pages, $page;
	
	$total = $icdb->get_var("SELECT COUNT(*) AS total FROM ".$icdb->prefix."urls WHERE deleted = '0' AND user_id = '".$active_user['id']."'".(!empty($search_query) ? " AND (url_code LIKE '%".$icdb->escape_string($search_query)."%' OR url LIKE '%".$icdb->escape_string($search_query)."%')" : ""));
	$totalpages = ceil($total/RECORDS_PER_PAGE);
	if ($totalpages == 0) $totalpages = 1;
	if ($p < 1 || $p > $totalpages) $p = 1;
	$switcher = page_switcher($url_base.(!empty($search_query) ? '?s='.urlencode($search_query) : ''), $p, $totalpages, true);
	$rows = $icdb->get_rows("SELECT * FROM ".$icdb->prefix."urls WHERE deleted = '0' AND user_id = '".$active_user['id']."'".(!empty($search_query) ? " AND (url_code LIKE '%".$icdb->escape_string($search_query)."%' OR url LIKE '%".$icdb->escape_string($search_query)."%')" : "")." ORDER BY created DESC LIMIT ".(($p-1)*RECORDS_PER_PAGE).", ".RECORDS_PER_PAGE);
	$htaccess = url_rewrite();

	$content = '
					<form action="'.$url_base.'" id="form-add-url" class="profile" method="get" onsubmit="return do_search();">
						<table class="table-search">
							<tr>
								<td><input type="text" id="search" name="s" class="widefat" value="'.htmlspecialchars($search_query, ENT_QUOTES).'" placeholder="Search shortened URL..." tabindex="1" title="New monitoring URL"></td>
								<td>
									<button type="submit" class="signin-btn" tabindex="2">Search</button>
									'.(!empty($search_query) ? '<button class="signup-btn-dark" tabindex="3" onclick="return reset_search();">Reset search result</button>' : '').'
								</td>
							</tr>
						</table>
					</form>
					'.($totalpages > 1 ? '<div class="paginator-top">'.$switcher.'</div>' : '').'
					<table class="table-listing">
						<tr>
							<th>URL</th>
							<th style="width: 80px; text-align: right; padding-right: 20px;">Redirects</th>
							<th style="width: 70px;">Actions</th>
						</tr>';
	if (sizeof($rows) > 0) {
		foreach ($rows as $row) {
			$domain = parse_url($row['url'], PHP_URL_HOST);
			$url_label = cut_string($row['url'], 80);
			$content .= '
						<tr'.($row['blocked'] == 1 ? ' style="color: #888;"' : '').'>
							<td>'.$url_base.($htaccess ? '' : '?u=').$row['url_code'].'<br /><em><a href="'.$row['url'].'" target="_blank">'.htmlspecialchars($url_label, ENT_QUOTES).'</a></em></td>
							<td style="text-align: right;"><span style="padding-right: 20px;">'.$row['redirects'].'</span></td>
							<td>
								<a href="'.$url_admin.'?page=statistics&id='.$row['id'].'" title="Show statistics"><img src="'.$url_base.'img/chart.png" alt="Show statistics" border="0"></a>
								'.($row['blocked'] == 0 ? '<a href="#" title="Block shortened URL" onclick="return block_url('.$row['id'].');"><img src="'.$url_base.'img/block.png" alt="Block shortened URL" border="0"></a>' : '<a href="#" title="Unblock shortened URL" onclick="return unblock_url('.$row['id'].');"><img src="'.$url_base.'img/unblock.png" alt="Unblock shortened URL" border="0"></a>').'
								<a href="#" title="Delete shortened URL" onclick="return delete_url('.$row['id'].');"><img src="'.$url_base.'img/delete.png" alt="Delete shortened URL" border="0"></a>
							</td>
						</tr>';
		}
	} else {
		$content .= '
						<tr>
							<td colspan="3" class="no-records">No records found.</td>
						</tr>';
	}
	$content .= '
					</table>
					'.($totalpages > 1 ? '<div class="paginator-bottom">'.$switcher.'</div>' : '').'
					<div class="legend">
						<ul>
							<li><img src="'.$url_base.'img/chart.png" alt="Show statistics" border="0" style="vertical-align: middle;"> Show statistics</li>
							<li><img src="'.$url_base.'img/block.png" alt="Block monitoring URL" border="0" style="vertical-align: middle;"> Block URL</li>
							<li><img src="'.$url_base.'img/unblock.png" alt="Unblock monitoring URL" border="0" style="vertical-align: middle;"> Unblock URL</li>
							<li><img src="'.$url_base.'img/delete.png" alt="Delete monitoring URL" border="0" style="vertical-align: middle;"> Delete URL</li>
						</ul>
					</div>
					<div class="loading-dark"></div>
					<input type="hidden" id="search_query" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
					<input type="hidden" id="page_number" value="'.htmlspecialchars($p, ENT_QUOTES).'">';
	return $content;
}

function inner_footer() {
	global $options, $url_base;
	$footer = '
		<div class="inner-footer">
			<ul>
				<li><span class="copyright">© '.date("Y").' '.$options['website_name'].'</span></li>
				'.($options['disable_api'] == 'yes' ? '' : '<li><a href="'.$url_base.'?page=api" title="Our API">API</li>').'
				<li><a href="'.$url_base.'?page=terms" title="Terms of Service">Terms</li>
			</ul>
		</div>';
	return $footer;
}
?>