<?php

class APIPlayground
{
	private $token;
	private $fields;
	private $status;
	private $headers;
	private $file_ext;
	private $file_mime;
	private $lang;

	function getFieldNames($project_id)
	{
		$names = array();
		$sql = "
			SELECT field_name
			FROM redcap_metadata
			WHERE project_id = $project_id
			ORDER BY field_name
		";
		$query = db_query($sql);
		while ($row = db_fetch_assoc($query))
		{
			$names[$row['field_name']] = $row['field_name'];
		}
		return $names;
	}

	function getInstruments($project_id)
	{
		$insts = array();
		$sql = "
			SELECT DISTINCT form_name
			FROM redcap_metadata
			WHERE project_id = $project_id
			ORDER BY form_name
		";
		$query = db_query($sql);
		while ($row = db_fetch_assoc($query))
		{
			$insts[$row['form_name']] = $row['form_name'];
		}
		return $insts;
	}

	function getArms($project_id)
	{
		$arms = array();
		$sql = "
			SELECT
				arm_num,
				arm_name
			FROM redcap_events_arms
			WHERE project_id = $project_id
			ORDER BY arm_num
		";
		$query = db_query($sql);
		while ($row = db_fetch_assoc($query))
		{
			$arms[$row['arm_num']] = $row['arm_name'];
		}
		return $arms;
	}

