function changeDeviceNickname(device_id, nickname, uuid)
{
    $.post(app_path_webroot+'MobileApp/index.php?dashboard=1&pid='+pid, { "device_id":device_id, "nickname":nickname }, function(data) {
        if ($("#nickname-table_"+uuid).length) {
            $("#nickname-table_"+uuid).html(data);
        }
		$("#"+device_id+"_nickname").val(data);
		saveDeviceNickname(device_id, uuid, data);
    });
}

function editDeviceNickname(id) {
        $("#"+id+"_nickname").show();
        $("#"+id+"_active").show();
        $("#"+id+"_displayname").hide();
        $("#"+id+"_namerow").hide();
        $("#"+id+"_button").show();
        $("#"+id+"_edit").hide();
}

function saveDeviceNickname(id, uuid, nickname) {
        $("#"+id+"_displayname").html($("#"+id+"_nickname").val());
        $("#"+id+"_nickname").hide();
        $("#"+id+"_active").hide();
        $("#"+id+"_displayname").show();
        $("#"+id+"_namerow").show();
        $("#"+id+"_button").hide();
        $("#"+id+"_edit").show();
        if (nickname.match(/\S/))
        {
            $("span[id^='row."+uuid+"']").html(nickname+" ("+uuid+")");
        }
        else
        {
            $("span[id^='row."+uuid+"']").html(uuid);
        }
}

function revokeDevice(b, uuid, id, mssg, buttontext, oktext)
{
        var cb = function() { };
        if (b)
        {
                cb = function() {
                                         $.post("update.php?pid="+pid, {"device_id" : id, "revoke" : 1 }, function(data) {
                                                 $("span[id^='row."+uuid+"']").css({ "color" : "red" })
                                                 $("#"+id+"_revoke").val("1");
                                                 $("#"+id+"_revokelink").html(buttontext);
                                                 $("#"+id+"_blocked").show();
                                                 $("#"+id+"_unblocked").hide();
                                         });
                                };
        }
        else
        {
                cb = function() {
                                         $.post("update.php?pid="+pid, {"device_id" : id, "revoke" : 0 }, function(data) {
                                                 $("span[id^='row."+uuid+"']").css({ "color" : "black" })
                                                 $("#"+id+"_revoke").val("0");
                                                 $("#"+id+"_revokelink").html(buttontext);
                                                 $("#"+id+"_blocked").hide();
                                                 $("#"+id+"_unblocked").show();
                                         });
                                };
        }
        simpleDialog(mssg, null, null, null, null, "Cancel", cb, oktext);
}
function showDeviceTables() {
        $("table[id^='table-']").show();
}

function hideDeviceTables() {
        $("table[id^='table-']").hide();
        $("#table-devices").show();
}
function openAppCodeDialog() {
	var title = "<i class='fas fa-tablet-alt'></i> " +
				"<span style='vertical-align:middle;'>"+$('#app_codes_dialog').attr('title')+"</span>";
	simpleDialog(null,title,'app_codes_dialog',550);
	$('#app_codes_dialog').attr('title','');
}
function getAppCode() {
	$('#app_user_codes_div').show();
	$('#app_user_codes_timer_div').hide();
	var val = "" + Math.floor(Math.random() * 10000);
	while (val.length < 4) val = "0" + val;
	// Get init code from Vanderbilt server
	$.get(app_path_webroot + "API/project_api_ajax.php?pid="+pid, { action: "getAppCode", user_code: val }, function (data) {
		if (data == '') {
			simpleDialog(lang_getAppCode1,lang_getAppCode2);
			$('#appCodeAltDiv').hide();
			setTimeout(function(){
				$('#appCodeAltDiv').hide();
			},500);
			return;
		}
		$('#app_code').val(data+val).effect('highlight',{ },3000);
		// After X minutes of being displayed,
		setTimeout(function(){
			$('#app_user_codes_div').hide();
			$('#app_user_codes_timer_div').show();
		},600000); // 10 minutes
	});
}

function validateAppCode(validation_code) {
	$.ajax({
        type: 'POST',
        url: 'https://redcap.vanderbilt.edu/consortium/app/validate_code.php',
        crossDomain: true,
        data: { validation_code: validation_code },
        success: function(data) {
			var json_data = jQuery.parseJSON(data);
			// Get REDCap base URL, API token, project ID, and error message (if returns an error)
			var error_msg = json_data.error;
			var redcap_url = json_data.url;
			var redcap_api_token = json_data.token;
			var redcap_project_id = json_data.project_id;
			var redcap_project_title = json_data.project_title;
			var redcap_api_username = json_data.username;
			// Check for error
			if (error_msg != '') {
				alert(error_msg);
				return;
			}
			// Display
			$('#app_redcap_url').html(redcap_url);
			$('#app_redcap_token').html(redcap_api_token);
			$('#app_redcap_username').html(redcap_api_username);
			$('#app_redcap_project_id').html(redcap_project_id);
			$('#app_redcap_project_title').html(redcap_project_title);
        },
        error: function(e) {
            alert("ERROR: "+e.status+": "+e.statusText);
        }
    });
}