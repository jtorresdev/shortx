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

function switch_mainpage(mode) {
	if (mode == "signin") {
		jQuery(".front-url").animate({top : "348", opacity : 0}, 200, function() {
			jQuery(".front-signin").animate({left : "536"}, 200);
			jQuery(".front-signup").animate({left : "536"}, 300);
		});
		jQuery(".front-welcome-text").animate({top : "100"}, 300);
	} else if (mode == "home") {
		jQuery(".front-signup").animate({left : "900"}, 200);
		jQuery(".front-signin").animate({left : "900"}, 300, function() {
			jQuery(".front-url").animate({top : "120", opacity : 1}, 300);
			jQuery(".front-welcome-text").animate({top : "30"}, 200);
		});
	}
	return false;
}

function switch_forgot() {
	jQuery("#form-signin").slideUp(300);
	jQuery("#form-forgot").slideDown(300);
}
function switch_signin() {
	jQuery("#form-signin").slideDown(300);
	jQuery("#form-forgot").slideUp(300);
}
function signup() {
	jQuery(".front-signup .loading-dark").fadeIn(200);
	jQuery.post(url_base+"ajax.php", {
		"email": jQuery("#signup-email").val(),
		"password": jQuery("#signup-password").val(),
		"password2": jQuery("#signup-password2").val(),
		"action": "signup"
		},
		function(return_data) {
			jQuery(".front-signup .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
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
function signin() {
	jQuery(".front-signin .loading-dark").fadeIn(200);
	jQuery.post(url_base+"ajax.php", {
		"email": jQuery("#signin-email").val(),
		"password": jQuery("#signin-password").val(),
		"action": "signin"
		},
		function(return_data) {
			jQuery(".front-signin .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				show_notification("success", data.message, 3000);
				location.href = data.redirect_url;
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact administrator.", 3000);
			}
		}
	);
	return false;
}
function remind() {
	jQuery(".front-signin .loading-dark").fadeIn(200);
	jQuery.post(url_base+"ajax.php", {
		"email": jQuery("#remind-email").val(),
		"action": "remind"
		},
		function(return_data) {
			jQuery(".front-signin .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
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
function save_profile() {
	jQuery(".front-settings .loading-dark").fadeIn(200);
	jQuery.post(url_base+"ajax.php", jQuery("#form-profile").serialize(),
		function(return_data) {
			jQuery(".front-settings .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
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
function update_api_key() {
	jQuery(".front-settings .loading-dark").fadeIn(200);
	jQuery.post(url_base+"ajax.php", {"action": "update_api_key"},
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
function add_url() {
	jQuery("#front-url .loading-dark").fadeIn(200);
	jQuery.post(url_base+"ajax.php", jQuery("#form-add-url").serialize(),
		function(return_data) {
			jQuery("#front-url .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				jQuery("#urls-url").val(data.url);
			} else if (status == "OK2") {
				jQuery("#search_query").val("");
				jQuery("#page_number").val("");
				reload_urls("", 1);
				jQuery("#urls-url").val(data.url);
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact administrator.", 3000);
			}
		}
	);
	return false;
}
function reload_urls(search_query, page_number) {
	jQuery(".front-content .loading-dark").fadeIn(200);
	jQuery.post(url_base+"ajax.php", {
		"s": search_query,
		"p": page_number,
		"action": "reload_urls"
		},
		function(return_data) {
			jQuery(".front-content .loading-dark").fadeOut(200);
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				jQuery("#urls-page-content").html(data.content);
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact administrator.", 3000);
			}
		}
	);
}
function delete_url(id) {
	if (submit_operation()) {
		jQuery.post(url_base+"ajax.php", {
			"id": id,
			"action": "delete"
			},
			function(return_data) {
				data = jQuery.parseJSON(return_data);
				var status = data.status;
				if (status == "OK") {
					show_notification("success", data.message, 3000);
					reload_urls(jQuery("#search_query").val(), jQuery("#page_number").val());
				} else if (status == "ERROR") {
					show_notification("error", data.message, 3000);
				} else {
					show_notification("error", "Internal error. Please contact administrator.", 3000);
				}
			}
		);
	}
	return false;
}
function block_url(id) {
	jQuery.post(url_base+"ajax.php", {
		"id": id,
		"action": "block"
		},
		function(return_data) {
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				show_notification("success", data.message, 3000);
				reload_urls(jQuery("#search_query").val(), jQuery("#page_number").val());
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact administrator.", 3000);
			}
		}
	);
	return false;
}
function unblock_url(id) {
	jQuery.post(url_base+"ajax.php", {
		"id": id,
		"action": "unblock"
		},
		function(return_data) {
			data = jQuery.parseJSON(return_data);
			var status = data.status;
			if (status == "OK") {
				show_notification("success", data.message, 3000);
				reload_urls(jQuery("#search_query").val(), jQuery("#page_number").val());
			} else if (status == "ERROR") {
				show_notification("error", data.message, 3000);
			} else {
				show_notification("error", "Internal error. Please contact administrator.", 3000);
			}
		}
	);
	return false;
}
function do_search() {
	reload_urls(jQuery("#search").val(), 1);
	window.history.pushState("", "", url_base+"?s="+encodeURIComponent(jQuery("#search").val()));
	return false;
}
function reset_search() {
	reload_urls("", 1);
	window.history.pushState("", "", url_base);
	return false;
}
function switch_page(page_number) {
	reload_urls(jQuery("#search_query").val(), page_number);
	var s;
	if (jQuery("#search_query").val() != "") s = "&s="+encodeURIComponent(jQuery("#search_query").val());
	else s = "";
	window.history.pushState("", "", url_base+"?p="+page_number+s);
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
