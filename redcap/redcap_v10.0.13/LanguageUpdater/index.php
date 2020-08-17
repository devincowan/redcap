<?php



// File with necessary functions
require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Create an array containing all English text
$English = Language::callLanguageFile('English');
// Create array of all available languages
$allLanguages = Language::getLanguageList();
// Loop through each language and grab counts of how many variables need to be translated for this version
foreach ($allLanguages as $this_lang)
{
	if ($this_lang == "English") continue;
	$allLanguages[$this_lang] = count(array_diff_key($English, Language::callLanguageFile($this_lang)));
}


$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();


?>
<style type="text/css">
#container {
	background: url("<?php echo APP_PATH_IMAGES ?>redcap-logo.png") right top no-repeat;
}
</style>

<div style='margin:50px 0 30px;'>
	<div style='font-weight:bold;font-size:20px;'>
		REDCap <?php echo $lang['lang_updater_02'] ?>
	</div>
	<p>
		<?php echo $lang['lang_updater_03'] ?>
		<a href="https://community.projectredcap.org/articles/676/redcap-language-center.html" target="_blank" style="text-decoration:underline;"><?php echo $lang['lang_updater_01'] ?></a>.
	</p>
</div>



<?php if (!isset($_GET['update'])) { ?>

	<!-- Give user choice to grab English language file to begin new translation -->
	<h4 style="border-bottom:1px solid #aaa;">
		1.) <?php echo $lang['lang_updater_04'] ?>
	</h4>
	<p>
		<?php echo $lang['lang_updater_05'] ?>
	</p>
	<div style="max-width:700px;padding:0 0 20px;">
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>1.)</b> <?php echo $lang['lang_updater_06'] ?>
		</div>
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>2.)</b> <?php echo $lang['lang_updater_07'] ?>
		</div>
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>3.)</b> <?php echo $lang['lang_updater_08'] ?> (e.g., Chinese.ini)
		</div>
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>4.)</b> <?php echo $lang['lang_updater_09'] ?> "<?php echo dirname(APP_PATH_DOCROOT) . DS . 'languages' ?>"
		</div>
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>5.)</b> <?php echo $lang['lang_updater_10'] ?>
			<a href="https://community.projectredcap.org/articles/676/redcap-language-center.html" target="_blank" style="text-decoration:underline;"><?php echo $lang['lang_updater_01'] ?></a>
		</div>
	</div>
	<input type="button" style="font-size:11px;" value="Get English language file" onclick="$('#english-text').toggle('blind',{},500);">
	<span style="color:#666;">(<?php echo $lang['lang_updater_11'] ?> <?php echo count($English) ?> <?php echo $lang['lang_updater_12'] ?>)</span><br><br>
	<textarea id="english-text" style="display:none;font-size:11px;width:700px;height:200px;" readonly="readonly" onclick="this.select();"
	>; <?php echo remBr($lang['lang_updater_13']) ?> (e.g., Chinese.ini)<?php echo remBr($lang['lang_updater_14']) ?> "<?php echo dirname(APP_PATH_DOCROOT) . DS . 'languages' ?>"
	<?php
	// Loop through each available language file (excluding English, which should never be updated)
	foreach ($English as $var=>$val)
	{
		print renderIniLine($var, $val);
	}
	?>
	</textarea>
	</p>




	<h4 style="color:#888;margin:20px 0 30px;">&#8212; <?php echo $lang['global_46'] ?> &#8212;</h4>




	<!-- Give user choices of grabbing English text or select a non-English file to update -->
	<h4 style="border-bottom:1px solid #aaa;">
		2.) <?php echo $lang['lang_updater_15'] ?>
	</h4>
	<p style="margin-bottom:30px;">
		<?php echo $lang['lang_updater_16'] ?> <b><?php echo $lang['lang_updater_17'] ?></b> <?php echo $lang['lang_updater_18'] ?><br><br>
		<?php
		// Loop through all language files
		foreach ($allLanguages as $this_lang=>$new_vars)
		{
			if ($this_lang == "English") continue;
			?>
			<img src="<?php echo APP_PATH_IMAGES . (($new_vars == 0) ? "tick.png" : "exclamation.png") ?>">
			<span style="color:<?php echo ($new_vars == 0) ? "green" : "#800000" ?>;">
				<?php echo $this_lang ?>.ini <?php echo $lang['lang_updater_19'] ?> <?php echo $new_vars ?> <?php echo $lang['lang_updater_20'] ?>
			</span>
			<input type="button" style="font-size:11px;" value="Get new <?php echo $this_lang ?> language file"
				<?php echo ($new_vars == 0) ? " disabled " : "" ?>
				onclick="window.location.href = '<?php echo PAGE_FULL ?>?update=<?php echo $this_lang ?>'"><br>
			<?php
		}
		// If no language files exist
		if (count($allLanguages) == 1)
		{
			?>
			<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
			<span style="color:#800000;"><?php echo $lang['lang_updater_21'] ?></span>
			<?php
		}
		?>
	</p>
	<?php

}









