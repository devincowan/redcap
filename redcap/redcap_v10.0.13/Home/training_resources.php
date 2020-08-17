<?php


?>

<style type="text/css">
.smTitle { font-weight:normal;font-size:12px; }
.bigTitle { color:#800000;font-weight:bold;width:200px;font-size:14px;text-align:center;padding:8px; }
.descrip { text-align:left;font-family:"Open Sans",tahoma;font-size:13px;padding:6px 9px; }
.trnHdr { font-weight:bold;background-color:#ddd;border:1px solid #888;text-align:center;padding:4px; }
td.exvid { width:80px;text-align:center;padding:5px; }
</style>


<!-- Page title -->
<div style="padding-top:8px;font-size: 18px;border-bottom:1px solid #aaa;padding-bottom:2px;">
	<span class="fas fa-film" aria-hidden="true"></span> 
	<?php echo $lang['training_res_02'] ?>
</div>



<!-- PRELIMIARY VIDEOS -->
<p style='padding-top:20px;'>
	<span style="font-size:14px;font-weight:bold;"><?php echo $lang['training_res_03'] ?></span><br>
	<?php echo $lang['training_res_86'] ?>
</p>
<table border=1 cellpadding=4 cellspacing=0 style='border-collapse:collapse;border:1px solid #888;width:100%;text-align:center;'>
	<tr>
		<td class='trnHdr'>
			<?php echo $lang['training_res_05'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['global_20'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['training_res_07'] ?>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['bottom_58'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_85'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('redcap_overview_brief02.mp4','A Brief Overview of REDCap')" href="javascript:;"
				style="font-size:12px;text-decoration:underline;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>4 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['bottom_57'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_09'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('redcap_overview03.mp4','A General Overview of REDCap')" href="javascript:;"
				style="font-size:12px;text-decoration:underline;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>14 <?php echo $lang['survey_428'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['bottom_56'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_87'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('data_entry_overview_02.mp4','An Overview of Basic Data Entry in REDCap')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>
				19 <?php echo $lang['config_functions_72'] ?>
			</div>
		</td>
	</tr>
</table>

<!-- Building a Project -->
<p style='padding-top:40px;'>
	<span style="font-size:14px;font-weight:bold;"><?php echo $lang['training_res_88'] ?></span><br>
	<?php echo $lang['training_res_89'] ?>
</p>
<table border=1 cellpadding=4 cellspacing=0 style='border-collapse:collapse;border:1px solid #888;width:100%;text-align:center;'>
	<tr>
		<td class='trnHdr'>
			<?php echo $lang['training_res_05'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['global_20'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['training_res_07'] ?>
		</td>
	</tr>

	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_101'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_91'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('intro_instrument_dev.mp4','<?php echo js_escape($lang['training_res_101']) ?>')" href="javascript:;"
				style="font-size:12px;text-decoration:underline;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>6 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>

	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_12'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_92'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('online_designer01.mp4','The Online Designer')" href="javascript:;"
				style="font-size:12px;text-decoration:underline;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'><?php echo $lang['training_res_14'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_15'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_93'] ?>
			<a href="<?php print APP_PATH_WEBROOT ?>Design/data_dictionary_demo_download.php"
				style="text-decoration:underline;"><?php echo $lang['training_res_17'] ?></a>.
		</td>
		<td class='exvid'>
			<a onclick="popupvid('redcap_data_dictionary02.mp4','The Data Dictionary')" href="javascript:;"
				style="font-size:12px;text-decoration:underline;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'><?php echo $lang['training_res_18'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['bottom_36'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_94'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('field_types02.mp4','<?php echo js_escape($lang['bottom_36']) ?>')" href="javascript:;"
				style="font-size:12px;text-decoration:underline;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>4 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>
</table>



<!-- Basic Features & Modules -->
<p id='db_types' style='padding-top:40px;'>
	<span style="font-size:14px;font-weight:bold;"><?php echo $lang['training_res_96'] ?></span>
</p>
<table border=1 cellpadding=4 cellspacing=0 style='border-collapse:collapse;border:1px solid #888;width:100%;text-align:center;'>
	<tr>
		<td class='trnHdr'>
			<?php echo $lang['training_res_24'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['global_20'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['training_res_07'] ?>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_97'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_98'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('redcap_db_applications_menu02.mp4','<?php echo js_escape($lang['training_res_97']) ?>')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>5 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['bottom_33'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_99'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('calendar02.mp4','<?php echo js_escape($lang['bottom_33']) ?>')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>7 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_19'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_100'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('scheduling02.mp4','The REDCap Scheduling Module')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>7 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['global_22'] . "<br>" . $lang['training_res_52'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_81'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('data_access_groups02.mp4','Data Access Groups')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>7 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>
</table>


<!-- Types of REDCap Projects -->
<p id='db_types' style='padding-top:40px;'>
	<span style="font-size:14px;font-weight:bold;"><?php echo $lang['training_res_22'] ?></span><br>
	<?php echo $lang['training_res_23'] ?>
</p>
<table border=1 cellpadding=4 cellspacing=0 style='border-collapse:collapse;border:1px solid #888;width:100%;text-align:center;'>
	<tr>
		<td class='trnHdr'>
			<?php echo $lang['training_res_24'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['global_20'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['training_res_07'] ?>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_71'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_73'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('project_types01.mp4','Types of REDCap Projects')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'><?php echo $lang['training_res_33'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_26'] ?>
			<div class="smTitle"><?php echo $lang['training_res_27'] ?></div>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_74'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('traditional_project02.mp4','The Traditional REDCap Project')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'><?php echo $lang['training_res_33'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_60'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_75'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('redcap_survey_basics02.mp4','Single Survey Project in REDCap')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'><?php echo $lang['training_res_29'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_30'] ?>
			<div class="smTitle"><?php echo $lang['training_res_31'] ?></div>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_76'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('longitudinal_project02.mp4','The Longitudinal REDCap Project')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'><?php echo $lang['training_res_33'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_34'] ?>
			<div class="smTitle"><?php echo $lang['training_res_35'] ?></div>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_77'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('longitudinal_sched_project02.mp4','The Longitudinal REDCap Project with Scheduling')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'><?php echo $lang['training_res_33'] ?></div>
		</td>
	</tr>
	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_46'] ?>
			<div class="smTitle"><?php echo $lang['training_res_47'] ?></div>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_78'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('operational_project02.mp4','Using REDCap for Operational Use and Non-clinical Data Collection')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>2 <?php echo $lang['survey_428'] ?></div>
		</td>
	</tr>
</table>


<p style='padding-top:40px;'>
	<span style="font-size:14px;font-weight:bold;"><?php echo $lang['training_res_49'] ?></span><br>
	<?php echo $lang['training_res_50'] ?>
</p>
<table border=1 cellpadding=4 cellspacing=0 style='border-collapse:collapse;border:1px solid #888;width:100%;text-align:center;'>
	<tr>
		<td class='trnHdr'>
			<?php echo $lang['training_res_51'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['global_20'] ?>
		</td>
		<td class='trnHdr'>
			<?php echo $lang['training_res_07'] ?>
		</td>
	</tr>

	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_69'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_83'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('define_events02.mp4','<?php echo js_escape($lang['training_res_69']) ?>')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>5 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>

	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_79'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_84'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('designate_instruments02.mp4','<?php echo js_escape($lang['training_res_79']) ?>')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>3 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>

	<tr>
		<td class='bigTitle'>
			<?php echo $lang['setup_146'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['data_entry_296'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('repeating_forms_events01.mp4','<?php echo js_escape($lang['setup_146']) ?>')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>33 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>

	<tr>
		<td class='bigTitle'>
			<?php echo $lang['global_118'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['system_config_330'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('app_overview_01.mp4','REDCap Mobile App')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>2 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>

	<tr>
		<td class='bigTitle'>
			<?php echo $lang['training_res_56'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_82'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('locking02.mp4','Record Locking Functionality')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>2 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>

	<tr>
		<td class='bigTitle'>
			<?php echo $lang['dataqueries_137'] ?>
		</td>
		<td class='descrip'>
			<?php echo $lang['training_res_95'] ?>
		</td>
		<td class='exvid'>
			<a onclick="popupvid('data_resolution_workflow01.swf','<?php echo js_escape($lang['dataqueries_137']) ?>')" href="javascript:;"
			   style="font-size:12px;text-decoration:underline;font-weight:normal;"><img src='<?php echo APP_PATH_IMAGES ?>video.png'></a>
			<div style='color:#555;font-size:11px;'>5 <?php echo $lang['config_functions_72'] ?></div>
		</td>
	</tr>

</table>

<br><br>

</div>
<?php
