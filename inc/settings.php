<?php
/** DO NOT MODIFY OPTIONS BELOW. YOU CAN MODIFY THEM VIA ADMIN PANEL. */
define('VERSION', '1.1');
define('RECORDS_PER_PAGE', '20');
define('TABLE_PREFIX', 'url_');
define('DEMO_MODE', false);
define('ABSPATH', dirname(dirname(__FILE__)));

$alphabet = str_split('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
shuffle($alphabet);
$options = array(
	"version" => VERSION,
	"website_name" => 'Shortix',
	"website_url" => '',
	"website_header" => 'Your own URL Shortener!',
	"website_slogan" => 'Welcome to Shortix, a web service that provides short aliases for redirection of long URLs.',
	"salt" => random_string(16),
	"alphabet" => implode($alphabet),
	"close_registration" => 'no',
	"disable_api" => "no",
	"only_registered" => "no",
	"mail_method" => "mail",
	"mail_from_name" => "Shortix",
	"mail_from_email" => "noreply@".str_replace("www.", "", $_SERVER["SERVER_NAME"]),
	"smtp_server" => '',
	"smtp_port" => '',
	"smtp_secure" => 'none',
	"smtp_username" => '',
	"smtp_password" => '',
	"activation_email_subject" => 'Activate your account',
	"activation_email_body" => 'Hi there,'.PHP_EOL.PHP_EOL.'Click the link below to complete registration:'.PHP_EOL.'{activation_link}'.PHP_EOL.PHP_EOL.'Thanks,'.PHP_EOL.'{website_name}',
	"resetpassword_email_subject" => 'Reset password',
	"resetpassword_email_body" => 'Hi there,'.PHP_EOL.PHP_EOL.'Seems you requested to reset your password.'.PHP_EOL.'The new password is: {password}'.PHP_EOL.PHP_EOL.'Thanks,'.PHP_EOL.'{website_name}',
	"login" => "admin",
	"password" => md5('admin')
);

$user_options = array();

$mail_methods = array('mail' => 'PHP Mail() function', 'smtp' => 'SMTP');
$smtp_secures = array('none' => 'None', 'ssl' => 'SSL', 'tls' => 'TLS');

?>