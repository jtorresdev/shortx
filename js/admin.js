jQuery(document).ready(function() {
	jQuery.each(jQuery("label.checkbox"), function() {
		var id = jQuery(this).attr("for");
		if (jQuery("#"+id).val() == "yes") jQuery(this).css("background-position", "0 -53px");
		else jQuery(this).css("background-position", "0 -3px");
	});
	jQuery("label.checkbox").click(function() {
		var id = jQuery(this).attr("for");
		if (jQuery("#"+id).val() == "yes") {
			jQuery(this).css("background-position", "0 -3px");
			jQuery("#"+id).val("no");
		} else {
			jQuery(this).css("background-position", "0 -53px");
			jQuery("#"+id).val("yes");
		}
	});
});

function signin() {
	jQuery(".front-signin .loading").fadeIn(200);
	jQuery.post("ajax.php", {
		"login": jQuery("#signin-login").val(),
		"password": jQuery("#signin-password").val(),
		"action": "signin"
		},
		function(return_data) {
			jQuery(".front-signin .loading").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				show_notification("success", data.message, 3000);
				location.href = data.redirect_url;
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function save_settings() {
	jQuery(".front-settings .loading-dark").fadeIn(200);
	jQuery.post("ajax.php", jQuery("#form-settings").serialize(),
		function(return_data) {
			jQuery(".front-settings .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				show_notification("success", data.message, 3000);
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function save_profile() {
	jQuery(".front-settings .loading-dark").fadeIn(200);
	jQuery.post("ajax.php", jQuery("#form-profile").serialize(),
		function(return_data) {
			jQuery(".front-settings .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				show_notification("success", data.message, 3000);
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function update_api_key(id) {
	jQuery(".front-settings .loading-dark").fadeIn(200);
	jQuery.post("ajax.php", {"action": "update_api_key", "id": id},
		function(return_data) {
			jQuery(".front-settings .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				jQuery("#profile-api-key").val(data.api_key);
				show_notification("success", data.message, 3000);
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact administrator.", 3000);
			}
		}
	);
	return false;
}
function add_user() {
	jQuery(".front-table .loading-dark").fadeIn(200);
	jQuery.post("ajax.php", jQuery("#form-add-user").serialize(),
		function(return_data) {
			jQuery(".front-table .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				location.reload();
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function add_url() {
	jQuery(".front-table .loading-dark").fadeIn(200);
	jQuery.post("ajax.php", jQuery("#form-add-url").serialize(),
		function(return_data) {
			jQuery(".front-table .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				location.reload();
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function delete_user(id) {
	if (submit_operation()) {
		jQuery.post("ajax.php", {
			"id": id,
			"action": "delete_user"
			},
			function(return_data) {
				data = jQuery.parseJSON(return_data);
				var status = data.status;
				if (status == "OK") {
					location.reload();
				} else if (status == "ERROR") {
					show_notification("error", data.message, 3000);
				} else {
					show_notification("error", "Internal error. Please contact developer.", 3000);
				}
			}
		);
	}
	return false;
}
function block_user(id) {
	jQuery.post("ajax.php", {
		"id": id,
		"action": "block_user"
		},
		function(return_data) {
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				location.reload();
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function unblock_user(id) {
	jQuery.post("ajax.php", {
		"id": id,
		"action": "unblock_user"
		},
		function(return_data) {
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				location.reload();
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function delete_url(id) {
	if (submit_operation()) {
		jQuery.post("ajax.php", {
			"id": id,
			"action": "delete_url"
			},
			function(return_data) {
				data = jQuery.parseJSON(return_data);
				var status = data.status;
				if (status == "OK") {
					location.reload();
				} else if (status == "ERROR") {
					show_notification("error", data.message, 3000);
				} else {
					show_notification("error", "Internal error. Please contact developer.", 3000);
				}
			}
		);
	}
	return false;
}
function block_url(id) {
	jQuery.post("ajax.php", {
		"id": id,
		"action": "block_url"
		},
		function(return_data) {
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				location.reload();
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function unblock_url(id) {
	jQuery.post("ajax.php", {
		"id": id,
		"action": "unblock_url"
		},
		function(return_data) {
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				location.reload();
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact developer.", 3000);
			}
		}
	);
	return false;
}
function check_url_status(id) {
	jQuery(".front-table .loading-dark").fadeIn(200);
	jQuery.post("ajax.php", {
		"id": id,
		"action": "check_url_status"
		},
		function(return_data) {
			jQuery(".front-table .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				show_notification("success", data.message, 3000);
				jQuery("#status-"+id).html(data.response_status);
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
	} else if (type == "success") {
		jSuccess(message, {
			HorizontalPosition : 'center',
			ShowTimeEffect : 1000,
			HideTimeEffect : 1000,
			VerticalPosition : 'top',
			ShowOverlay : false,
			TimeShown : delay
		});
	} else if (type == "info") {
		jNotify(message, {
			HorizontalPosition : 'center',
			ShowTimeEffect : 1000,
			HideTimeEffect : 1000,
			VerticalPosition : 'top',
			ShowOverlay : false,
			TimeShown : delay
		});
	}
}
function submit_operation() {
	var answer = confirm("Do you really want to continue?");
	if (answer) return true;
	else return false;
}
function switch_mail_settings() {
	var method = jQuery("#settings-mail-method").val();
	if (method == 'mail') {
		jQuery(".mail-method-mail").fadeIn(0);
		jQuery(".mail-method-smtp").fadeOut(0);
	} else {
		jQuery(".mail-method-smtp").fadeIn(0);
		jQuery(".mail-method-mail").fadeOut(0);
	}
}