<?php

if (!defined(__DIR__)){
  define(__DIR__, dirname(__FILE__));
  }

require_once __DIR__ . "/OntologyManager.php";

class BioPortalOntologyProvider implements OntologyProvider{

	public function getProviderName(){
		global $lang;
		return "BioPortal ".$lang['design_772'];
	}

  /**
    return the prefex used to denote ontologies provided by this provider.
   */
  public function getServicePrefix(){
    return "BIOPORTAL";
  }

	/**
	 * Search API with a search term for a given ontology
	 * Returns array of results with Notation as key and PrefLabel as value.
	 */
	public function searchOntology($category, $search_term, $result_limit){
    $result = BioPortal::searchOntology($category, $search_term, $result_limit);
    //error_log(print_r($result, TRUE));
    return $result;
  }


  public function getOnlineDesignerSection(){
    global $bioportal_api_token;
    global $lang;

    $getToken = ($bioportal_api_token == "") ? "onclick='alertGetBioPortalToken();'" : "";
    $ontologyList = BioPortal::displayOntologyListDropDown();
    $onlineDesignerHtml = <<<EOD
<script type="text/javascript">
  // Display dialog of explanation of BioPortal functionality
  function displayBioPortalExplainDlg() {
    $.post(app_path_webroot+"Design/get_bioportal_explain_popup.php?pid="+pid, { },function(data){
      var json_data = jQuery.parseJSON(data);
      if (json_data.length < 1) {
        alert(woops);
        return false;
      }
      simpleDialog(json_data.content,json_data.title,'get_bioportal_explain_popup',650);
      fitDialog($('#get_bioportal_explain_popup'));
    });
  }

  // Display dialog for user to obtain a BioPortal token in order to use the functionality
  function alertGetBioPortalToken() {
    $.post(app_path_webroot+"Design/get_bioportal_token_popup.php?pid="+pid, { },function(data){
      var json_data = jQuery.parseJSON(data);
      if (json_data.length < 1) {
        alert(woops);
        return false;
      }
      simpleDialog(json_data.content,json_data.title,'get_bioportal_token_popup',600);
      fitDialog($('#get_bioportal_token_popup'));
      $('#bioportal_api_token_btn').button();
    });
  }

  // Display dialog for user to obtain a BioPortal token in order to use the functionality
  function saveBioPortalToken() {
    var bioportal_api_token = trim($('#bioportal_api_token').val());
    if (bioportal_api_token == '') {
      $('#bioportal_api_token').focus();
      return;
    }
    showProgress(1);
    $.post(app_path_webroot+"Design/get_bioportal_token_popup.php?pid="+pid, { bioportal_api_token: bioportal_api_token },function(data){
      var json_data = jQuery.parseJSON(data);
      if (json_data.length < 1) {
        alert(woops);
        return false;
      }
      showProgress(0,0);
    simpleDialog(json_data.content,json_data.title,'get_bioportal_token_popup',600,"if("+json_data.success+"=='1') window.location.reload();");
      $('#bioportal_api_token_btn').button();
    });
  }

  function BIOPORTAL_ontology_changed(service, category){
    var newSelection = ('BIOPORTAL' == service) ? category : '';
    $('#bioportal_ontology_category').val(newSelection);
  }
  
</script>
<div style='margin:5px 0 1px;'>
  <b>{$lang['design_583']}</b><a href='javascript:;' onclick='displayBioPortalExplainDlg();' class='help'>?</a>
</div>
<select id='bioportal_ontology_category' name='bioportal_ontology_category' 
            onchange="update_ontology_selection('BIOPORTAL', this.options[this.selectedIndex].value)"
            {$getToken} class='x-form-text x-form-field' style='width:330px;max-width:330px;'>
        {$ontologyList}
</select>
EOD;
    return $onlineDesignerHtml;
  }

  public function getLabelForValue($category, $value){
    return $value;
  }
}


