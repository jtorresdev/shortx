<?php
class ICDB {
	var $server = "";
	var $port = "";
	var $db = "";
	var $user = "";
	var $password = "";
	var $prefix = "";
	var $insert_id;
	var $link;

	function __construct($_server, $_port, $_db, $_user, $_password, $_prefix) {
		$this->server = $_server;
		$this->port = $_port;
		$this->db = $_db;
		$this->user = $_user;
		$this->password = $_password;
		$this->prefix = $_prefix;
		$host = $this->server;
		if (defined('DB_HOST_PORT') && !empty($this->port)) $host .= ':'.$this->port;
		$this->link = mysqli_connect($host, $this->user, $this->password) or die("Could not connect: " . mysqli_connect_error());
		mysqli_select_db($this->link, $this->db) or die ('Can not use database : ' . mysqli_error($this->link));
		mysqli_query($this->link, 'SET NAMES utf8');
	}
	
	function get_row($_sql) {
		$result = mysqli_query($this->link, $_sql) or die("Invalid query: " . mysqli_error($this->link));
		$row = mysqli_fetch_array($result, MYSQL_ASSOC);
		mysqli_free_result($result);
		return $row;
	}
	
	function get_rows($_sql) {
		$rows = array();
		$result = mysqli_query($this->link, $_sql) or die("Invalid query: " . mysqli_error($this->link));
		while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$rows[] = $row;
		}
		mysqli_free_result($result);
		return $rows;
	}

	function get_var($_sql) {
		$result = mysqli_query($this->link, $_sql) or die("Invalid query: " . mysqli_error($this->link));
		$row = mysqli_fetch_array($result, MYSQL_NUM);
		mysqli_free_result($result);
		if ($row && is_array($row)) return $row[0];
		return false;
	}
	
	function query($_sql) {
		$result = mysqli_query($this->link, $_sql) or die("Invalid query: " . mysqli_error($this->link));
		$this->insert_id = mysqli_insert_id($this->link);
		return $result;
	}
	
	function escape_string($_string) {
		return mysqli_real_escape_string($this->link, $_string);
	}
}
?>