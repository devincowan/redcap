<?php



/**
 * BIOPORTAL Class
 * Contains methods used with regard to the REST API at http://bioportal.bioontology.org/
 */
class BioPortal
{

	// URL of the BioPortal website to sign up for a new account and API token
	static $SIGNUP_URL = 'https://bioportal.bioontology.org/accounts/new/';
	

	/**
	 * Obtain BioPortal base URL
	 */
	public static function getApiUrl()
	{
		global $bioportal_api_url;
		return $bioportal_api_url;
	}
	

	/**
	 * Obtain complete list of ontologies offered by the API.
	 * Returns array with Acronym as key and Name as value.
	 */
	public static function getOntologyList($prependServiceNameToValue=false)
	{
		global $bioportal_api_token, $bioportal_ontology_list, $bioportal_ontology_list_cache_time;

		// If have no token yet, then return empty array
		if ($bioportal_api_token == '') return array();

		// If we have the list already cached in the config table, then get it
		if ($bioportal_ontology_list != '' && $bioportal_ontology_list_cache_time == TODAY)
		{
			// Use cached list and parse the JSON into an array
			$list = json_decode($bioportal_ontology_list, true);
		}
		else
		{
			// Get list via HTTP request
			// Build URL to call
			$url = self::getApiUrl() . "ontologies?include=name,acronym&display_links=false&display_context=false&format=json&apikey=" . $bioportal_api_token;
			// Call the URL
			$json = http_get($url);
			// Parse the JSON into an array
			$list = json_decode($json, true);
			if (isset($list['error']) || !is_array($list)) return array();
			// Save the JSON in the config table
			$bioportal_ontology_list = $json;
			$bioportal_ontology_list_cache_time = TODAY;
			$sql = "update redcap_config set value = '".db_escape($bioportal_ontology_list)."' where field_name = 'bioportal_ontology_list'";
			db_query($sql);
			$sql = "update redcap_config set value = '".db_escape($bioportal_ontology_list_cache_time)."' where field_name = 'bioportal_ontology_list_cache_time'";
			db_query($sql);
		}

		// Build formatted array of choices from list array
		$ontologies = array();
		foreach ($list as $this_item) {
			$value = ($prependServiceNameToValue ? "BIOPORTAL:" : "") . $this_item['acronym'];
			$ontologies[$value] = "{$this_item['acronym']} - {$this_item['name']}";
		}

		// Sort list
		natcasesort($ontologies);

		// Return list
		return $ontologies;
	}


	/**
	 * Return ontology list as drop-down options
	 */
	public static function displayOntologyListDropDown()
	{
		global $lang;
		$options_html = "";
		// Obtain ontology list as array
		$results = self::getOntologyList(false);
		if ($bioportal_api_token != '' && (!is_array($results) || empty($results))) {
			$results = array(''=>$lang['design_585']);
		}
		// Return list as OPTIONs HTML for a SELECT drop-down list
		if (count($results) > 1 || $bioportal_api_token == '') {
			$options_html .= "<option value=\"\"> -- {$lang['design_584']} -- </option>";
		}
		foreach ($results as $value=>$label) {
			$options_html .= "<option value=\"".htmlspecialchars($value, ENT_QUOTES)."\">$label</option>";
		}
		return $options_html;
	}


	/**
	 * Search API with a search term for a given ontology
	 * Returns array of results with Notation as key and PrefLabel as value.
	 */
	public static function searchOntology($ontology_acronym, $search_term='', $result_limit=20)
	{
		global $bioportal_api_token;
		if ($bioportal_api_token == '') return array();
		// Build URL to call
		$url = self::getApiUrl() . "search?q=".urlencode($search_term)."&ontologies=".urlencode($ontology_acronym)
			 . "&suggest=true&include=prefLabel,notation,cui&display_links=false&display_context=false&format=json&apikey=" . $bioportal_api_token;
		// Call the URL
		$json = http_get($url);
		// Parse the JSON into an array
		$list = json_decode($json, true);
		if (!is_array($list) || !isset($list['collection'])) return array();
		// Set 20 as default limit
		$result_limit = (is_numeric($result_limit) ? $result_limit : 20);
		// Loop through results
		$results = array();
		foreach ($list['collection'] as $this_item) {
			// The following line added via Ticket #44910
			if ($ontology_acronym == "STY" || ($ontology_acronym != "STY" && strpos($this_item['@id'], "/STY/") === false)) {
				// Determine the value (notation will be the value by default)
				if (isset($this_item['notation']) && $this_item['notation'] != '') {
					$this_value = $this_item['notation'];
				}
				// Use CUI as secondary
				elseif (isset($this_item['cui']) && $this_item['cui'] != '') {
					$this_value = $this_item['cui'];
				}
				// If all else fails, use prefLabel
				else {
					$this_value = $this_item['prefLabel'];
				}
				// Add to array
				$results[$this_value] = $this_item['prefLabel'];
			}
		}
		// Return array of results
		return array_slice($results, 0, $result_limit, true);
	}

}
