<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// MAPPING FIELDS IMPORT/EXPORT
$DDP_data_tool = new DDPDataTool($DDP);

// manage import/export calls
if(isset($_POST['export']))
{
	$filename_suffix = 'mapping-fields';
	$filename = isset($project_name) ? "{$project_name}-{$filename_suffix}" : $filename_suffix;
	$DDP_data_tool->exportMappingFields($filename);
}
else if(isset($_POST['import']))
{
	$DDP_data_tool->importMappingFields();
}

// Header
include APP_PATH_DOCROOT  . 'ProjectGeneral/header.php';
renderPageTitle("<i class=\"fas fa-database\"></i> " . ($DDP->isEnabledInProjectFhir() ? $lang['ws_210'] : $lang['ws_51']) . " " . $DDP->getSourceSystemName());

// CSS & Javascript
?>

<script type="text/javascript" src="<?php print APP_PATH_JS; ?>DynamicDataPullDataTool.js"></script>
<link rel="stylesheet" href="<?php print APP_PATH_CSS; ?>DynamicDataPullDataTool.css">

<style type="text/css">
#ext_field_tree { border:1px solid #ccc; padding:15px; max-width:650px; background-color:#f3f3f3; }
</style>
<script type="text/javascript">
(function($,window,document){
	
	$(function(){
		// Hide "saved" message after displaying for a bit
		if ($('.msgrt').length) {
			setTimeout(function(){
				$('.msgrt').hide('slow');
			}, 3000);
		}
		// Trigger to remove "blue" class on row
		$('.mapfld').change(function(){
			// Add/remove blue class
			if ($(this).val() == '') {
				$(this).parents('tr:first').children('td').addClass('blue');
			} else {
				$(this).parents('tr:first').children('td').removeClass('blue');
			}
		});

		DDP_DataTool.init(); // initialize the mapping fields import/export tool

	});
})(jQuery,window,document);
</script>

<?php
// Mapping fields import export form 
?>
<section id="mapping-import-export">
	<form id="import-form" action="" method="POST" enctype="multipart/form-data">
		<input class="inputfile" type="file" name="file" id="file">
		<input type="hidden" name="import" value="1">
	</form>
	<form id="export-form" action="" method="POST">
		<input type="hidden" name="export" value="1">
	</form>
</section>

<!-- Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="messageModalLabel">Modal title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
		  modal message
      </div>
      <div class="modal-footer">
        <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
        <button type="button" class="btn btn-primary" data-dismiss="modal">Ok</button>
      </div>
    </div>
  </div>
</div>

<?php

// Render page
print $DDP->renderSetupPage();

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';