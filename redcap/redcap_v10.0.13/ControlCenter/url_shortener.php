<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
//If user is not a super user, go back to Home page
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);

// AJAX request
if ($isAjax) {
    if (isset($_POST['long_url']) && isset($_POST['custom_ending'])) {
		$shorturl_status = getREDCapShortUrl($_POST['long_url'], $_POST['custom_ending']);
		if (isset($shorturl_status['error'])) exit($lang['global_01'].$lang['colon']." ".$shorturl_status['error']);
		if (!isset($shorturl_status['url_short'])) exit("ERROR: An unknown error occurred!");
        // Output HTML for dialog
		print RCView::div(array('class'=>'fs14'),
                $lang['control_center_4719']." ".RCView::b($shorturl_status['url_long'])." ".$lang['control_center_4720'].RCView::br().RCView::br().
                '<input id="url_short_created" value="'.$shorturl_status['url_short'].'" onclick="this.select();" readonly="readonly" class="staticInput fs15 p-2" style="float:left;width:80%;max-width:400px;margin-bottom:5px;margin-right:5px;">
				<button class="btn btn-defaultrc btn-xs btn-clipboard px-3 py-2" onclick="copyUrlToClipboard(this);" title="'.js_escape2($lang['global_137']).'" data-clipboard-target="#url_short_created" style=""><i class="fas fa-paste"></i></button>'
              );
		exit;
    } else {
        exit("ERROR: An unknown error occurred!");
    }
}

// Display page
include 'header.php';
include APP_PATH_VIEWS . 'HomeTabs.php';
renderPageTitle($lang['control_center_4709']);
loadJS('Libraries/clipboard.js');
?>
<style type="text/css">
    #pagecontent { margin-top: 70px; }
    #control_center_window { max-width:800px; }
</style>
<script type="text/javascript">
// Copy-to-clipboard action
var clipboard = new Clipboard('.btn-clipboard');
// Copy the public survey URL to the user's clipboard
function copyUrlToClipboard(ob) {
    // Create progress element that says "Copied!" when clicked
    var rndm = Math.random()+"";
    var copyid = 'clip'+rndm.replace('.','');
    var clipSaveHtml = '<span class="clipboardSaveProgress" id="'+copyid+'">Copied!</span>';
    $(ob).after(clipSaveHtml);
    $('#'+copyid).toggle('fade','fast');
    setTimeout(function(){
        $('#'+copyid).toggle('fade','fast',function(){
            $('#'+copyid).remove();
        });
    },2000);
}
function createShortUrl(do_custom) {
    var long_url = $('#long_url').val().trim();
    var custom_ending = '';
    if (long_url == '') {
        simpleDialog('<?=js_escape($lang['control_center_4716'])?>','<?=js_escape($lang['global_01'])?>',null,null,function(){
            $('#long_url').effect('highlight',2000).focus();
        });
        return;
    }
    if (!isUrl(long_url)) {
        simpleDialog('<?=js_escape($lang['control_center_4718'])?>','<?=js_escape($lang['global_01'])?>',null,null,function(){
            $('#long_url').effect('highlight',2000).focus();
        });
        return;
    }
    if (do_custom) {
        custom_ending = $('#custom_ending').val().trim();
        if (custom_ending == '') {
            simpleDialog('<?=js_escape($lang['control_center_4717'])?>','<?=js_escape($lang['global_01'])?>',null,null,function(){
                $('#custom_ending').effect('highlight',2000).focus();
            });
            return;
        }
    }
    showProgress(1);
    $.post(app_path_webroot+page, { long_url: long_url, custom_ending: custom_ending }, function(data) {
        showProgress(0,0);
        var error = (data == '0' || data.indexOf('ERROR') > -1);
        var title = error ? '<?=js_escape($lang['global_01'])?>' : '<?=js_escape($lang['global_79'])?>';
        simpleDialog(data, title, null, 700);
        if (!error) {
            $('#long_url').val('');
            $('#custom_ending').val('');
        }
    });
}
</script>
<?php

print RCView::div(array(),
	RCView::div(array(),
		$lang['control_center_4710']
	) .
	RCView::div(array('class'=>'form-group my-4'),
		RCView::label(array('style'=>'font-weight:bold;margin-right:6px;font-size:14px;'), "URL:") .
        RCView::input(array('type'=>'text', 'id'=>'long_url', 'class'=>'form-control', 'style'=>'color:#000;font-size:14px;display:inline;max-width:700px;width:90%;margin:0 5px',
            'placeholder'=>$lang['control_center_4712']))
    ) .
	RCView::div(array('class'=>'form-group my-5'),
		RCView::label(array('style'=>'font-weight:bold;margin-right:6px;font-size:14px;'), $lang['control_center_4714']) .
        RCView::br() .
		RCView::button(
			array('class'=>'btn btn-primaryrc btn-sm fs14', 'onclick'=>'createShortUrl(false);'),
			'<span class="fa f-search"></span>&nbsp;'.$lang['control_center_4711'].'&nbsp;'
		)
	) .
	RCView::div(array('class'=>'form-group my-5'),
		RCView::label(array('style'=>'font-weight:bold;margin-right:6px;font-size:14px;'), $lang['control_center_4715']) .
		RCView::br() .
		RCView::label(array('style'=>'font-weight:bold;font-size:14px;color:#A00000;'), 'https://redcap.link/') .
		RCView::input(array('type'=>'text', 'id'=>'custom_ending', 'class'=>'form-control', 'style'=>'color:#000;font-size:14px;display:inline;max-width:180px;width:40%;margin:0 5px')) .
		RCView::br() .
		RCView::button(
			array('class'=>'btn btn-primaryrc btn-sm fs14 mt-3', 'onclick'=>'createShortUrl(true);'),
			'<span class="fa f-search"></span>&nbsp;'.$lang['control_center_4713'].'&nbsp;'
		)
	)
);
include 'footer.php';