<?php
// Prevent view from being called directly
require_once dirname(dirname(dirname(__FILE__))) . '/Config/init_functions.php';
System::init();

// Initialize page display object
$objHtmlPage = new HtmlPage();
$objHtmlPage->addStylesheet("home.css", 'screen,print');
$objHtmlPage->PrintHeader();

// TABS
include APP_PATH_VIEWS . 'HomeTabs.php';


// Only show page header/footer when viewing outside of a REDCap project
if ($fileLocation == 1)
{
	?>
	<!-- top navbar -->
	<nav class="rcproject-navbar navbar navbar-light fixed-top" role="navigation">
		<div class="container">
			<div class="navbar-header">
				<a href="<?php echo APP_PATH_WEBROOT_PARENT ?>"><img style="margin:0 0 0 10px;" src="<?php echo APP_PATH_IMAGES ?>redcap-logo.png"></a>
				<button type="button" class="navbar-toggler collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
					<span class="navbar-toggler-icon"></span>
				</button>
			</div>
			<div id="navbar" class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
					<?php
					// Default tabs
					print $tabs;
					// My Profile
					print "<li><a href='" . APP_PATH_WEBROOT . "Profile/user_profile.php'
							style='color:#393733;padding:7px 4px 6px 9px;'><img src='" . APP_PATH_IMAGES . "user_info3.png'
							style='padding-right:2px;'> {$lang['config_functions_50']}</a></li>";
					// Log out
					if ($auth_meth != 'none') {
						print "<li><a href='" . PAGE_FULL . ($_SERVER['QUERY_STRING'] == "" ? "?" : "?" . $_SERVER['QUERY_STRING'] . "&")."logout=1'
								style='color:#393733;padding:7px 4px 6px 9px;'><img src='" . APP_PATH_IMAGES . "lock.png'
								style='padding-right:2px;'> {$lang['bottom_02']} <span style='margin-left:20px;font-weight:normal;'>({$lang['bottom_01']} <b>$userid</b>)</span></a></li>";
					}
					?>
				</ul>
			</div>
		</div>
	</nav>
	<!-- main window -->
	<div>
	<?php

}


?>

<script type='text/javascript'>
$(function() {
	var form = $("#form1");
	var recipients = $("#recipients");
	var file = $("#file");
	var fileLocation = $("#fileLocation");

	autosize($('#recipients'));
	autosize($('#message'));

	form.submit(function() {
		if ( validateRecipients() && validateFile() ) {
			return true;
		} else {
			showProgress(0,0);
			return false;
		}
	});

	function validateRecipients() {
		if (recipients.val().length == 0) {
			recipients.addClass("error");
			return false;
		}
		else {
			recipients.removeClass("error");
			// Parse email addresses and validate each
			var emails = recipients.val();
			emails = emails.replace(";", ",");
			emails = emails.replace(new RegExp( "\\r\\n", "g" ), ",");
			emails = emails.replace(new RegExp( "\\r", "g" ), ",");
			emails = emails.replace(new RegExp( "\\n", "g" ), ",");
			emails = emails.split(' ').join('');
			emails = emails.replace(new RegExp( ",+", "g" ), ",");
			if (emails.substring(0,1) == ",") emails = emails.substring(1);
			if (emails.substring(emails.length-1) == ",") emails = emails.substring(0,emails.length-1);
			// Loop through all
			emailsArr = emails.split(',');
			badEmails = "";
			for (var i=0; i<emailsArr.length; i++) {
				if (!isEmail(emailsArr[i])) badEmails += "<br>" + escapeHtml(emailsArr[i]);
			}
			if (badEmails == "") {
				return true;
			} else {
				simpleDialog('<?php echo js_escape($lang['sendit_07']."<br><br><b>".$lang['sendit_08']."</b>") ?>'+badEmails,'<?php echo js_escape($lang['global_01']) ?>','',400);
				recipients.val(emails.replace(new RegExp( ",", "g"), ", "));
				recipients.addClass("error");
				return false;
			}
		}
	}

	function validateFile() {
		if (getParameterByName('id') == '') {
			if (file.val().length == 0) {
				file.addClass("error");
				return false;
			} else {
				file.removeClass("error");
				return true;
			}
		} else {
			return true;
		}
	}
});
</script>




