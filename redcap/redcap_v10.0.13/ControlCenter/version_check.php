<?php


require dirname(dirname(__FILE__)) . "/Config/init_global.php";

//If user is not a super user, go back to Home page
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);
//Get current version with no decimals (for comparison)
$redcap_version_numeric = Upgrade::getDecVersion(REDCAP_VERSION);
// Gather all REDCap directories on the server
$webroot = dirname(APP_PATH_DOCROOT).DS;
$versions = array();
foreach (getDirFiles($webroot) as $this_version_dir) {
	if (substr($this_version_dir,0,8) == "redcap_v" && is_dir($webroot . $this_version_dir)) {		
		$this_version = substr($this_version_dir, 8);
		if (Upgrade::getDecVersion($this_version) > $redcap_version_numeric) {
			$versions[Upgrade::getDecVersion($this_version)] = $this_version;
		}
	}
}
krsort($versions);
$versions = array_values($versions);
// No, no upgrading needed
if (empty($versions)) exit('0');
// Yes, we need to upgrade
print  "<i class='fa fa-star' aria-hidden='true'></i>
		<b>{$lang['control_center_61']} REDCap {$versions[0]}{$lang['exclamationpoint']}</b><br>
		<div style='margin:10px 0 5px;'>
			<button class='btn btn-xs btn-rcgreen' onclick=\"window.location.href='".APP_PATH_WEBROOT_PARENT."redcap_v{$versions[0]}/upgrade.php';\">{$lang['control_center_62']}</button>
			{$lang['control_center_63']} {$versions[0]}
		</div>";
// If there is more than one version that we can upgrade to, then list them all here and note if we can do a fast upgrade
if (count($versions) > 1) {
	print  "<div style='margin:15px 0 5px;border-top:1px dashed #048804;padding-top:10px;'>
				<b>{$lang['global_03']}{$lang['colon']}</b> {$lang['control_center_4684']}
			</div>";
	ksort($versions);
	foreach ($versions as $this_version) {
		$isFastUpgrade = Upgrade::isFastUpgrade(REDCAP_VERSION, $this_version);
		$isFastUpgradeText = $isFastUpgrade ? $lang['control_center_4685'] : $lang['control_center_4686'];
		print  "<div style='margin:3px;'>
					<i class='fas fa-sign-in-alt'></i>
					<a href='".APP_PATH_WEBROOT_PARENT."redcap_v{$this_version}/upgrade.php' style='text-decoration:underline;margin:0 3px;'>{$lang['control_center_4687']} REDCap $this_version</a> 
					<span style='font-size:12px;color:#800000;'>$isFastUpgradeText</span>
				</div>";
	}
}