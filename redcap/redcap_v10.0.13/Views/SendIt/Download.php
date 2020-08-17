<?php
// Prevent view from being called directly
require_once dirname(dirname(dirname(__FILE__))) . '/Config/init_functions.php';
System::init();


// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();

?>
<style type="text/css">
#pagecontent { margin: 0; }
</style>
<div>
	<a href="<?php echo APP_PATH_WEBROOT_FULL ?>"><img src="<?php echo APP_PATH_IMAGES . 'redcap-logo-large.png' ?>" title="REDCap" alt="REDCap"></a>
</div>

<div style="padding-top:10px;font-size: 18px;border-bottom:1px solid #aaa;padding-bottom:2px;">
	<img src='<?php echo APP_PATH_IMAGES ?>mail_arrow.png'> Send-It: <span style="color:#777;"><?php echo $lang['sendit_41'] ?></span>
</div>
<?php

if ($error == '' || $error == 'Password incorrect') {
	?>
	<p style="padding:15px 0 20px;">
		<b><?php echo $lang['global_24'] . $lang['colon'] ?></b><br>
		<?php echo $lang['sendit_43'] ?> <b><?php echo $doc_size ?> MB</b>.
	</p>

	<div id="formdiv">
		<form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" id="Form1" name="Form1">
		<?php echo $lang['sendit_44'] ?> &nbsp;
		<input type="password" name="pwd" autocomplete="new-password" value="" /> &nbsp;
		<input type="submit" name="submit" value="Download File" onclick="
			document.getElementById('formdiv').style.visibility='hidden';
			if (document.getElementById('errormsg') != null) document.getElementById('errormsg').style.visibility='hidden';
			setTimeout(function(){
				$('#progress').toggle('blind','fast');
			},1000);
			return true;
		"/>
		</form>
	</div>

	<div id="progress" class="darkgreen" style="display:none;font-weight:bold;">
		<img src="<?php echo APP_PATH_IMAGES ?>tick.png"> <?php echo $lang['sendit_58'] ?>
	</div>
	<?php
}

// Display error message, if error occurs
if ($error != '') {
	?>
	<p id="errormsg" style='padding-top:5px;font-weight:bold;color:#800000;'>
		<img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
		<?php echo $error ?>.
	</p>
	<?php
}

print "<br><br><br>";

$objHtmlPage->PrintFooter();