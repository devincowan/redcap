$(function(){
	$('#redcap_updates_community_user').change(function(){
		redcap_updates_community_user = $(this).val();
	});
	$('#redcap_updates_community_password').change(function(){
		decrypt_pass = 0;
		redcap_updates_community_password = $(this).val();
	});
	if ($('#pid-go-project').length) {
		$('#pid-go-project').focus();
	}
});

// [JS] Expand Abbreviated IPv6 Addresses
// by Christopher Miller
// http://forrst.com/posts/JS_Expand_Abbreviated_IPv6_Addresses-1OR
// Modified to work with embedded IPv4 addresses
function expandIPv6Address(address)
{
	var fullAddress = "";
	var expandedAddress = "";
	var validGroupCount = 8;
	var validGroupSize = 4;

	var ipv4 = "";
	var extractIpv4 = /([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})/;
	var validateIpv4 = /((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})/;

	// look for embedded ipv4
	if(validateIpv4.test(address))
	{
		var groups = address.match(extractIpv4);
		for(var i=1; i<groups.length; i++)
		{
			ipv4 += ("00" + (parseInt(groups[i], 10).toString(16)) ).slice(-2) + ( i==2 ? ":" : "" );
		}
		address = address.replace(extractIpv4, ipv4);
	}

	if(address.indexOf("::") == -1) // All eight groups are present.
		fullAddress = address;
	else // Consecutive groups of zeroes have been collapsed with "::".
	{
		var sides = address.split("::");
		var groupsPresent = 0;
		for(var i=0; i<sides.length; i++)
		{
			groupsPresent += sides[i].split(":").length;
		}
		fullAddress += sides[0] + ":";
		for(var i=0; i<validGroupCount-groupsPresent; i++)
		{
			fullAddress += "0000:";
		}
		fullAddress += sides[1];
	}
	var groups = fullAddress.split(":");
	for(var i=0; i<validGroupCount; i++)
	{
		while(groups[i].length < validGroupSize)
		{
			groups[i] = "0" + groups[i];
		}
		expandedAddress += (i!=validGroupCount-1) ? groups[i] + ":" : groups[i];
	}
	return expandedAddress;
}

