$(function(){
	$('#econsent_confirm_checkbox').prop('disabled',true);
	$('#form button[name=\"submit-btn-saverecord\"]').button('disable');
	$('#econsent_confirm_checkbox_label, #econsent_confirm_checkbox').click(function(){	
		if ($('#econsent_confirm_checkbox').prop('checked')) {
			$('#form button[name=\"submit-btn-saverecord\"]').button('enable');
			$('#econsent_confirm_checkbox_div').removeClass('yellow').addClass('green');
		} else {
			$('#form button[name=\"submit-btn-saverecord\"]').button('disable');
			$('#econsent_confirm_checkbox_div').removeClass('green').addClass('yellow');
		}
	});
	showProgress(1);
	setTimeout(function(){
		showProgress(0,0);
		$('#econsent_confirm_checkbox').prop('disabled',false);
		$('#econsent_confirm_checkbox_label').removeClass('opacity50');
	},1000);
});

function resetSignatureValuesPrep() {
    var ob = $('#form button[name=\"submit-btn-saveprevpage\"]');
    ob.attr('onclick','return false;').click(function(){
        simpleDialog(null,null,'resetSignatureValuesDialog',600,null,langSig.global_53,'resetSignatureValues();',langSig.survey_1266);
	});
}

function resetSignatureValues() {
    $('#form').attr('action', $('#form').attr('action')+'&__es=1');
    var prevPageBtn = $('#form button[name=\"submit-btn-saveprevpage\"]');
    prevPageBtn.button("disable");
    dataEntrySubmit(prevPageBtn);
}