<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirServices;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;

class ControlCenterController extends Controller
{
	// Perform the One-Click Upgrade
	public function oneClickUpgrade()
	{
		if (!SUPER_USER) exit('ERROR'); // Super users only
		Upgrade::performOneClickUpgrade();
	}
	
	// Execute the upgrade SQL script to complete an upgrade
	public function executeUpgradeSQL()
	{
		if (!SUPER_USER) exit('ERROR'); // Super users only
		print Upgrade::executeUpgradeSQL($_POST['version']);
	}
	
	// Execute the upgrade SQL script to complete an upgrade
	public function autoFixTables()
	{
		// Super users only
		print ((SUPER_USER && Upgrade::autoFixTables()) ? '1' : '0');
	}
	
	// Hide the Easy Upgrade box on the main Control Center page
	public function hideEasyUpgrade()
	{
		// Super users only
		print ((SUPER_USER && Upgrade::hideEasyUpgrade($_POST['hide'])) ? '1' : '0');
	}
	
	/**
	 * get patient string identifiers from a patient using a social security number
	 * @see https://www.hl7.org/fhir/identifier-registry.html
	 *
	 * @return void
	 */
	public function getFhirStringIdentifiers()
	{
		
		// get an access_token
		$getAccessToken = function()
		{
			global $userid;
			$user_id = User::getUIIDByUsername($userid);
			$tokenManager = new FhirTokenManager($user_id);
			if(!$token = $tokenManager->getToken()) throw new Exception("Error: You do not have a FHIR access token or you do not have proper user privileges in the EHR.", 400);
			return $token->access_token;
		};

		// get patient data using SSN
		$getPatientDataUsingSsn = function($ssn, $access_token)
		{
			global $fhir_endpoint_base_url;
			$ssn_string_identifier = '2.16.840.1.113883.4.1'; // string identifier for SSN
			$fhirServices = FhirServices::getInstance();
			$url = $fhir_endpoint_base_url."Patient/?identifier={$ssn_string_identifier}|{$ssn}";

			$data = $fhirServices->getFhirData($url, $access_token);
			return $data;
		};

		// extract string identifiers from a patient resource
		$extractStringIdentifiers = function($fhir_data)
		{
			$patient = $fhir_data->entry[0]->resource;
			$string_identifiers = $patient->identifier;
			return $string_identifiers;
		};

		try {
		
			$ssn = trim($_GET['ssn']);
			preg_match('/[^\d\s-]/',$ssn, $not_allowed_matches); // only numbers, dashes, and spaces are allowed
			// check if SSN is empty or contains characters not allowed
			if(empty($ssn) || !empty($not_allowed_matches)) throw new Exception("Error: A valid SSN must be provided", 400);

			// extract numbers from the ssn string
			preg_match_all('/\d+/', $ssn, $matches);
			$ssn_numbers = implode('', $matches[0]);

			$access_token = $getAccessToken();
			$data = $getPatientDataUsingSsn($ssn_numbers, $access_token);
			if($data->total==0) throw new Exception("No patient found for the provided SSN ({$ssn_numbers})", 404);
			$string_identifiers = $extractStringIdentifiers($data);

			$response = array(
				'ssn' => $ssn_numbers,
				// 'patient' => $data->entry[0]->resource,
				'string_identifiers' => $string_identifiers,
				'success' => true,
			);

			HttpClient::printJSON($response);
		} catch (\Exception $e) {
			$response = array('message' => $e->getMessage());
			HttpClient::printJSON($response, $e->getCode());
		}
	}
}