// Validate IP ranges for 2FA
// If all are valid, returns true, else returns comma-delimited list of invalid IPs.
function validateIpRanges(ranges) {
	// Remove all whitespace
	ranges = ranges.replace(/\s+/, "");
	// Replace all semi-colons with commas
	ranges = ranges.replace(/;/g, ",");
	// Replace all dashes with commas (so we can treat min/max of range as separate IPs)
	ranges = ranges.replace(/-/g, ",");
	// Now split into individual IP address components to check format via regex
	var ranges_array = ranges.split(',');
	var regex_ip4 = /^((\*|[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}(\*|[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/([0-9]|[1-2][0-9]|3[0-2]))?$/;
	var regex_ip6 = /^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/g;
	var bad_ips = new Array(), thisRange, thisIPv6, match_ip4, match_ip6, ipv6parts;
	if (ranges != "") {
		var k=0;
		for (var i=0; i<ranges_array.length; i++) {
			thisRange = trim(ranges_array[i]);
			match_ip4 = (thisRange.match(regex_ip4) != null);
			match_ip6 = false;
			// try IPv6 range
			if (!match_ip4 && thisRange.indexOf("/") > 0) {
				ipv6parts = thisRange.split("/");
				thisIPv6 = expandIPv6Address(ipv6parts[0]);
				match_ip6 = (thisIPv6.match(regex_ip6) != null && isNumeric(ipv6parts[1]) && ipv6parts[1] >=1 && ipv6parts[1] <= 128);
			}
			if (!match_ip4 && !match_ip6) {
				bad_ips[k++] = thisRange;
			}
		}
	}
	// Display error msg if any IPs are invalid
	if (bad_ips.length > 0) {
		return bad_ips.join(',');
	}
	return true;
}

// Test if string is a valid domain name (i.e. domain from a URL)
function isDomainName(domain) {
    // Set regex to be used to validate the domain
    var dwRegex = /^([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i;
    // Return boolean
    return dwRegex.test(trim(domain));
}

// Opens dialog popup for viewing all users in Control Center
function openUserHistoryList() {
	var days = $('#activity-level').val();
	var title = $('#activity-level option:selected').text();
	var search_term = '';
	var search_attr = '';
	if ($('#user_list_search').length) {
		search_term = trim($('#user_list_search').val());
		search_attr = $('#user_list_search_attr').val();
	}
	// Set progress spinner and reset div
	$('#userListTable').css('visibility','hidden');
	$('#userListProgress, #userList').show();
	$('#indv_user_info').html('');
	$('#user_search').val('').trigger('blur');
	// Ajax call
	$.get(app_path_webroot+'ControlCenter/user_list_ajax.php', { d: days, search_term: search_term, search_attr: search_attr}, function(data) {
		// Inject table		
		$('#userListTable').css('visibility','visible');
		$('#userList, #userListTable').show();
		$('#userListProgress').hide();
		$('#userListTable').html(data);
	});
}

// Download CSV list of users listed in popup
function downloadUserHistoryList() {
	var days = $('#activity-level').val();
	var search_term = '';
	var search_attr = '';
	if ($('#user_list_search').length) {
		search_term = trim($('#user_list_search').val());
		search_attr = $('#user_list_search_attr').val();
	}
	window.location.href = app_path_webroot+'ControlCenter/user_list_ajax.php?download=1&d='+days+'&search_term='+encodeURIComponent(search_term)+'&search_attr='+encodeURIComponent(search_attr);
}

// Perform one-click upgrade
var selected_version, decrypt_pass=1; 
function oneClickUpgrade(version) {
	selected_version = version;
	$('#oneClickUpgradeDialogVersion').html(version);
	simpleDialog(null,null,'oneClickUpgradeDialog',650,null,'Cancel',function(){
		if (redcap_updates_community_user == '' || redcap_updates_community_password == '') {
			simpleDialog("You must enter a valid REDCap Community username/password in order to begin the upgrade process.", "ERROR",null,500,function(){
				oneClickUpgrade(selected_version);	
			});
		} else {
			oneClickUpgradeDo(version);
		}
	},'Upgrade');
}
function oneClickUpgradeDo(version) {
	selected_version = version;
	showProgress(1);
	$('#working').css('width','auto').html('<img src="'+app_path_images+'progress_circle.gif">&nbsp; Downloading & extracting REDCap '+version+'...</div>');
	$.post(app_path_webroot+'index.php?route=ControlCenterController:oneClickUpgrade',{decrypt_pass: decrypt_pass, version: version, redcap_updates_community_user: redcap_updates_community_user, redcap_updates_community_password: redcap_updates_community_password},function(data){
		if (data == '1') {			
			$('#working').html('<img src="'+app_path_images+'progress_circle.gif">&nbsp; Executing SQL upgrade script...</div>');
			$.post(app_path_webroot+'index.php?route=ControlCenterController:executeUpgradeSQL',{version: version},function(data2){
				showProgress(0,0);
				$('#working').remove();
				if (data2 == '1') {					
					simpleDialog("<div class='green'>The upgrade to REDCap "+selected_version+" has completed successfully. You will now be redirected to the Configuration Check page.</div>", "<img src='"+app_path_images+"tick.png'> <span style='color:green;'>Upgrade Complete!</span>",null,500,function(){
						window.location.href = app_path_webroot_full+'redcap_v'+selected_version+'/ControlCenter/check.php?upgradeinstall=1';
					},"Go to Configuration Check");
				} else if (data2 == '2') {
					simpleDialog("NOTICE: The upgrade to REDCap "+selected_version+" cannot be completed automatically, so you will need to finish the upgrade by navigating to the "+
								 "<a style='text-decoration:underline;' href='"+app_path_webroot_full +"upgrade.php'>REDCap Upgrade Module</a>.");
				} else if (data2 == '3') {
					// AWS: Redirect to upgrade page
					simpleDialog("<div class='green'>REDCap "+selected_version+" has downloaded successfully, but you must still <b>wait while the new "+
						"download is being deployed to all nodes/servers. This may take several seconds or several minutes.</b> Please wait on this page until this is done, after which the rest of the upgrade "+
						"process will continue automatically.</div>", 
						"<img src='"+app_path_images+"tick.png'> <span style='color:green;'>Download Complete - PLEASE WAIT - DO NOT LEAVE THIS PAGE</span>",'aws-upgrade-dialog-wait',600);
					// Hide the close buttons to encourage user to wait on the page for a bit
					$('#aws-upgrade-dialog-wait').dialog({ closeOnEscape: false });
					modifyURL(app_path_webroot_full+'redcap_v'+selected_version+'/upgrade.php?auto=1');
					$('.ui-dialog .ui-dialog-buttonpane button, .ui-dialog .ui-dialog-titlebar-close').hide();
					// Keep calling the upgrade page via AJAX for new version until it no longer returns a 404 error
					check404onUpgradePage(selected_version);
				} else {
					simpleDialog("ERROR: For unknown reasons, the upgrade could not be completed.");
				}
			});
		} else {
			showProgress(0,0);
			$('#working').remove();
			simpleDialog(data,"ERROR",null,500,function(){
				oneClickUpgrade(selected_version);	
			});
		}
	});
}

function check404onUpgradePage(version) {
	var upgradeUrl = app_path_webroot_full+'redcap_v'+version+'/upgrade.php';
	$.ajax({ 
		cache: false,
		url: upgradeUrl,
		data: {  },
		success: function (data) {
			window.location.href = upgradeUrl+'?auto=1';
		},
		error:function (xhr, ajaxOptions, thrownError){
			if(xhr.status==404) {				
				setTimeout("check404onUpgradePage('"+version+"')",3000);
			}
		}
	});
}

function autoFixTables() {
	showProgress(1);
	$.post(app_path_webroot+'index.php?route=ControlCenterController:autoFixTables',{ },function(data){	
		if (data != '1') {
			showProgress(0,0);
			alert(woops);
			return;
		}
		window.location.reload();
	});
}
function hideEasyUpgrade(hide) {
	$.post(app_path_webroot+'index.php?route=ControlCenterController:hideEasyUpgrade',{hide:hide },function(data){	
		if (data != '1') {
			alert(woops);
			return;
		}
		if (hide == '1') {
			window.location.reload();
		} else {
			$('#easy_upgrade_alert').removeClass('gray2').addClass('blue');
			$('.redcap-updates-rec').show();
		}
	});
}

// Save a new value for a config setting (super users only)
function setConfigVal(settingName,value,reloadPage) {
	$.post(app_path_webroot+'ControlCenter/set_config_val.php',{ settingName: settingName, value: value },function(data){
		if (data == '1') {
			alert("The setting has been successfully saved!");
			if (reloadPage != null && reloadPage) {
				window.location.reload();
			}
		} else {
			alert(woops);
		}
	});
}

// Show dialog of project revision history
function revHist(this_pid) {
	$.get(app_path_webroot+'ProjectSetup/project_revision_history.php?pid='+this_pid,{},function(data){
		initDialog('revHist','<div style="height:400px;">'+data+'</div>');
		var d = $('#revHist').dialog({ bgiframe: true, title: $('#revHist #revHistPrTitle').text(), modal: true, width: 800, buttons: {
				Close: function() { $(this).dialog('close'); }
			}});
		initButtonWidgets();
		fitDialog(d);
	});
}