<!-- Page title -->
<div style="padding-top:8px;font-size: 18px;border-bottom:1px solid #aaa;padding-bottom:2px;">
	<i class="fas fa-envelope" aria-hidden="true"></i> 
	<?php
	// Add filename here if from File Repository or data entry page
	print $lang['home_26'];
	if ($fileLocation != 1) {
		print $lang['colon']." <span style='color:#800000;font-weight: normal;'>{$lang['sendit_09']} \"<b class='notranslate'>$originalFilename</b>\"</span>";
	}
	?>
</div>





<!-- Display message that file was uploaded successfully -->
<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && count($errors) == 0)
{
	// Get YMD timestamp of the file's expiration time
	$expireTimestamp = date('Y-m-d H:i:s', mktime( $expireHour, $expireMin, 0, $expireMonth, $expireDay, $expireYear));
?>
	<p style="color:green;font-weight:bold;font-size:14px;margin:15px 0;">
		<img src="<?php echo APP_PATH_IMAGES ?>tick.png"> <?php echo $lang['sendit_10'] ?>
	</p>
	<p>
		<?php echo $lang['sendit_11'] ?> "<b class="notranslate"><?php echo $originalFilename ?></b>" (<?php echo round_up($fileSize/1024/1024) ?> MB)
		<?php echo $lang['sendit_12'] ?>
		<?php echo date('l', mktime( $expireHour, $expireMin, 0, $expireMonth, $expireDay, $expireYear))
				 . ", " . DateTimeRC::format_ts_from_ymd($expireTimestamp); ?>.
	</p>
	<p>
		<b><?php echo $lang['sendit_13'] ?></b><br>
		<?php echo implode("<br>", $successfulEmails); ?>
	</p>
	<?php if (!empty($failedEmails)) { ?>
		<p style="color:#800000;">
			<b><?php echo $lang['sendit_14'] ?></b><br>
			<?php echo implode("<br>", $failedEmails); ?>
		</p>
	<?php } ?>
	<?php if ($fileLocation == 1) { ?>
		<p style='border-top:1px solid #AAAAAA;margin-top:40px;padding:5px;'>
			<img src="<?php echo APP_PATH_IMAGES ?>arrow_skip_180.png">&nbsp;
			<a href="<?php echo PAGE_FULL ?>?route=SendItController:upload" style="color:#2E87D2;font-weight:bold;"><?php echo $lang['sendit_15'] ?></a>
		</p>
	<?php } ?>




<!-- Render form for uploading file -->
<?php
 } else {

	// Display any errors
	if (count($errors) > 0) {
		?>
		<p class="red" style="margin:20px 0 10px;">
			<b><?php echo $lang['global_01'] ?>:</b><br> &bull;
			<?php echo implode("<br> &bull; " , $errors) ?>
		</p>
		<?php
	}

	// Do not show paragraph explaining "Send-It" in pop-up window mode
	if ($fileLocation == 1) {
		?>
		<p style="margin:20px 0 10px;">
			<b><?php echo $lang['sendit_17'] ?>
			<?php echo maxUploadSizeSendit() ?> MB <?php echo $lang['sendit_18'] ?></b>
			<?php echo $lang['sendit_19'] ?>
		</p>
		<?php
	}
	?>
	<p style="margin:10px 0 25px;">
		<b><?php echo $lang['sendit_20'] ?></b><br>
		<?php echo $lang['sendit_21'] ?>
	</p>

	<form action="<?php echo PAGE_FULL."?route={$_GET['route']}&loc=$fileLocation".(empty($_GET['id']) ? "" : "&id=".$_GET['id']) ?>" 
		onsubmit="showProgress(1);return true;" enctype="multipart/form-data" target="_self" method="post" id="form1" name="form1">
	<div id="senditbox">
		<fieldset>

			<div style="margin-bottom: 10px; clear: both;">
				<label for="fromEmail" class="labelrc"><?php echo $lang['global_37'] ?> </label>
				<?php echo User::emailDropDownList() ?>
			</div>

			<div style="margin-bottom: 10px; clear: both;">
				<label for="recipients" class="labelrc">
					<?php echo $lang['global_38'] ?><br><span style="font-weight:normal;color:#555;"><?php echo $lang['sendit_23'] ?></span>
				</label>
					<textarea id="recipients" name="recipients" style="width:100%;max-width: 400px; height: 80px; min-height: 80px;"><?php echo (isset($recipients) && $recipients != '') ? htmlspecialchars($recipients, ENT_QUOTES) : '' ?></textarea>
				<div style="font-weight:normal;font-size:11px;padding:0 0 10px 130px;color:#555;">
					<?php echo $lang['sendit_24'] ?>
				</div>
			</div>

			<div style="color:#555; height: 33px; margin-bottom: 10px; clear: both;">
				<label for="subject" class="labelrc" style="font-weight:normal; "><?php echo $lang['sendit_25'] ?> <br/><?php echo $lang['global_06'] ?></label>
				<input type="text" id="subject" name="subject" value="<?php echo (isset($subject) && $subject != '') ? $subject : '' ?>" style="width:100%;max-width: 400px;" />
			</div>

			<div style="color:#555; margin-bottom: 15px; clear: both;">
				<label for="message" class="labelrc" style="font-weight:normal;" ><?php echo $lang['sendit_27'] ?> <br><?php echo $lang['global_06'] ?></label>
					<textarea id="message" name="message" style="width:100%;max-width: 400px; height: 80px;min-height: 80px;"><?php echo (isset($message) && $message != '') ? htmlspecialchars($message, ENT_QUOTES) : '' ?></textarea>
			</div>

			<div style="height: 33px; margin-bottom: 10px; clear: both;"><label for="expireDate" class="labelrc"><?php echo $lang['sendit_28'] ?></label>
			<select id="expireDays" name="expireDays">
				<?php
				$expireDays = $expireDays == '' ? 3 : $expireDays;
				for ($i=1; $i <= 14; $i++) {
					echo '<option value="'.$i.'" '.(($i != $expireDays) ? "" : "selected").'>'.$i.' day'.(($i == 1) ? "" : "s").'</option>';
				}
				?>
			</select>
			<span style="color: #000; margin-left: 10px;"><?php echo $lang['sendit_29'] ?></span></div>

			<div style="margin-bottom: 25px; clear: both;font-weight:bold;">
				<label for="file" class="labelrc" id="lblFile">
			<?php if (empty($_GET['id'])) { ?>
				<?php echo $lang['sendit_30'] ?> </label>
				<input type="file" id="file" name="file" /> &nbsp;&nbsp;(<?php echo $lang['sendit_31'] ?>: <?php echo maxUploadSizeSendit() ?> MB)
			<?php } else { ?>
				<?php echo $lang['sendit_32'] ?> </label>
				<span style="color:#800000;"><?php echo $originalFilename ?></span>
				<span style="color:#800000;font-weight:normal;">(<?php echo round_up($fileSize/1024/1024) ?> MB)</span>
			<?php } ?>
			</div>
			<div style="margin-bottom: 25px; ">
				<label class="labelrc">
			<input type="checkbox" id="confirmation" name="confirmation" value="yes" <?php echo (isset($confirmation) && $confirmation != '') ? 'checked' : '' ?> />
				</label>
				<b><?php echo $lang['sendit_33'] ?></b>
				<div style="color:#555;">
					<?php echo $lang['sendit_34'] ?>
				</div>
			</div>

			<div style="margin-left: 130px;">
				<input type="submit" id="submit" name="submit" value="Send It!" />
			</div>
		</fieldset>
	</div>
	</form>

<?php
}

if ($fileLocation == 1) { print "</div></div>"; }

$objHtmlPage->PrintFooter();