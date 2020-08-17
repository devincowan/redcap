<?php


// Initialize vars as global since this file might get included inside a function
global $homepage_announcement, $homepage_grant_cite, $homepage_custom_text, $sendit_enabled, $edoc_field_option_enabled, $api_enabled;

// Show custom homepage announcement text (optional)
if (trim($homepage_announcement) != "" && isset($_SESSION['username'])) {
	print RCView::div(array('style'=>'margin-bottom:10px;'), nl2br(decode_filter_tags($homepage_announcement)));
}

print  "<div class='row'>
			<div class='col-12 col-md-7' style='padding-bottom:20px;'>";

// Link to consortium public site (only show on login page and only for redcap.vanderbilt.edu)
if (!isset($_SESSION['username']) && $_SERVER['SERVER_NAME'] == 'redcap.vanderbilt.edu')
{
	print  "<p class='blue' style='margin-bottom:20px;'>
				For more information about the global REDCap consortium, please visit
				<a target='_blank' style='text-decoration:underline;color:#800000;' href='https://projectredcap.org'>projectredcap.org</a>.
			</p>";
}

// Welcome message and instroduction
print  "<div>
			<div style='float:left;font-weight:bold;'>{$lang['info_01']}</div>
			<div class='".(isset($_SESSION['username']) ? 'd-block d-sm-none' : 'd-none')."' style='float:right;margin-right:20px;'>
				<button class='jqbuttonmed' onclick=\"window.location.href = '".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects';\"><img src='".APP_PATH_IMAGES."folders_stack.png'> <span style='vertical-align:middle;'>{$lang['setup_45']} {$lang['bottom_03']}</span></button>
			</div>
			<div class='clear'></div>
		</div>
		<p>
			{$lang['info_44']}
		</p>
		<p>
			{$lang['info_35']}
		</p>
		<p>
			{$lang['info_36']}
			<i class=\"fas fa-film\"></i> <a href='javascript:;' onclick=\"popupvid('redcap_overview_brief02','Brief Overview of REDCap')\" style='text-decoration:underline;'>{$lang['info_37']}</a>{$lang['period']}
			{$lang['info_38']}
			<a href='index.php?action=training' style='text-decoration:underline;'>{$lang['info_06']}</a>
			{$lang['global_14']}{$lang['period']}<br>
		</p>";

// Show grant name to cite (if exists)
if (trim($homepage_grant_cite) != "") {
	print  "<p>
				{$lang['info_08']}
				(<b'>$homepage_grant_cite</b>).
			</p>";
}

// Notice about usage for human subject research
?>
<p style='color:#C00000;'>
	<i><?php echo $lang['global_03'].$lang['colon'] ?></i> <?php echo $lang['info_10'] ?>
</p>

<?php

print  "<p>
			{$lang['info_11']}
			<a style='text-decoration:underline;' href='".
			(trim($homepage_contact_url) == '' ? "mailto:$homepage_contact_email" : trim($homepage_contact_url)) .
			"'>$homepage_contact</a>{$lang['period']}
		</p>";

// Show custom text defined by REDCap adminstrator on System Config page
if (trim($homepage_custom_text) != "") {
	$homepage_custom_text = nl2br(decode_filter_tags($homepage_custom_text));
	print "<div class='round'
		style='background-color:#E8ECF0;border:1px solid #99B5B7;margin:15px 10px 0 0;padding:5px 5px 5px 10px;'>$homepage_custom_text</div>";
}

print  "</div>";
print  "<div class='col-11 col-md-5'>";

// Features of REDCap (right-hand side)
print  '<div class="well" style="font-size:12px;">
				<h6 style="text-align:center;margin-top:0;">
					'.$lang['info_12'].'
				</h6>
				<p>
					<b>'.$lang['info_13'].'</b> - '.$lang['info_14'].'
				</p>
				<p>
					<b>'.$lang['info_15'].'</b> - '.$lang['info_16'].'
				</p>
				<p>
					<b>'.$lang['info_19'].'</b> - '.$lang['info_20'].'
				</p>
				<p>
					<b>'.$lang['info_23'].'</b> - '.$lang['info_24'].'
				</p>';
if ($pdf_econsent_system_enabled) {
    print '		<p>
					<b>'.$lang['info_45'].'</b> - '.$lang['info_46'].'
				</p>';
}
print '			<p>
					<b>'.$lang['info_32'].'</b> - '.$lang['info_33'].'
				</p>
				<p>
					<b>'.$lang['global_25'].'</b> - '.$lang['info_18'].'
				</p>';

// Display info about Mobile App, if enabled
if ($api_enabled && isset($mobile_app_enabled) && $mobile_app_enabled) {
	print "		<p>
					<b>{$lang['global_118']}</b> - {$lang['info_43']}
				</p>";
}

// Display ability to upload files via Send-It, if enabled
if ($sendit_enabled != 0) {
	print "		<p>
					<b>{$lang['info_21']}</b> - {$lang['info_22']}
				</p>";
}

print " 		<p>
					<b>{$lang['info_25']}</b> - {$lang['info_26']}
				</p>
				<p>
					<b>{$lang['info_27']}</b> - {$lang['info_28']}, ";

// Display ability to upload files, if enabled
if ($edoc_field_option_enabled) {
	print "			{$lang['info_29']}, ";
}

print " 			{$lang['info_30']}
				</p>";

// Display info about API, if enabled
if ($api_enabled) {
	print "		<p>
					<b>REDCap API</b> - {$lang['info_31']}
				</p>";
}

// Data Resolution module
print "			<p>
					<b>{$lang['info_39']}</b> - {$lang['info_40']}
				</p>";

// Piping
print "		<p>
				<b>{$lang['info_41']}</b> - {$lang['info_42']}
			</p>";

print "
		</div>";

print "
			</div>
		</div>";
