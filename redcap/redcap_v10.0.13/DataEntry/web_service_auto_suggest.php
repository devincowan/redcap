<?php


// Check if coming from survey or authenticated form
if (isset($_GET['s']) && !empty($_GET['s']))
{
	// Call config_functions before config file in this case since we need some setup before calling config
	require_once dirname(dirname(__FILE__)) . '/Config/init_functions.php';
	// Validate and clean the survey hash, while also returning if a legacy hash
	$hash = $_GET['s'] = Survey::checkSurveyHash();
	// Set all survey attributes as global variables
	Survey::setSurveyVals($hash);
	// Now set $_GET['pid'] before calling config
	$_GET['pid'] = $project_id;
	// Set flag for no authentication for survey pages
	define("NOAUTH", true);
}

require dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Must be accessed via AJAX
if (!$isAjax) exit("ERROR!");

//Retrieve matching records to populate auto-complete box
if (isset($_GET['term']) && isset($_GET['field']) && isset($Proj->metadata[$_GET['field']])) {
	// Decode the string
	$queryString = rawurldecode(urldecode($_GET['term']));
	$queryStringLength = strlen($queryString);
	// Get the name of the name of the web service API and the category (ontology) name
	list ($autosuggest_service, $autosuggest_cat) = explode(":", $Proj->metadata[$_GET['field']]['element_enum'], 2);
	$results = OntologyManager::searchOntology($autosuggest_service, $autosuggest_cat, $queryString);

	// Add results to new array
	$json = array();
	foreach ($results as $this_notation=>$this_preflabel) {
		// Prepend notation to preflabel for display purposes
		$this_label = "[$this_notation] $this_preflabel";
		$this_label = filter_tags(label_decode($this_label));
		// Add boldness to search term
		$pos = stripos($this_label, $queryString);
		if ($pos !== false) {
			$this_label = substr($this_label, 0, $pos)
							. "<b style=\"color:#319AFF;\">".substr($this_label, $pos, $queryStringLength)."</b>"
							. substr($this_label, $pos+$queryStringLength);
		}
		// Add to json array
		$json[] = array('value'=>$this_notation, 'label'=>$this_label, 'preflabel'=>$this_preflabel,
						'service'=>$autosuggest_service, 'cat'=>$autosuggest_cat);
	}
	// If no results, then return single line saying so
	if (empty($json)) {
		$this_label = RCView::span(array('style'=>'color:#888;'),
						"[" . RCView::span(array('style'=>'margin:0 2px;'), $lang['report_builder_87']) . "]"
					  );
		$json[] = array('value'=>'', 'label'=>$this_label, 'preflabel'=>'', 'service'=>'', 'cat'=>'');
	}
	//Render JSON
	print json_encode_rc($json);
} else {
	// User should not be here! Redirect to index page.
	redirect(APP_PATH_WEBROOT . "index.php?pid=$project_id");
}
