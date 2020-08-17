$(function(){
	// Check record name for invalid characters
	$('#addPatientDialog #newRecordName').blur(function(){
		var input = $(this);
		input.val( input.val().trim() );
		var validRecordName = recordNameValid(input.val());
		if (validRecordName !== true) {
			alert(validRecordName);
			input.focus();
			return false;
		}
	});
});

// Add patient to a project
var newRecord;
function addPatientToProject(pid, mrn, record_auto_numbering) {
	// If auto-numbering is not enabled for new records, then add input for user to provide record name
	if (record_auto_numbering == '1') {
		$('#addPatientDialog #newRecordNameDiv, #addPatientDialog #newRecordNameAutoNumText').hide();
	} else {
		$('#addPatientDialog #newRecordNameDiv, #addPatientDialog #newRecordNameAutoNumText').show();
	}
	$('#addPatientDialog #newRecordName').val('');
	var projectTitle = $('.ehr-project-title-'+pid).text();
	$('#addPatientDialog #newRecordNameProjTitle').html(projectTitle);
	
	$('#addPatientDialog').dialog({ bgiframe: true, modal: true, width: 500, buttons: { 
		'Cancel': function() { 
			$(this).dialog('close');
		},
		'Create record': function() { 			
			if (record_auto_numbering == '0' && $('#addPatientDialog #newRecordName').val() == '') {
				setTimeout(function(){
					addPatientToProject(pid, mrn, record_auto_numbering);
					simpleDialog('Please enter a record name for the new record.');
				},100);
				return false;
			}
			showProgress(1);
			$.post(app_path_webroot+'ehr.php?pid='+pid,{ action: 'create_record', fhirPatient: getParameterByName('fhirPatient'), record: $('#addPatientDialog #newRecordName').val(), mrn: mrn },function(data){
				showProgress(0,0);
				if (data.indexOf('ERROR') > -1) {
					setTimeout(function(){
						addPatientToProject(pid, mrn, record_auto_numbering);
						simpleDialog(data);
					},100);
					$('#addPatientDialog').dialog('close');
					return;
				}
				initDialog('ehr-add-record-success');
				$('#ehr-add-record-success').html(data);			
				newRecord = $('#ehr-add-record-success #newRecordCreated').val();
				$('#ehr-add-record-success').dialog({ bgiframe: true, modal: true, width: 500, title: 'SUCCESS!', buttons: { 
					'Close': function() { 
						window.location.reload();
					},
					'View patient in project': function() { 
						window.location.href = app_path_webroot+'DataEntry/record_home.php?pid='+pid+'&id='+newRecord;
					} 
				} });
				$('#addPatientDialog').dialog('close');
			});
		} 
	} });
}