	function expReportsSetFields()
	{
		$this->fields = array(
			'token'     => $this->token,
			'content'   => 'report',
			'format'    => $_SESSION['api_fmt'],
			'report_id' => $_SESSION['api_report_id'],
			'csvDelimiter' => $_SESSION['api_csvdelimiter']
		);

		if( isset( $_SESSION['api_name_label'] ) )
		{
			$this->fields['rawOrLabel'] = $_SESSION['api_name_label'];
		}

		if( isset( $_SESSION['api_header_label'] ) )
		{
			$this->fields['rawOrLabelHeaders'] = $_SESSION['api_header_label'];
		}

		if( isset( $_SESSION['api_checkbox_label'] ) )
		{
			$this->fields['exportCheckboxLabel'] = $_SESSION['api_checkbox_label'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expFileSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'file',
			'action'  => 'export',
			'record'  => $_SESSION['api_record'],
			'field'   => $_SESSION['api_field_name'],
			'event'   => $_SESSION['api_event'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function delFileSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'file',
			'action'  => 'delete',
			'record'  => $_SESSION['api_record'],
			'field'   => $_SESSION['api_field_name'],
			'event'   => $_SESSION['api_event'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function impFileSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'file',
			'action'  => 'import',
			'record'  => $_SESSION['api_record'],
			'field'   => $_SESSION['api_field_name'],
			'event'   => $_SESSION['api_event'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function impRecordsSetFields()
	{
		$this->fields = array(
			'token'             => $this->token,
			'content'           => 'record',
			'format'            => $_SESSION['api_fmt'],
			'type'              => $_SESSION['api_type'],
			'overwriteBehavior' => $_SESSION['api_overwrite'],
			'forceAutoNumber' 	=> $_SESSION['api_forceAutoNumber'],
			'data'              => $_SESSION['api_data']
		);

		if( isset( $_SESSION['api_date_format'] ) )
		{
			$this->fields['dateFormat'] = $_SESSION['api_date_format'];
		}

		if( isset( $_SESSION['api_return_content'] ) )
		{
			$this->fields['returnContent'] = $_SESSION['api_return_content'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function delRecordsSetFields()
        {
		$this->fields = array(
			'token'   => $this->token,
			'action'  => 'delete',
			'content' => 'record',
		);
		if( isset( $_SESSION['api_records'] ) )
		{
			$this->fields['records'] = $_SESSION['api_records'];
		}
        }

	function expRecordsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'record',
			'format'  => $_SESSION['api_fmt'],
			'type'    => $_SESSION['api_type'],
			'csvDelimiter' => $_SESSION['api_csvdelimiter']
		);

		if( isset( $_SESSION['api_records'] ) )
		{
			$this->fields['records'] = $_SESSION['api_records'];
		}

		if( isset( $_SESSION['api_field_names'] ) )
		{
			$this->fields['fields'] = $_SESSION['api_field_names'];
		}

		if( isset( $_SESSION['api_insts'] ) )
		{
			$this->fields['forms'] = $_SESSION['api_insts'];
		}

		if( isset( $_SESSION['api_events'] ) )
		{
			$this->fields['events'] = $_SESSION['api_events'];
		}

		if( isset( $_SESSION['api_name_label'] ) )
		{
			$this->fields['rawOrLabel'] = $_SESSION['api_name_label'];
		}

		if( isset( $_SESSION['api_header_label'] ) )
		{
			$this->fields['rawOrLabelHeaders'] = $_SESSION['api_header_label'];
		}

		if( isset( $_SESSION['api_checkbox_label'] ) )
		{
			$this->fields['exportCheckboxLabel'] = $_SESSION['api_checkbox_label'];
		}

		if( isset( $_SESSION['api_survey_field'] ) )
		{
			$this->fields['exportSurveyFields'] = $_SESSION['api_survey_field'];
		}

		if( isset( $_SESSION['api_dag'] ) )
		{
			$this->fields['exportDataAccessGroups'] = $_SESSION['api_dag'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}

		if( isset( $_SESSION['api_filter_logic'] ) )
		{
			$this->fields['filterLogic'] = $_SESSION['api_filter_logic'];
		}

		if( isset( $_SESSION['api_date_range_begin'] ) )
		{
			$this->fields['dateRangeBegin'] = $_SESSION['api_date_range_begin'];
		}

		if( isset( $_SESSION['api_date_range_end'] ) )
		{
			$this->fields['dateRangeEnd'] = $_SESSION['api_date_range_end'];
		}
	}

	function expProjXmlSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'project_xml',
			'format'  => $_SESSION['api_fmt'],
			'returnMetadataOnly'  => false,
			'exportFiles'  => false
		);

		if( isset( $_SESSION['api_returnMetadataOnly'] ) )
		{
			$this->fields['returnMetadataOnly'] = $_SESSION['api_returnMetadataOnly'];
		}

		if( isset( $_SESSION['api_records'] ) )
		{
			$this->fields['records'] = $_SESSION['api_records'];
		}

		if( isset( $_SESSION['api_field_names'] ) )
		{
			$this->fields['fields'] = $_SESSION['api_field_names'];
		}

		if( isset( $_SESSION['api_events'] ) )
		{
			$this->fields['events'] = $_SESSION['api_events'];
		}

		if( isset( $_SESSION['api_survey_field'] ) )
		{
			$this->fields['exportSurveyFields'] = $_SESSION['api_survey_field'];
		}

		if( isset( $_SESSION['api_dag'] ) )
		{
			$this->fields['exportDataAccessGroups'] = $_SESSION['api_dag'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}

		if( isset( $_SESSION['api_filter_logic'] ) )
		{
			$this->fields['filterLogic'] = $_SESSION['api_filter_logic'];
		}
		
		if( isset( $_SESSION['api_exportFiles'] ) )
		{
			$this->fields['exportFiles'] = $_SESSION['api_exportFiles'];
		}

		if( isset( $_SESSION['api_date_range_begin'] ) )
		{
			$this->fields['dateRangeBegin'] = $_SESSION['api_date_range_begin'];
		}

		if( isset( $_SESSION['api_date_range_end'] ) )
		{
			$this->fields['dateRangeEnd'] = $_SESSION['api_date_range_end'];
		}
	}

	function impMetadataSetFields()
	{
		$this->fields = array(
			'token'             => $this->token,
			'content'           => 'metadata',
			'format'            => $_SESSION['api_fmt'],
			'data'              => $_SESSION['api_data']
		);

		if( isset( $_SESSION['api_field_names'] ) )
		{
			$this->fields['fields'] = $_SESSION['api_field_names'];
		}

		if( isset( $_SESSION['api_insts'] ) )
		{
			$this->fields['forms'] = $_SESSION['api_insts'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expMetadataSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'metadata',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}

		if( isset( $_SESSION['api_field_names'] ) )
		{
			$this->fields['fields'] = $_SESSION['api_field_names'];
		}

		if( isset( $_SESSION['api_insts'] ) )
		{
			$this->fields['forms'] = $_SESSION['api_insts'];
		}
	}

	function expFieldNamesSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'exportFieldNames',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['api_field_name'] ) )
		{
			$this->fields['field'] = $_SESSION['api_field_name'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expInstrPdfSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'pdf',
		);

		if( isset( $_SESSION['api_record'] ) )
		{
			$this->fields['record'] = $_SESSION['api_record'];
		}

		if( isset( $_SESSION['api_event'] ) )
		{
			$this->fields['event'] = $_SESSION['api_event'];
		}

		if( isset( $_SESSION['api_inst'] ) )
		{
			$this->fields['instrument'] = $_SESSION['api_inst'];
		}

		if( isset( $_SESSION['api_all_records'] ) && $_SESSION['api_all_records'] == 'true')
		{
			$this->fields['allRecords'] = $_SESSION['api_all_records'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expInstrSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'instrument',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expRepeatingFormsEventsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'repeatingFormsEvents',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}


	function impRepeatingFormsEventsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'repeatingFormsEvents',
			'format'  => $_SESSION['api_fmt'],
			'data'    => $_SESSION['api_data'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expSurvLinkSetFields()
	{
		$this->fields = array(
			'token'      => $this->token,
			'content'    => 'surveyLink',
			'format'     => $_SESSION['api_fmt'],
			'instrument' => $_SESSION['api_inst'],
			'event'      => $_SESSION['api_event'],
			'record'     => $_SESSION['api_record'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expSurvQueueLinkSetFields()
	{
		$this->fields = array(
			'token'      => $this->token,
			'content'    => 'surveyQueueLink',
			'format'     => $_SESSION['api_fmt'],
			'record'     => $_SESSION['api_record'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expSurvRetCodeSetFields()
	{
		$this->fields = array(
			'token'      => $this->token,
			'content'    => 'surveyReturnCode',
			'format'     => $_SESSION['api_fmt'],
			'instrument' => $_SESSION['api_inst'],
			'event'      => $_SESSION['api_event'],
			'record'     => $_SESSION['api_record'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expSurvPartsSetFields()
	{
		$this->fields = array(
			'token'      => $this->token,
			'content'    => 'participantList',
			'format'     => $_SESSION['api_fmt'],
			'instrument' => $_SESSION['api_inst'],
			'event'      => $_SESSION['api_event'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function impInstEventMapsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'formEventMapping',
			'data'    => $_SESSION['api_data'],
			'format'  => $_SESSION['api_fmt']
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expInstEventMapsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'formEventMapping',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['arm_nums'] ) )
		{
			$this->fields['arms'] = $_SESSION['arm_nums'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expArmsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'arm',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['arm_nums'] ) )
		{
			$this->fields['arms'] = $_SESSION['arm_nums'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function impArmsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'arm',
			'action'  => 'import',
			'format'  => $_SESSION['api_fmt'],
			'data'    => $_SESSION['api_data'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function delArmsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'arm',
			'action'  => 'delete',
			'arms'    => $_SESSION['arm_nums'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expUsersSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'user',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function impUsersSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'user',
			'format'  => $_SESSION['api_fmt'],
			'data'    => $_SESSION['api_data']
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

        function impProjSettSetFields()
        {
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'project_settings',
			'format'  => $_SESSION['api_fmt'],
			'data'    => $_SESSION['api_data']
		);
        }

	function expNextIdSetFields()
        {
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'generateNextRecordName'
		);
        }

	function expProjSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'project',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function impProjSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'project',
			'action'  => 'import',
			'format'  => $_SESSION['api_fmt'],
			'data'    => $_SESSION['api_data']
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expRcVSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'version'
		);
	}

	function expEventsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'event',
			'format'  => $_SESSION['api_fmt'],
		);

		if( isset( $_SESSION['arm_nums'] ) )
		{
			$this->fields['arms'] = $_SESSION['arm_nums'];
		}

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function impEventsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'event',
			'action'  => 'import',
			'format'  => $_SESSION['api_fmt'],
			'data'    => $_SESSION['api_data'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function delEventsSetFields()
	{
		$this->fields = array(
			'token'   => $this->token,
			'content' => 'event',
			'action'  => 'delete',
			'events'  => $_SESSION['api_events'],
		);

		if( isset( $_SESSION['api_return'] ) )
		{
			$this->fields['returnFormat'] = $_SESSION['api_return'];
		}
	}

	function expEventsRawData()
	{
		return $this->drawRawData();
	}

	function expEventsFormattedData()
	{
		return $this->drawFormattedData();
	}

	function drawRawData()
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			if(is_array( $v )) $v = implode(',', $v );
			$str[] = "$k: $v";
		}
		return implode("\n", $str);
	}

	function phpDrawFormattedData($data=null)
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			if(is_array($v))
			{
				// wrap values in quotes
				$vv = array();
				foreach($v as $key) $vv[] = "'$key'";
				$v = 'array(' . implode(',', $vv) . ')';
			}
			else
			{
				$v = "'".str_replace("'", "\'", $v)."'";
			}

			$str[] = "    '$k' => $v";
		}
		return "\$data = array(\n" . implode(",\n", $str) . "\n);";
	}

	function curlDrawFormattedData($data=null)
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			if(is_array($v))
			{
				$x = 0;
				foreach($v as $key)
				{
					$str[] = htmlentities($k . "[$x]" . "=$key");
					++$x;
				}
				continue;
			}

			$str[] = htmlentities("$k=$v");
		}
		return implode("&", $str);
	}

	function perlDrawFormattedData($data=null)
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			if(is_array($v))
			{
				$x = 0;
				foreach($v as $key)
				{
					$str[] = "    '$k" . "[$x]'" . " => '$key'";
					++$x;
				}
				continue;
			} else {
                $v = str_replace("'", "\'", $v);
            }

			$str[] = "    $k => '$v'";
		}
		return "my \$data = {\n" . implode(",\n", $str) . "\n};";
	}

	function perlDrawFormattedDataFields($data)
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			if(is_array($v))
			{
				// wrap values in quotes
				$vv = array();
				foreach($v as $key) $vv[] = "'$key'";
				$v = '{' . implode(',', $vv) . '}';
			}
			else
			{
				$v = "'$v'";
			}

			$str[] = "        $k => $v";
		}
		return implode(",\n", $str);
	}


	function pythonDrawFormattedData($data=null)
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			if(is_array($v))
			{
				$x = 0;
				foreach($v as $key)
				{
					$str[] = "    '$k" . "[$x]'" . ": '$key'";
					++$x;
				}
				continue;
			} else {
			    $v = str_replace("'", "\'", $v);
			    // Escape newline chars for python
			    $v = htmlentities(str_replace("\r","\\r",str_replace("\n","\\n",html_entity_decode($v))));
            }

			if($k != 'record_id' && $k != 'file')
			{
				$v = "'$v'";
			}

			$str[] = "    '$k': $v";
		}
		return "data = {\n" . implode(",\n", $str) . "\n}";
	}

	function rubyDrawFormattedData($data=null)
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			if(is_array($v))
			{
				$x = 0;
				foreach($v as $key)
				{
					$str[] = "    '$k" . "[$x]'" . " => '$key'";
					++$x;
				}
				continue;
			} else {
                $v = str_replace("'", "\'", $v);
            }

			if($k != 'record_id') $v = "'$v'";
			$str[] = "    :$k => $v";
		}
		return "data = {\n" . implode(",\n", $str) . "\n}";
	}

	function javaDrawFormattedData($data=null)
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			$array = '';

			if(is_array($v))
			{
				$array = "    array = new ArrayList<String>();\n";

				$vv = array();
				foreach($v as $key) $array .= "    array.add(\"$key\");\n";
				$v = 'array.toString()';
			}
			else
			{
				if($k != 'record_id') $v = "\"".str_replace('"', '\"', $v)."\"";
			}

			$str[] = $array . "    params.add(new BasicNameValuePair(\"$k\", $v);";
		}

		return "    params = new ArrayList<NameValuePair>();\n" . implode("\n", $str) . "\n";
	}

	function rDrawFormattedData($data=null)
	{
		$str = array();
		foreach($this->fields as $k => $v)
		{
			if(is_array($v))
			{
				$x = 0;
				foreach($v as $key)
				{
					$str[] = "    '$k" . "[$x]'" . "='$key'";
					++$x;
				}
				continue;
			} else {
                $v = str_replace("'", "\'", $v);
            }

			$str[] = "    $k='$v'";
		}
		return implode(",\n", $str);
	}

	function pythonGetCode()
	{
		$output_cmd = $file_def = $more_imports = $file_param = $file_open = $file_close = '';

		// Tailor call to API method
		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import') {
			// Import File			
			$file_def = "\nfile_path = '$_SESSION[api_file_path]'";
			$file_param = ",files={'file':file_obj}";
			$file_open = "\nfile_obj = open(file_path, 'rb')";
			$file_close = "\nfile_obj.close()";
			// $this->fields['file'] = '(pycurl.FORM_FILE, file)';
		}
		elseif ($this->fields['content'] == 'file' && $this->fields['action'] == 'export') {
			// Export File
			$output_cmd = "\n\nf = open('/tmp/file.raw.txt', 'wb')\nf.write(r.content)\nf.close()";
		}
		elseif ($this->fields['content'] == 'pdf') {
			// Export PDF File of Instruments			
			$output_cmd = "\n\nf = open('/tmp/export.pdf', 'wb')\nf.write(r.content)\nf.close()";
		}		
		else {
			// other methods
			$output_cmd = "\nprint(r.text)";
		}

		// Tailor output to chosen return & error formats (json)
		if ($this->fields['format'] == 'json' && $this->fields['returnFormat'] == 'json') {
			$output_cmd = str_replace('r.text', 'r.json()', $output_cmd);
		}		

		// TJ - this section may be obsolete, as "record_id" isn't a required param for the "import records" method, according to the docs. 
		// . ..Unless this is used to generate the "API > examples" files.
		/*
		if($_SESSION['api_call'] == 'imp_records')
		{
			$more_imports = ', hashlib';
			$this->fields['record_id'] = 'hashlib.sha1().hexdigest()[:16]';
		}
		*/

		return "#!/usr/bin/env python
import requests$more_imports$file_def
" . $this->pythonDrawFormattedData() . "$file_open
r = requests.post('" . APP_PATH_WEBROOT_FULL . "api/',data=data$file_param)$file_close
print('HTTP Status: ' + str(r.status_code))$output_cmd
";

/* import pycurl, cStringIO$more_imports
buf = cStringIO.StringIO()$file_def
" . $this->pythonDrawFormattedData() . "
ch = pycurl.Curl()
ch.setopt(ch.URL, '" . APP_PATH_WEBROOT_FULL . "api/')
ch.setopt(ch.HTTPPOST, data.items())
ch.setopt(ch.WRITEFUNCTION, buf.write)
ch.perform()
ch.close()
print buf.getvalue()
buf.close()
"; */
	}

	function rubyGetFileImportCode()
	{
		return "#!/usr/bin/env ruby
require 'digest/sha1'
require 'net/http'
require 'uri'
file = '$_SESSION[api_file_path]'
BOUNDARY = Digest::SHA1.hexdigest(Time.now.usec.to_s)
" . $this->rubyDrawFormattedData() . "
body = <<-EOF
--#{BOUNDARY}
Content-Disposition: form-data; name=\"file\"; filename=\"#{File.basename(file)}\"
Content-Type: application/octet-stream\\n
#{File.read(file)}
--#{BOUNDARY}
#{fields.collect{|k,v|\"Content-Disposition: form-data; name=\"#{k.to_s}\"\\n\\n#{v}\\n--#{BOUNDARY}\\n\"}.join}\\n
EOF
uri = URI.parse(Settings::API_URL)
http = Net::HTTP.new(uri.host, uri.port)
req = Net::HTTP::Post.new(uri.request_uri)
req.body = body
req['Content-Type'] = \"multipart/form-data, boundary=#{BOUNDARY}\"
resp = http.request(req)
puts resp.code
";
	}

	function rubyGetCode()
	{
		// special case for file upload
		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import')
		{
			return $this->rubyGetFileImportCode();
		}

		return "#!/usr/bin/env ruby
require 'curl'
" . $this->rubyDrawFormattedData() . "
ch = Curl::Easy.http_post(
  '" . APP_PATH_WEBROOT_FULL . "api/',
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
";
	}

	function javaGetFileImportCode()
	{
		return "package org.projectredcap.main;

import java.io.BufferedReader;
import java.io.File;
import java.io.InputStreamReader;

import org.apache.http.HttpEntity;
import org.apache.http.HttpResponse;
import org.apache.http.client.HttpClient;
import org.apache.http.client.methods.HttpPost;
import org.apache.http.entity.ContentType;
import org.apache.http.entity.mime.MultipartEntityBuilder;
import org.apache.http.impl.client.HttpClientBuilder;

public class MyClass
{
  private final HttpPost post;
  private HttpResponse resp;
  private final HttpClient client;
  private int respCode;
  private BufferedReader reader;
  private final StringBuffer result;
  private String line;
  private final File file;
  private final HttpEntity httpEntity;
  private final MultipartEntityBuilder multipartEntityBuilder;

  public MyClass()
  {
    file = new File(\"$_SESSION[api_file_path]\");

    multipartEntityBuilder = MultipartEntityBuilder.create();
    multipartEntityBuilder.addBinaryBody(
      \"file\", file,
      ContentType.create(\"application/octet-stream\"),
      file.getName()
    );
    multipartEntityBuilder.addTextBody(\"token\", \"$this->token\");
    multipartEntityBuilder.addTextBody(\"content\", \"file\");
    multipartEntityBuilder.addTextBody(\"action\", \"import\");
    multipartEntityBuilder.addTextBody(\"record\", \"$_SESSION[api_record]\");
    multipartEntityBuilder.addTextBody(\"field\", \"$_SESSION[api_field_name]\");
    multipartEntityBuilder.addTextBody(\"event\", \"$_SESSION[api_event]\");

    httpEntity = multipartEntityBuilder.build();

    post = new HttpPost(\"" . APP_PATH_WEBROOT_FULL . "api/\");

    try
    {
      post.setEntity(httpEntity);
    }
    catch (final Exception e)
    {
      e.printStackTrace();
    }

    result = new StringBuffer();
    client = HttpClientBuilder.create().build();
    respCode = -1;
    reader = null;
    line = null;
  }

  public void doPost()
  {
    resp = null;

    try
    {
      resp = client.execute(post);
    }
    catch (final Exception e)
    {
      e.printStackTrace();
    }

    if(resp != null)
    {
      respCode = resp.getStatusLine().getStatusCode();

      try
      {
        reader = new BufferedReader(new InputStreamReader(resp.getEntity().getContent()));
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }
    }

    if(reader != null)
    {
      try
      {
        while ((line = reader.readLine()) != null)
        {
          result.append(line);
        }
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }
    }

    System.out.println(\"respCode: \" + respCode);
    System.out.println(\"result: \" + result.toString());
  }
}";
	}

	function javaGetFileExportOrPDFCode()
	{
		$params = $fos = '';

		if($this->fields['content'] == 'file')
		{
			$params = "params.add(new BasicNameValuePair(\"content\", \"file\"));
    params.add(new BasicNameValuePair(\"action\", \"export\"));
    params.add(new BasicNameValuePair(\"record\", \"$_SESSION[api_record]\"));
    params.add(new BasicNameValuePair(\"field\", \"$_SESSION[api_field_name]\"));
    params.add(new BasicNameValuePair(\"event\", \"$_SESSION[api_event]\"));";

			$fos = "fos = new FileOutputStream(new File(\"/tmp/file.raw\"));";
		}

		if($this->fields['content'] == 'pdf')
		{
			$params = "params.add(new BasicNameValuePair(\"content\", \"pdf\"));
    params.add(new BasicNameValuePair(\"format\", \"json\"));";

			$fos = "fos = new FileOutputStream(new File(\"/tmp/export.pdf\"));";
		}

		return "package org.projectredcap.main;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.util.ArrayList;
import java.util.List;

import org.apache.http.HttpResponse;
import org.apache.http.NameValuePair;
import org.apache.http.client.HttpClient;
import org.apache.http.client.entity.UrlEncodedFormEntity;
import org.apache.http.client.methods.HttpPost;
import org.apache.http.impl.client.HttpClientBuilder;
import org.apache.http.message.BasicNameValuePair;

public class MyClass
{
  private final List<NameValuePair> params;
  private final HttpPost post;
  private HttpResponse resp;
  private final HttpClient client;
  private int respCode;
  private InputStream is;
  private FileOutputStream fos;
  private int read;
  private final byte[] buf;

  public MyClass()
  {
    params = new ArrayList<NameValuePair>();
    params.add(new BasicNameValuePair(\"token\", \"$this->token\"));
    $params

    post = new HttpPost(\"" . APP_PATH_WEBROOT_FULL . "api/\");
    post.setHeader(\"Content-Type\", \"application/x-www-form-urlencoded\");

    try
    {
      post.setEntity(new UrlEncodedFormEntity(params));
    }
    catch (final Exception e)
    {
      e.printStackTrace();
    }

    client = HttpClientBuilder.create().build();
    respCode = -1;
    is = null;
    fos = null;
    read = 0;
    buf = new byte[4096];
  }

  public void doPost()
  {
    resp = null;

    try
    {
      resp = client.execute(post);
    }
    catch (final Exception e)
    {
      e.printStackTrace();
    }

    if(resp != null)
    {
      respCode = resp.getStatusLine().getStatusCode();
      System.out.println(\"respCode: \" + respCode);

      if(respCode != 200)
      {
        return;
      }

      try
      {
        is = resp.getEntity().getContent();
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }

      try
      {
        $fos
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }

      try
      {
        while ((read = is.read(buf)) > 0)
        {
          fos.write(buf, 0, read);
        }
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }

      try
      {
        fos.close();
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }

      try
      {
        is.close();
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }
    }
  }
}";
	}

	function javaGetCode()
	{
		// special case for file import
		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import')
		{
			return $this->javaGetFileImportCode();
		}

		// file exports
		if(($this->fields['content'] == 'file' && $this->fields['action'] == 'export')
			|| $this->fields['content'] == 'pdf')
		{
			return $this->javaGetFileExportOrPDFCode();
		}

		return "package org.projectredcap.main;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.util.ArrayList;
import java.util.List;

import org.apache.http.HttpResponse;
import org.apache.http.NameValuePair;
import org.apache.http.client.HttpClient;
import org.apache.http.client.entity.UrlEncodedFormEntity;
import org.apache.http.client.methods.HttpPost;
import org.apache.http.impl.client.HttpClientBuilder;
import org.apache.http.message.BasicNameValuePair;

public class MyClass
{
  private final List<NameValuePair> params;
  private final HttpPost post;
  private HttpResponse resp;
  private final HttpClient client;
  private int respCode;
  private BufferedReader reader;
  private final StringBuffer result;
  private String line;

  public MyClass()
  {
" . $this->javaDrawFormattedData() . "
    post = new HttpPost(\"" . APP_PATH_WEBROOT_FULL . "api/\");
    post.setHeader(\"Content-Type\", \"application/x-www-form-urlencoded\");

    try
    {
      post.setEntity(new UrlEncodedFormEntity(params));
    }
    catch (final Exception e)
    {
      e.printStackTrace();
    }

    result = new StringBuffer();
    client = HttpClientBuilder.create().build();
    respCode = -1;
    reader = null;
    line = null;
  }

  public void doPost()
  {
    resp = null;

    try
    {
      resp = client.execute(post);
    }
    catch (final Exception e)
    {
      e.printStackTrace();
    }

    if(resp != null)
    {
      respCode = resp.getStatusLine().getStatusCode();

      try
      {
        reader = new BufferedReader(new InputStreamReader(resp.getEntity().getContent()));
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }
    }

    if(reader != null)
    {
      try
      {
        while ((line = reader.readLine()) != null)
        {
          result.append(line);
        }
      }
      catch (final Exception e)
      {
        e.printStackTrace();
      }
    }

    System.out.println(\"respCode: \" + respCode);
    System.out.println(\"result: \" + result.toString());
  }
}";
	}

	function perlGetFileImportCode()
	{
		return "#!/usr/bin/env perl
use strict;
use warnings;
use LWP::UserAgent;
use HTTP::Request::Common;
my \$file = '$_SESSION[api_file_path]';
my \$ua = LWP::UserAgent->new;
my \$req = \$ua->request(
    POST '" . APP_PATH_WEBROOT_FULL . "api/',
    Content_Type => 'form-data',
    Content => [
" . $this->perlDrawFormattedDataFields() . "
	file => [\$file]
    ]
);
print \$req->is_success;
";
	}

	function perlGetCode()
	{
		// special case for file upload
		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import')
		{
			return $this->perlGetFileImportCode();
		}

		return "#!/usr/bin/env perl
use strict;
use warnings;
use LWP::Curl;
" . $this->perlDrawFormattedData() . "
my \$ch = LWP::Curl->new();
my \$content = \$ch->post(
    '" . APP_PATH_WEBROOT_FULL . "api/',
    \$data,
    'http://myreferer.com/'
);
print \$content;
";
	}

	function phpGetCode()
	{
		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import')
		{
			$more_data = "\$data['file'] = (function_exists('curl_file_create') ? curl_file_create('$_SESSION[api_file_path]', '$_SESSION[api_file_mime]', '$_SESSION[api_file_name]') : \"@$_SESSION[api_file_path]\");\n";
			$post_fields = "curl_setopt(\$ch, CURLOPT_POSTFIELDS, \$data);";
		}
		else
		{
			$more_data = '';
			$post_fields = "curl_setopt(\$ch, CURLOPT_POSTFIELDS, http_build_query(\$data, '', '&'));";
		}

		return "<?php\n" . $this->phpDrawFormattedData() . "
$more_data\$ch = curl_init();
curl_setopt(\$ch, CURLOPT_URL, '" . APP_PATH_WEBROOT_FULL . "api/');
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt(\$ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt(\$ch, CURLOPT_VERBOSE, 0);
curl_setopt(\$ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt(\$ch, CURLOPT_AUTOREFERER, true);
curl_setopt(\$ch, CURLOPT_MAXREDIRS, 10);
curl_setopt(\$ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt(\$ch, CURLOPT_FRESH_CONNECT, 1);
$post_fields
\$output = curl_exec(\$ch);
print \$output;
curl_close(\$ch);
";
	}

	function rGetFileImportCode()
	{
		return "#!/usr/bin/env Rscript
library(RCurl)
file = '$_SESSION[api_file_path]'
result <- postForm(
    uri='" . APP_PATH_WEBROOT_FULL . "api/',
    token=$this->token,
    content='file',
    action='import',
    record='$_SESSION[api_record]',
    field='$_SESSION[api_field_name]',
    event='$_SESSION[api_event]',
    returnFormat='$_SESSION[api_return]',
    file=fileUpload(file)
)
print(result)
";
	}

	function rGetPDFCode()
	{
		return "#!/usr/bin/env Rscript
library(RCurl)
result <- postForm(
    uri='" . APP_PATH_WEBROOT_FULL . "api/',
    binary=TRUE,
" . $this->rDrawFormattedData() . "
)
f <- file('/tmp/export.pdf', 'wb')
writeBin(as.vector(result), f)
close(f)
";
	}

	function rGetCode()
	{
		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import')
		{
			return $this->rGetFileImportCode();
		}

		if($this->fields['content'] == 'pdf')
		{
			return $this->rGetPDFCode();
		}

		return "#!/usr/bin/env Rscript
library(RCurl)
result <- postForm(
    uri='" . APP_PATH_WEBROOT_FULL . "api/',
" . $this->rDrawFormattedData() . "
)
print(result)
";
	}

	function curlGetFileImportCode()
	{
		return "#!/bin/sh
CURL=`which curl`
\$CURL -H \"Accept: application/json\" \
      -F \"token=$this->token\" \
      -F \"content=file\" \
      -F \"action=import\" \
      -F \"record=$_SESSION[api_record]\" \
      -F \"field=$_SESSION[api_field_name]\" \
      -F \"event=$_SESSION[api_event]\" \
      -F \"filename=$_SESSION[api_file_name]\" \
      -F \"file=@$_SESSION[api_file_path]\" \
      " . APP_PATH_WEBROOT_FULL . "api/
";
	}

	function curlGetCode()
	{
		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import')
		{
			return $this->curlGetFileImportCode();
		}

		$out = '';

		if($this->fields['content'] == 'file' && $this->fields['action'] == 'export')
		{
			$out = "\n      -o /tmp/file.raw \     ";
		}

		if($this->fields['content'] == 'pdf')
		{
			$out = "\n      -o /tmp/export.pdf \     ";
		}

		return "#!/bin/sh
DATA=\"" . $this->curlDrawFormattedData() . "\"
CURL=`which curl`
\$CURL -H \"Content-Type: application/x-www-form-urlencoded\" \
      -H \"Accept: application/json\" \
      -X POST \
      -d \$DATA \\$out
      " . APP_PATH_WEBROOT_FULL . "api/
";
	}

	function getCode()
	{
		$this->setFields();
		$func = str_replace(' ', '', ucwords(str_replace('_', ' ', $_SESSION['code_tab'] . '_get_code')));
		$func[0] = strtolower($func[0]);
		return $this->$func();
	}

	function setFields()
	{
		$func = str_replace(' ', '', ucwords(str_replace('_', ' ', $_SESSION['api_call'] . '_set_fields')));
		$func[0] = strtolower($func[0]);
		$this->$func();
	}

	function getRawData()
	{
		$this->setFields();

		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import' && isset($_SESSION['api_file_path']))
		{
			$this->fields['file'] = basename($_SESSION['api_file_path']);
		}

		return $this->drawRawData();
	}

	function getFormattedData()
	{
		$this->setFields();

		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import' && isset($_SESSION['api_file_path']))
		{
			$this->fields['file'] = basename($_SESSION['api_file_path']);
		}

		$func = str_replace(' ', '', ucwords(str_replace('_', ' ', $_SESSION['code_tab'] . '_draw_formatted_data')));
		$func[0] = strtolower($func[0]);
		return $this->$func();
	}

	function setStatus($status)
	{
		$this->status = $status;
	}

	function getStatus()
	{
		return $this->status;
	}

	function httpParseHeaders($raw)
	{
		$headers = array();
		$key = '';

		foreach(explode("\n", $raw) as $i => $h)
		{
			$h = explode(':', $h, 2);

			if(isset($h[1]))
			{
				if(!isset($headers[$h[0]]))
				{
					$headers[$h[0]] = trim($h[1]);
				}
				elseif(is_array($headers[$h[0]]))
				{
					$headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
				}
				else
				{
					$headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
				}

				$key = $h[0];
			}
			else
			{
				if(substr($h[0], 0, 1) == "\t")
				{
					$headers[$key] .= "\r\n\t" . trim($h[0]);
				}
				elseif(!$key)
				{
					$headers[0] = trim($h[0]);
				}
			}
		}

		return $headers;
	}

	// curl callback
	function readHeader($ch, $header)
	{
		$this->headers = $this->httpParseHeaders($header);

		if(isset($this->headers['Content-Type']))
		{
			$a = explode(';', $this->headers['Content-Type']);
			$this->file_mime = $a[0];

			if(count($a) >= 2)
			{
				$b = explode('=', $a[1]);
				if(count($b) >= 2)
				{
					$filename = str_replace('"', '', $b[1]);
					$c = explode('.', $filename);
					if(count($c) >= 2)
					{
						$this->file_ext = $c[count($c)-1];
					}
				}
			}
		}

		// override for missing PDF header info
		if($_SESSION['api_call'] == 'exp_instr_pdf')
		{
			$this->file_ext = 'pdf';
		}

		// required for this function to be a valid curl callback
		return strlen($header);
	}

	function getResponse()
	{
		if (!function_exists('curl_init'))
		{
			$_SESSION['api_playground_curl_bypass'] = 1;
		}

		$url = APP_PATH_WEBROOT_FULL . 'api/';

		$this->setFields();
		if ($this->fields['csvDelimiter'] == "tab") $this->fields['csvDelimiter'] = "\t";
		$this->fields['playground'] = 1;
		if($this->fields['content'] == 'file' && $this->fields['action'] == 'import' && isset($_SESSION['api_file_path']))
		{
			$this->fields['file'] = (function_exists('curl_file_create') ? curl_file_create($_SESSION['api_file_path'], $_SESSION['api_file_mime'], $_SESSION['api_file_name']) : "@".$_SESSION['api_file_path']);
		}

		if (!isset($_SESSION['api_playground_curl_bypass']))
		{
			// these have to be set before the file below
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeader'));
			if (!sameHostUrl($url)) {
				curl_setopt($ch, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
			}

			if($this->fields['content'] == 'file' && $this->fields['action'] == 'import' && isset($_SESSION['api_file_path']))
			{
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->fields);
			}
			else
			{
				// only use http_build_query() if there's no file to upload
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->fields, '', '&'));
			}

			$resp = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$this->setStatus($http_code);
			curl_close($ch);
		}

		// If curl fails for some reason, try backup method via file_get_contents()
		if (isset($_SESSION['api_playground_curl_bypass']) || (isset($http_code) && $http_code == '0'))
		{
			$http_array = array('method'=>'POST',
								'header'=>"Content-type: application/x-www-form-urlencoded",
								'content'=>http_build_query($this->fields, '', '&')
						  );
			$resp = file_get_contents($url, false, stream_context_create(array('http'=>$http_array)));
			$this->setStatus($resp === false ? 'Unknown' : 200);
			// Set session value to use this method first instead of curl
			if ($resp !== false && !isset($_SESSION['api_playground_curl_bypass'])) {
				$_SESSION['api_playground_curl_bypass'] = 1;
			}
		}

		if($this->status == 200 && $this->fields['content'] == 'pdf'
			|| ($this->fields['content'] == 'file' && $this->fields['action'] == 'export'))
		{
			$path = sys_get_temp_dir() . DS . 'exported.' . $this->file_ext;
			$_SESSION['api_exp_file_path'] = $path;
			$_SESSION['api_exp_file_mime'] = $this->file_mime;
			file_put_contents($path, $resp);
		}

		// textarea rendering magic
		if($_SESSION['api_fmt'] == 'csv')
		{
			$resp = str_replace(array("\n", "\r", "\r\n", "\t"), array('&#10;', '&#13;', '&#13;&#10;', '&#09;'), $resp);
		}

		return $resp;
	}

	function getAPIFormats()
	{
		$a = array('json', 'xml', 'csv');
		sort($a);
		return $a;
	}

	function getTypeNames()
	{
		$a = array('eav', 'flat');
		sort($a);
		return $a;
	}

	function getBooleanTypes()
	{
		return array('true', 'false');
	}

	function getRawLabelTypes()
	{
		return array('raw', 'label');
	}

	function getOverwriteOptions()
	{
		return array('normal', 'overwrite');
	}

	function getDateFormatOptions()
	{
		return array(
			'YMD' => 'Y-M-D',
			'MDY' => 'M/D/Y',
			'DMY' => 'D/M/Y'
		);
	}

	function getReturnContentOptions()
	{
		return array(
			'count'   => 'count',
			'ids'     => 'ids',
			'auto_ids'=> 'auto_ids',
			'nothing' => 'nothing'
		);
	}

	function getLangName($lang)
	{
		switch($lang)
		{
		case 'php':
			return 'PHP';
		case 'curl':
			return 'cURL';
		default:
			return ucfirst($lang);
		}
	}

	static function getLangs()
	{
		return array('php', 'perl', 'python', 'ruby', 'java', 'r', 'curl',);
	}

	function getUploadErrorMessages($err)
	{
		switch($err)
		{
		case UPLOAD_ERR_INI_SIZE:
			$msg = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
			break;
		case UPLOAD_ERR_FORM_SIZE:
			$msg = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
			break;
		case UPLOAD_ERR_PARTIAL:
			$msg = "The uploaded file was only partially uploaded";
			break;
		case UPLOAD_ERR_NO_FILE:
			$msg = "No file was uploaded";
			break;
		case UPLOAD_ERR_NO_TMP_DIR:
			$msg = "Missing a temporary folder";
			break;
		case UPLOAD_ERR_CANT_WRITE:
			$msg = "Failed to write file to disk";
			break;
		case UPLOAD_ERR_EXTENSION:
			$msg = "File upload stopped by extension";
			break;
		default:
			$msg = "Unknown upload error";
			break;
		}
		return $msg;
	}

	public static function getAPIsArray()
	{
		global $lang;

		return array(

			// arms
			$lang['api_97'] => array(
				'exp_arms' => $lang['api_63'],
				'imp_arms' => $lang['api_82'],
				'del_arms' => $lang['api_83']
			),

			// events
			$lang['global_45'] => array(
				'exp_events' => $lang['api_62'],
				'imp_events' => $lang['api_84'],
				'del_events' => $lang['api_85']
			),

			// fields
			$lang['api_98'] => array(
				'exp_field_names' => $lang['api_52'],
			),

			// files
			$lang['api_99'] => array(
				'exp_file' => $lang['api_53'],
				'imp_file' => $lang['api_54'],
				'del_file' => $lang['api_55']
			),

			// instruments
			$lang['global_110'] => array(
				'exp_instr'           => $lang['api_56'],
				'exp_instr_pdf'       => $lang['api_57'],
				'exp_inst_event_maps' => $lang['api_64'],
				'imp_inst_event_maps' => $lang['api_81'],
			),

			// metadata
			$lang['api_100'] => array(
				'exp_metadata' => $lang['api_51'],
				'imp_metadata' => $lang['api_79'],
			),

			// projects
			$lang['control_center_134'] => array(
				'imp_proj' => $lang['api_78'],
				'imp_proj_sett' => $lang['api_133'],
				'exp_proj' => $lang['api_66'],
				'exp_proj_xml' => $lang['api_docs_252']
			),

			// records
			$lang['dashboard_38'] => array(
				'exp_records' => $lang['api_48'],
				'imp_records' => $lang['api_50'],
				'del_records' => $lang['api_130'],
				'exp_next_id' => $lang['api_134']
			),

			// repeating forms and events
			$lang['rep_forms_events_01'] => array(
				'exp_repeating_forms_events' => $lang['rep_forms_events_02'],
				'imp_repeating_forms_events' => $lang['rep_forms_events_05']
			),

			// reports
			$lang['app_06'] => array(
				'exp_reports' => $lang['api_49'],
			),

			// redcap
			$lang['api_101'] => array(
				'exp_rc_v' => $lang['api_67'],
			),

			// surveys
			$lang['dashboard_69'] => array(
				'exp_surv_link'       => $lang['api_58'],
				'exp_surv_parts'      => $lang['api_61'],
				'exp_surv_queue_link' => $lang['api_59'],
				'exp_surv_ret_code'   => $lang['api_60'],
			),

			// users
			$lang['api_docs_228'] => array(
				'exp_users'      => $lang['api_65'],
				'imp_users' => $lang['api_80'],
			),
		);
	}

	function getAPICalls($project)
	{
		$a = APIPlayground::getAPIsArray();

		// hide dangerous APIs
		if($project->project['status'] > 0)
		{
			$d_apis = APIPlayground::dangerousAPIs();

			foreach($a as $group => $opts)
			{
				foreach($opts as $k => $opt)
				{
					if(in_array($k, $d_apis)) unset($a[$group][$k]);
				}
			}
		}

		return $a;
	}

	public static function dangerousAPIs()
	{
		return array(
			'del_file',
			'del_arms',
			'del_events',
			'imp_records',
			'imp_file',
			'imp_events',
			'imp_arms',
			'imp_inst_event_maps',
			'imp_metadata',
			'imp_users',
			'del_records',
			'imp_repeating_forms_events'
		);
	}

	function __construct($token, $lang)
	{
		$this->token    = $token;
		$this->lang     = $lang;
		$this->fields   = array();
		$this->headers  = array();
		$this->file_ext = 'raw';
	}
}
