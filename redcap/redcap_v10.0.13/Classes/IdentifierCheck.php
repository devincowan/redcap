<?php

/**
 * IdentifierCheck
 */
class IdentifierCheck
{
	// Return *new* fields in Draft Mode (production status) that have variables or labels that match
	// keywords for Check For Identifiers
	public static function getNewFieldsMatchingKeywords($project_id, $status)
	{		
		// Keywords to use in query (get from config table and parse into $identifiers array)
		$identifiers = self::getKeywordArray();
		if ($status < 1 || empty($identifiers)) return array();

		// Set WHERE clause in query
		$whereFieldName    = "a.field_name LIKE '%" . implode("%' OR a.field_name LIKE '%", $identifiers) . "%'";
		$whereElementLabel = "a.element_label LIKE '%" . implode("%' OR a.element_label LIKE '%", $identifiers) . "%'";
		
		// Query to get new fields with keyword match
		$sql = "select a.field_name from redcap_metadata_temp a left join redcap_metadata b on a.field_name = b.field_name 
				and a.project_id = b.project_id where a.project_id = $project_id and b.field_name is null
				and ($whereFieldName OR $whereElementLabel) order by a.field_order";
		$q = db_query($sql);
		$fieldMatches = array();
		while ($row = db_fetch_assoc($q))
		{
			$fieldMatches[] = $row['field_name'];
		}
		
		// Return array
		return $fieldMatches;
	}
	
	// Return array of parsed identifier keywords
	public static function getKeywordArray()
	{
		global $identifier_keywords;
		// Keywords to use in query (get from config table and parse into $identifiers array)
		$identifier_keywords = str_replace(array("\r\n",",",";","\n\n"), array("\n","\n","\n","\n"), $identifier_keywords);
		$identifiers = array();
		foreach (explode("\n", $identifier_keywords) as $this_ident)
		{
			$this_ident = trim($this_ident);
			if ($this_ident != '') $identifiers[] = db_escape($this_ident);
		}
		return $identifiers;
	}

}