<?php
global $format, $returnFormat, $post;

// Check for required privileges
if ($post['user_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_103'], $returnFormat));

# put all the records to be imported
$content = putItems();

# Logging
Logging::logEvent("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Import users (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function putItems()
{
	global $post, $format, $lang;
	$count = 0;
	$errors = array();
	$data = $post['data'];

	$Proj = new Project();
	$dags = $Proj->getUniqueGroupNames();

	switch($format)
	{
	case 'json':
		// Decode JSON into array
		$data = json_decode($data, true);
		if ($data == '') return $lang['data_import_tool_200'];
		break;
	case 'xml':
		// Decode XML into array
		$data = Records::xmlDecode(html_entity_decode($data, ENT_QUOTES));
		if ($data == '' || !isset($data['users']['item'])) return $lang['data_import_tool_200'];
		$data = (isset($data['users']['item'][0])) ? $data['users']['item'] : array($data['users']['item']);
		break;
	case 'csv':
		// Decode CSV into array
		$data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $data);
		$data = csvToArray($data);
		// Reformat form-level rights for CSV only
		foreach ($data as $key=>$this_user) {
			if (isset($this_user['forms']) && $this_user['forms'] != '') {
				$these_forms = array();
				foreach (explode(",", $this_user['forms']) as $this_pair) {
					list ($this_form, $this_right) = explode(":", $this_pair, 2);
					$these_forms[$this_form] = $this_right;
				}
				$data[$key]['forms'] = $these_forms;
			}
		}
		break;
	}

	// Begin transaction
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

	foreach ($data as $key=>&$this_user) {
		$this_user['username'] = trim($this_user['username']);
		// If username is missing
		if (!isset($this_user['username']) || $this_user['username'] == '') {
			$errors[] = $lang['api_118'] . ($key+1) . " " . $lang['api_119'];
			continue;
		}
		// Validation username format
		if (!preg_match("/^([a-zA-Z0-9'_\s\.\-\@])+$/", $this_user['username'])) {
			$errors[] = "username \"{$this_user['username']}\" " . $lang['api_151'] . " " . $lang['rights_354'];
		}
		// Validate DAG id number (if provided)
		if ($this_user['data_access_group_id'] != '') {
			// Is valid DAG id?
			if (!isset($dags[$this_user['data_access_group_id']])) {
				$errors[] = "data_access_group_id \"{$this_user['data_access_group_id']}\" " . $lang['api_110'];
			} else {
				// Add unique name since we'll drop the id number in the end
				$this_user['data_access_group'] = $dags[$this_user['data_access_group_id']];
			}
		} elseif ($this_user['data_access_group'] != '' && !array_search($this_user['data_access_group'], $dags)) {
			$errors[] = "data_access_group \"{$this_user['data_access_group']}\" " . $lang['api_111'];
		}
		// Check form-level rights
		if (isset($this_user['forms']) && !empty($this_user['forms'])) {
			// Parse the forms
			$these_forms = array();
			foreach ($this_user['forms'] as $this_form=>$this_right) {
				// Is valid form and right level value?
				if (!isset($Proj->forms[$this_form])) {
					$errors[] = $lang['api_113'] . " \"$this_form\" " . $lang['api_114'] . " \"{$this_user['username']}\" " . $lang['api_115'];
				} elseif (!is_numeric($this_right) || !($this_right >= 0 && $this_right <=3)) {
					$errors[] = $lang['api_113'] . " \"$this_form\" " . $lang['api_116'] . " \"$this_right\" " . $lang['api_117'];
				} else {
					$these_forms[] = $this_form;
				}
			}
			// If some forms are not provided, then by default set their rights to 0.
			$missing_forms = array_diff(array_keys($Proj->forms), $these_forms);
			foreach ($missing_forms as $this_form) {
				$this_user['forms'][$this_form] = 0;
			}
			// Reformat form-level rights to back-end format
			$data_entry = "";
			foreach ($this_user['forms'] as $this_form=>$this_val) {
				$data_entry .= "[$this_form,$this_val]";
			}
			$this_user['forms'] = $data_entry;
		}
		// Convert unique DAG name to group_id
		if ($this_user['data_access_group'] != '' && array_search($this_user['data_access_group'], $dags)) {
			$this_user['data_access_group'] = array_search($this_user['data_access_group'], $dags);
		}
		// Remove email, first name, and last name (if included)
		unset($this_user['email'], $this_user['firstname'], $this_user['lastname']);
	}
	unset($this_user);

	if (empty($errors)) {
		foreach($data as $ur)
		{
			$privileges = UserRights::getPrivileges(PROJECT_ID, $ur['username']);
			if(empty($privileges))
			{
				if(UserRights::addPrivileges(PROJECT_ID, $ur))
				{
					$count++;
				}
			}
			else
			{
				// If user is in a role, then return an error
				if (is_numeric($privileges[PROJECT_ID][strtolower($ur['username'])]['role_id'])) {
					$errors[] = "The user \"{$ur['username']}\" " . $lang['api_112'];
					continue;
				}

				// Update
				if(UserRights::updatePrivileges(PROJECT_ID, $ur))
				{
					$count++;
				}
			}
		}
	}

	if (!empty($errors)) {
		// ERROR: Roll back all changes made and return the error message
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
		die(RestUtility::sendResponse(400, implode("\n", $errors)));
	}

	db_query("COMMIT");
	db_query("SET AUTOCOMMIT=1");

	return $count;
}