// If user has selected a language file to update
else
{
	// Set language being updated
	$language = $_GET['update'];

	// Create an array containing all non-English text from selected language
	$notEnglish = Language::callLanguageFile($language);
	ksort($notEnglish);

	// Create an array containing both English and non-English merged together
	$merged_strings = array_merge($English, $notEnglish);

	// Create an array containing all untranslated text from the non-Engilsh file
	$untranslated_strings = array_diff_key($English, $notEnglish);
	ksort($untranslated_strings);

	// Create an array containing all untranslated text from the non-Engilsh file
	$unused_strings = array_diff_key($notEnglish, $English);

	?>
	<img src="<?php echo APP_PATH_IMAGES ?>arrow_skip_180.png">
	<a href="<?php echo PAGE_FULL ?>" style="color:#2E87D2;font-weight:bold;font-size:13px;"><?php echo $lang['lang_updater_22'] ?></a>

	<h4 id="updateinst" style="color:#800000;border-top:1px solid #aaa;padding-top:5px;margin-top:30px;">
		<?php echo $lang['lang_updater_23'] ?> "<?php echo RCView::escape($language) ?>.ini"
	</h4>
	<p>
		<?php echo $lang['lang_updater_24'] ?> <?php echo count($untranslated_strings) ?> <?php echo $lang['lang_updater_25'] ?>
		<?php echo $lang['lang_updater_24'] ?> <?php echo count($unused_strings) ?> <?php echo $lang['lang_updater_26'] ?>
		<?php echo RCView::escape($language) ?>.ini <?php echo $lang['lang_updater_27'] ?>
	</p>
	<div style="max-width:700px;padding:0 0 20px;">
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>1.)</b> <?php echo $lang['lang_updater_28'] ?>
		</div>
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>2.)</b> <?php echo $lang['lang_updater_29'] ?> <?php echo RCView::escape($language) ?>
		</div>
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>3.)</b> <?php echo $lang['lang_updater_30'] ?> "<?php echo RCView::escape($language) ?>.ini" <?php echo $lang['lang_updater_31'] ?>
		</div>
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>4.)</b> <?php echo $lang['lang_updater_32'] ?>
			"<?php echo dirname(APP_PATH_DOCROOT) . DS . 'languages' ?>"
		</div>
		<div style="padding:3px; margin-left:2em; text-indent:-1.5em;">
			<b>5.)</b> <?php echo $lang['lang_updater_10'] ?>
			<a href="https://community.projectredcap.org/articles/676/redcap-language-center.html" target="_blank" style="text-decoration:underline;"><?php echo $lang['lang_updater_01'] ?></a>
		</div>
	</div>
	<textarea style="font-size:11px;width:700px;height:200px;" readonly="readonly" onclick="this.select();">; <?php echo RCView::escape($language) ?>.ini - <?php echo remBr($lang['lang_updater_13'] . $lang['lang_updater_33']) ?> "<?php echo dirname(APP_PATH_DOCROOT) . DS . 'languages' ?>"
	<?php
	// Loop through the untranslated strings and display as first block
	foreach ($untranslated_strings as $var=>$val)
	{
		print renderIniLine($var, $val);
	}
	// Loop through the remaining non-English text from existing file
	print "\n\n; " . remBr($lang['lang_updater_34']) . " ".RCView::escape($language).".ini\n";
	foreach ($notEnglish as $var=>$val)
	{
		if (isset($untranslated_strings[$var]) || isset($unused_strings[$var])) continue; // Ignore if untranslated or no longer exist
		print renderIniLine($var, $val);
	}
	?>
	</textarea>
	<?php

}



// Footer
$objHtmlPage->PrintFooter();

// Take a variable and string and render it as a line for an INI file
function renderIniLine($variable, $string)
{
	return "\n" . $variable . ' = "' . str_replace(array('"',"\r\n","\r","\n","\t","  ","  "), array('\"',' ',' ',' ',' ',' ',' '), $string) . '"';
}
