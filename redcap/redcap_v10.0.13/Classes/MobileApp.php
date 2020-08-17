<?php


/**
 * MOBILEAPP Class
 * Contains methods used with regard to the REDCap Mobile App
 */
class MobileApp
{

	// URLs for app stores to download app
	const URL_IOS_APP_STORE 	= "https://itunes.apple.com/us/app/redcap-mobile-app/id972760478";
	const URL_GOOGLE_PLAY_STORE = "https://play.google.com/store/apps/details?id=edu.vanderbilt.redcap";
	const URL_AMAZON_APP_STORE 	= "http://www.amazon.com/REDCap-at-Vanderbilt-Mobile-App/dp/B015TK6K36/ref=sr_1_1?s=mobile-apps&ie=UTF8&qid=1443280882&sr=1-1&keywords=redcap";
	const URL_GOOGLE_MAPS   	= "https://www.google.com/maps/place/";

	// Return URL for Google Maps
	public static function getMapURL($lat, $long)
	{
		return self::URL_GOOGLE_MAPS . $long . "," . $lat;
	}

	// Return true if user has had at least one logged activity for the app (e.g. initialized the project in the app)
	public static function userHasInitializedProjectInApp($username, $project_id)
	{
		$sql = "select 1 from redcap_mobile_app_log l, ".Logging::getLogEventTable($project_id)." e
				where l.project_id = $project_id and l.project_id = e.project_id and e.log_event_id = l.log_event_id
				and e.user = '".db_escape($username)."' limit 1";
		$q = db_query($sql);
		return (db_num_rows($q) == '1');
	}


	public static function getAppDeviceDashboards($project_id)
        {
                // 'INIT_PROJECT','INIT_DOWNLOAD_DATA','INIT_DOWNLOAD_DATA_PARTIAL','REINIT_PROJECT','REINIT_DOWNLOAD_DATA','REINIT_DOWNLOAD_DATA_PARTIAL','SYNC_DATA'
                $order = "DESC";
                $sql0 = "SELECT uuid, nickname, device_id, revoked FROM redcap_mobile_app_devices WHERE (project_id = $project_id)";
                $sql1 = "SELECT d.uuid AS uuid, d.nickname AS nickname, e.ts AS ts, l.event AS event, l.details AS details,
						l.latitude AS latitude, l.longitude AS longitude
						FROM ".Logging::getLogEventTable($project_id)." e, redcap_mobile_app_log l
						left join redcap_mobile_app_devices d on l.project_id = d.project_id AND d.device_id = l.device_id
						WHERE (l.project_id = e.project_id) AND (l.log_event_id = e.log_event_id)
						AND (l.event = 'SYNC_DATA') AND (l.project_id = $project_id) ORDER BY e.ts ".$order;
                $sql2 = "SELECT d.uuid AS uuid, d.nickname AS nickname, e.ts AS ts, l.event AS event, l.details AS details
						FROM ".Logging::getLogEventTable($project_id)." e, redcap_mobile_app_log l
						left join redcap_mobile_app_devices d on l.project_id = d.project_id AND d.device_id = l.device_id
						WHERE (l.project_id = e.project_id) AND (l.log_event_id = e.log_event_id)
						AND ((l.event = 'REINIT_PROJECT') OR (l.event = 'INIT_PROJECT') OR (l.event = 'INIT_DOWNLOAD_DATA')
							OR (l.event = 'INIT_DOWNLOAD_DATA_PARTIAL') OR (l.event = 'REINIT_DOWNLOAD_DATA') OR (l.event = 'REINIT_DOWNLOAD_DATA_PARTIAL'))
						AND (l.project_id = $project_id) ORDER BY e.ts ".$order;

                $rv = array();
                $rv['devices'] = array();
                $q0 = db_query($sql0);
                while ($row = db_fetch_assoc($q0)) {
                        if ($row['uuid']) {
                                $rv['devices'][] = $row;
                        }
                }

                $q1 = db_query($sql1);
                $prev_idxes = array();   // for SYNC
                $idx = 0;
                while ($row = db_fetch_assoc($q1)) {
                        $uuid = $row['uuid'];
                        if (!isset($rv[$row['uuid']])) {
                                $rv[$uuid] = array();
                                $rv[$uuid]['nickname'] = $row['nickname'] ? $row['nickname'] : "";
                                $rv[$uuid]['sync'] = array();
                                $rv[$uuid]['refresh'] = array();
                        }
                        if (count($rv[$uuid]['sync']) < 5) {
                                if ((isset($prev_idxes[$uuid])) && ($prev_idxes[$uuid]['event'] != 'SYNC_DATA')) {
                                        $rv[$uuid]['sync'][] = array( "ts" => $row['ts'], "latitude" => $row['latitude'], "longitude" => $row['longitude'], "steps" => 1, "records" => array($row['details']));
                                        // each step is one record for now; could change
                                }
                                else if (isset($prev_idxes[$uuid]))  // previous idx SYNC; therefore, increment the number of records on the previous row.
                                {
                                        // breaks at 3 minutes of inactivity
                                        if ($rv[$uuid]['sync'][count($rv[$uuid]['sync']) - 1]['ts'] - 180 < $row['ts'])
                                        {
                                                $rv[$uuid]['sync'][count($rv[$uuid]['sync']) - 1]['steps']++;
                                                $rv[$uuid]['sync'][count($rv[$uuid]['sync']) - 1]['ts'] = $row['ts'];
                                                if (!in_array($rv[$uuid]['sync'][count($rv[$uuid]['sync']) - 1]['records'], $row['details'])) {
                                                        array_push($rv[$uuid]['sync'][count($rv[$uuid]['sync']) - 1]['records'], $row['details']);
                                                }
                                        }
                                        else
                                        {
                                                $rv[$uuid]['sync'][] = array( "ts" => $row['ts'], "latitude" => $row['latitude'], "longitude" => $row['longitude'], "steps" => 1, "records" => array($row['details']));
                                        }
                                }
                                else  // no prev_idxes for uuid
                                {
                                        $rv[$uuid]['sync'][] = array( "ts" => $row['ts'], "latitude" => $row['latitude'], "longitude" => $row['longitude'], "steps" => 1, "records" => array($row['details']));
                                }
                                $prev_idxes[$uuid] = array( "idx" => $idx, "event" => $row['event'], "ts" => $row['ts'] );
                        }
                        $idx++;
                }

                $q2 = db_query($sql2);
                $prev_idxes = array();   // for REFRESH
                $idx = 0;
                $projects = array('INIT_PROJECT','REINIT_PROJECT');
                $downloads = array('INIT_DOWNLOAD_DATA','INIT_DOWNLOAD_DATA_PARTIAL','REINIT_DOWNLOAD_DATA','REINIT_DOWNLOAD_DATA_PARTIAL');
                $partial_downloads = array('INIT_DOWNLOAD_DATA_PARTIAL','REINIT_DOWNLOAD_DATA_PARTIAL');
                while ($row = db_fetch_assoc($q2)) {
                        $uuid = $row['uuid'];
                        if (!isset($rv[$row['uuid']])) {
                                $rv[$uuid] = array();
                                $rv[$uuid]['nickname'] = $row['nickname'] ? $row['nickname'] : "";
                                $rv[$uuid]['sync'] = array();
                                $rv[$uuid]['refresh'] = array();
                        }
                        if (($row['event'] == "INIT_PROJECT") || ($row['event'] == "REINIT_PROJECT")) {
                                if (count($rv[$uuid]['refresh']) == 0) {
                                        // print "<br>NONE";
                                        $rv[$uuid]['refresh'][] = array( "ts" => $row['ts'], "type" => "NONE");
                                }
                        }
                        else { // data
                                // print "<br>prev: ".$prev_idxes[$uuid]['event']." curr: ".$row['event'];
                                if (isset($prev_idxes[$uuid])) {
                                        if (in_array($prev_idxes[$uuid]['event'], $projects)) {   // previous log-event
                                                if (in_array($row['event'], $downloads)) { // current log-event
                                                        // if (in_array($row['event'], $partial_downloads)) { // current log-event
                                                                // $rv[$uuid]['refresh'][0]['type'] = "PARTIAL";
                                                        // }
                                                        // else {
                                                                $rv[$uuid]['refresh'][0]['type'] = "DATA";
                                                        // }
                                                }
                                        }
                                }
                                // else makes no sense to have a data download before an init
                        }
                        $prev_idxes[$uuid] = array( "idx" => $idx, "event" => $row['event'] );
                        $idx++;
                }
                return $rv;
        }

	public static function getAppDevices($project_id)
        {
                $sql = "SELECT d.uuid AS uuid, d.nickname AS nickname, e.ts AS ts
						FROM ".Logging::getLogEventTable($project_id)." e, redcap_mobile_app_log l
						left join redcap_mobile_app_devices d on l.project_id = d.project_id AND d.device_id = l.device_id
						WHERE AND (l.project_id = e.project_id) AND (l.log_event_id = e.log_event_id)
						AND (l.project_id = $project_id)";
                $q = db_query($sql);
                $rows1 = array();
                $max_ts = array();
                while ($row = db_fetch_assoc($q)) {
                        $rows1[] = $row;
                        if (isset($max_ts[$row['uuid']])) {
                                if ($max_ts[$row['uuid']] > $row['ts']) {
                                        $max_ts[$row['uuid']] = $row['ts'];
                                }
                        }
                        else {
                                $max_ts[$row['uuid']] = $row['ts'];
                        }
                }

                $rows2 = array();
                $uuids = array();
                foreach ($rows1 as $row) {
                        if (($max_ts[$row['uuid']] == $row['ts']) && (!in_array($row['uuid'], $uuids))) {
                                $uuids[] = $row['uuid'];
                                $rows2[] = $row;
                        }
                }
                return $rows2;
        }

	// Return array of all logged app activity for a given project
	public static function getAppActivity($project_id)
	{
		$rows = array();
		$sql = "select e.ts, l.event, l.details, e.user, e.event as event_type, d.nickname, d.uuid
				from ".Logging::getLogEventTable($project_id)." e, redcap_mobile_app_log l
				left join redcap_mobile_app_devices d on l.project_id = d.project_id AND d.device_id = l.device_id
				where l.project_id = $project_id and e.log_event_id = l.log_event_id
				order by l.mal_id desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$rows[] = $row;
		}
		return $rows;
	}

	// Return an array of all data dumps, or if $logs_override is true, logs
	public static function getAppFiles($project_id, $doc_id=null, $logs_override = false)
	{
		$subsql = (is_numeric($doc_id)) ? "and a.doc_id = $doc_id" : "";
		$sql = "select a.*, e.doc_name, e.stored_name, e.stored_date, e.doc_size, d.uuid
				from redcap_edocs_metadata e, redcap_mobile_app_files a
				left join redcap_mobile_app_devices d on d.project_id = $project_id AND d.device_id = a.device_id
				where e.project_id = $project_id and e.doc_id = a.doc_id
				and e.delete_date is null $subsql order by e.stored_date desc";
		$q = db_query($sql);
		$files = array();
		while ($row = db_fetch_assoc($q)) {
			$uuid = $row['uuid'];
			if (!isset($files[$uuid]))
			{
					$files[$uuid] = array();
			}
			if (!$logs_override)
			{
				if ($row['type'] == 'ESCAPE_HATCH')
				{
					if (is_numeric($doc_id)) {
						return $row;
					}
					$files[$uuid][$row['af_id']] = $row;
				}
			}
			else
			{
				if ($row['type'] == 'LOGGING')
				{
					if (is_numeric($doc_id)) {
						return $row;
					}
					$files[$uuid][$row['af_id']] = $row;
				}
			}
		}
		// Return ALL files
		return $files;
	}

	// Return array of all files associated with mobile app for a project (escape hatch, logging, etc.)
	public static function getAppArchiveFiles($project_id, $doc_id=null)
	{
		// Get list of files
		$files = array();
		$subsql = (is_numeric($doc_id)) ? "and a.doc_id = $doc_id" : "";
		$sql = "select a.*, e.doc_name, e.stored_date, e.doc_size, i.username, concat(i.user_firstname, ' ', i.user_lastname) as name
				from redcap_edocs_metadata e, redcap_mobile_app_files a
				left join redcap_user_information i on a.user_id = i.ui_id
				where e.project_id = $project_id and e.doc_id = a.doc_id and e.delete_date is null $subsql
				order by e.stored_date desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if (is_numeric($doc_id)) {
				return $row;
			}
			$files[$row['af_id']] = $row;
		}
		// Return ALL files
		return $files;
	}


        // return tables with most recent log files for each device
	public static function displayAppLogTables($project_id)
    {
		global $lang, $edoc_storage_option;

		$allfiles = self::getAppFiles($project_id, null, true);  // logs only
		$tables = array();
		$width = 706;
		foreach ($allfiles as $uuid => $files)
		{
			$id = "logtable_".$uuid;
			$row_data = array();
			$most_recent = "";
			$most_recent_id = "";
			foreach ($files as $file => $row) {
					if ($most_recent == "") {
							$most_recent = $row['stored_date'];
							$most_recent_id = $row['doc_id'];
					} else if ($most_recent < $row['stored_date']) {
							$most_recent = $row['stored_date'];
							$most_recent_id = $row['doc_id'];
					}
			}
			$title = self::makeTableHeader(self::getNickname($uuid, $project_id), $uuid, $id, $project_id, $most_recent_id);
			$display = false;
			foreach ($files as $file => $row) {
				if ($most_recent == $row['stored_date'])
				{
					if ($edoc_storage_option == '0' || $edoc_storage_option == '3') {
						// File is already local, so get the patch
						$localFilePath = EDOC_PATH . $row['stored_name'];
						$deleteLocalFile = false;
					} else {
						$localFilePath = Files::copyEdocToTemp($row['doc_id'], true);
						$deleteLocalFile = true;
					}
					$fh = fopen($localFilePath, "r");
					$i = 0;
					while ($csvrow = fgetcsv($fh)) {
						if ($i > 0) {
							$a = array();
							if ((count($csvrow) > 0) && ($a[0] !== "")) {
								$a[] = RCView::div(array('class'=>'wrap'), DateTimeRC::format_ts_from_ymd($csvrow[0]));
							}
							for ($j = 1; $j < count($csvrow); $j++) {
								if ($j == 4)
								{
									$newcsvrow = str_replace("',","'".RCView::br(), $csvrow[$j]);
									$a[] = RCView::div(array('class'=>'wrap'), $newcsvrow);
								}
								else if ($j == 3)
								{
									$newcsvrow = str_replace(" (___EVENT___)","", $csvrow[$j]);
									$a[] = RCView::div(array('class'=>'wrap'), $newcsvrow);
								}
								else
								{
									$a[] = RCView::div(array('class'=>'wrap'), $csvrow[$j]);
								}
							}
							for ($j = count($csvrow); $j < 5; $j++) {
								$a[] = "";
							}
							$row_data[] = $a;
						}
						$i++;
					}
					$display = true;
					fclose($fh);
					// Delete file if a temp file
					if ($deleteLocalFile) unlink($localFilePath);
				}
			}
			if (!$display) {
				$headers = array();
				$row_data[] = array($lang['mobile_app_84']);
				$show_headers = false;
			} else {
				$headers = array(
					array(100, $lang['mobile_app_69'], 'center'),
					array(70, $lang['mobile_app_85'], 'center'),
					array(50, $lang['mobile_app_86'], 'center'),
					array(210, $lang['mobile_app_87']),
					array(220, $lang['mobile_app_88'])
				);
			   $show_headers = true;
			}
			$tables[] = renderGrid($id, $title, $width, 'auto', $headers, $row_data, $show_headers, false, false, true) .
						RCView::div(array('class'=>'space', 'style'=>'margin:15px 0;'), '');
		}
		$title = RCView::div(array('style'=>'color:#800000;font-size:18px;font-weight:bold;padding:5px;'), $lang['mobile_app_89']);
		if (count($tables) === 0)
		{
			$tables[] = RCView::div(array('style'=>'color:#800000;font-size:13px;padding:5px;'), $lang['mobile_app_79']);
		}
		return $title . implode('', $tables);
	}

    // return tables with all data dump files for each device
		public static function displayAppDataDumpTables($project_id) {
	  global $lang, $user_rights, $edoc_storage_option;
	  $allfiles = self::getAppFiles($project_id); // data dump only
	  $tables = array();
	  $width = 786;
	  foreach($allfiles as $uuid => $files) {
	    $id = "eddtable_".$uuid;
	    $title = self::makeTableHeader(self::getNickname($uuid, $project_id), $uuid, $id, $project_id);
	    $row_data = array();
	    foreach($files as $file => $row) {
	      if(preg_match("/\.data\.csv$/", $row['doc_name'])) {
	        $subfiles = array();
	        $screen = str_replace(".data.csv", "", $row['doc_name']);
	        $abbrev = false;
	        foreach($files as $subfile => $subrow) {
	          if(!preg_match("/\.data\.csv$/", $subrow['doc_name']) && (strpos($subrow['doc_name'], $screen) !== false)) {
	            if(!preg_match("/^[^\.]+\.[^\.]+\.\d+\./", $subrow['doc_name'])) {
	              preg_match("/^[^\.]+\.[^\.]+\.[^\.]+\./", $subrow['doc_name'], $start);
	            } else {
	              preg_match("/^[^\.]+\.[^\.]+\./", $subrow['doc_name'], $start);
	            }
	            preg_match("/\.[^\.]+$/", $subrow['doc_name'], $end);
	            $url = APP_PATH_WEBROOT.
	            "DataEntry/file_download.php?pid=$project_id&doc_id_hash=".Files::docIdHash($subrow['doc_id']).
	            "&id=".$subrow['doc_id'];
	            if($abbrev) {
	              $subfiles[] = "<a style='font-size: 11px;' href='$url'>".$start[0].
	              " ... ".$end[0].
	              "</a>";
	            } else {
	              $subfiles[] = "<a style='font-size: 11px;' href='$url'>".$subrow['doc_name'].
	              "</a>";
	            }
	          }
	        }
	        // aggregate record names from CSV
	        $records = array();
	        if($edoc_storage_option == '0' || $edoc_storage_option == '3') {
	          // File is already local, so get the patch
	          $localFilePath = EDOC_PATH.$row['stored_name'];
	          $deleteLocalFile = false;
	        } else {
	          $localFilePath = Files::copyEdocToTemp($row['doc_id'], true);
	          $deleteLocalFile = true;
	        }
	        $fh = fopen($localFilePath, "r");
	        $i = 0;
	        while($csvrow = fgetcsv($fh)) {
	          if(!in_array($csvrow[0], $records) && ($i > 0)) {
	            $records[] = $csvrow[0];
	          }
	          $i++;
	        }
	        fclose($fh);
	        // Delete file if a temp file
	        if($deleteLocalFile) unlink($localFilePath);
	        //ABM
	        $rid = $file.
	        "-records"; //mt_rand(0,10000);
	        $record_str = "<button data-target='#$rid' class='btn btn-xs btn-primaryrc' data-toggle='collapse' onclick='$(this.dataset.target).collapse(\"toggle\")'> ".count($records).
	        " Records</button><div id='$rid' class='collapse'>".implode(RCView::br(), $records).
	        "</div>";
	        //                  $record_str = implode(", ", $records);
	        //             if (strlen($record_str) > 20) {
	        //                $record_str = RCView::div(array('id'=>'rlist_'.$row['doc_id']), $record_str) .
	        //                           RCView::a(array('href'=>'javascript:;', 'style'=>'color:#C00000;font-size:11px;margin-top: 5px;display: block;text-align: center;', 'onclick'=>"simpleDialog($('#rlist_{$row['doc_id']}').html(),'".cleanHtml($lang['mobile_app_125'])."');"), $lang['mobile_app_124']);
	        //             } else {
	        //                $record_str = RCView::div(array('id'=>'rlist_'.$row['doc_id']), $record_str);
	        //             }
	        $url = APP_PATH_WEBROOT.
	        "DataEntry/file_download.php?pid=$project_id&doc_id_hash=".Files::docIdHash($row['doc_id']).
	        "&id=".$row['doc_id'];
	        $import_url = APP_PATH_WEBROOT.
	        "index.php";
	        $import_data = "";
	        if($user_rights['data_import_tool'] == 1) {
	          $gets = array("pid" => $project_id,
	            "route" => "DataImportController:index",
	            "doc_id" => $row['doc_id'],
	            "doc_id_hash" => Files::docIdHash($row['doc_id']),
	            "format" => "rows",
	            "date_format" => "MDY",
	            "overwriteBehavior" => "normal",
	            "submit" => "1"
	          );
	          $gets2 = array();
	          foreach($gets as $key => $val) {
	            $gets2[] = RCView::escape($key.
	              "=".$val);
	          }
	          $import_data = RCView::button(array('class' => 'btn btn-defaultrc btn-xs', 'style' => 'margin:3px; 0;font-size:11px;', "onclick" => "simpleDialog('".cleanHtml($lang['mobile_app_112']).
	            "','".cleanHtml($lang['global_72']).
	            "', null, 600, null, '".cleanHtml($lang['global_53']).
	            "', function() { window.location.replace(\"".$import_url.
	            "?".implode("&", $gets2).
	            "\"); }, '".cleanHtml($lang['global_72']).
	            "');"), $lang['mobile_app_76']).RCView::br();
	        }
	        //ABM
	        $rid = $file.
	        "-subfiles"; //(0,10000);
	        $subfiles_str = "<button data-target='#$rid' class='btn btn-xs btn-primaryrc' data-toggle='collapse' onclick='$(this.dataset.target).collapse(\"toggle\")'> ".count($subfiles).
	        " Files </button><div id='$rid' class='collapse'>".implode(RCView::br(), $subfiles).
	        "</div>";
	        $row_data[] = array(
	          RCView::div(array('class' => 'wrap'), DateTimeRC::format_ts_from_ymd($row['stored_date'])),
	          RCView::div(array('class' => 'wrap'), $row['doc_name']).$import_data.RCView::button(array('class' => 'btn btn-primaryrc btn-xs', 'style' => 'font-size:11px;', 'onclick' => "window.open('$url','_blank');"), $lang['mobile_app_101']),
	          $record_str,
	          //                                    (empty($subfiles) ? $lang['mobile_app_77'] : implode(RCView::br(), $subfiles))
	          (empty($subfiles) ? $lang['mobile_app_77'] : $subfiles_str)
	        );
	      }
	    }
	    if(count($row_data) === 0) {
	      $headers = array();
	      $row_data[] = array($lang['mobile_app_83']);
	      $show_headers = false;
	    } else {
	      $headers = array(
	        array(70, $lang['mobile_app_69'], 'center'),
	        array(150, $lang['mobile_app_80'], 'center'),
	        array(100, $lang['mobile_app_81'], 'center'),
	        array(420, $lang['mobile_app_82'])
	      );
	      $show_headers = true;
	    }
	    $tables[] = renderGrid($id, $title, $width, 'auto', $headers, $row_data, $show_headers, false, false, true).
	    RCView::div(array('class' => 'space', 'style' => 'margin:15px 0;'), '');
	  }
	  $title = RCView::div(array('style' => 'color:#800000;font-size:18px;font-weight:bold;padding:5px;'), $lang['mobile_app_78']);
	  if(count($tables) === 0) {
	    $tables[] = RCView::div(array('style' => 'color:#800000;font-size:13px;padding:5px;'), $lang['mobile_app_79']);
	  }
	  return $title.implode('', $tables);
	}

	// Return html table all app-related files in app file archive for a given project
	public static function displayAppFileArchiveTable($project_id)
	{
		global $lang;
		// Get list of all app-related files
		$appFiles = self::getAppArchiveFiles($project_id);
		// Loop through files
		$row_data = array();
		foreach ($appFiles as $attr) {
			$filesize_kb = 	round(($attr['doc_size'])/1024,2);
			$description = 	RCView::div(array('style'=>'font-size:12px;font-weight:bold;'),
								$attr['doc_name']
							) .
							RCView::div(array('style'=>'margin-top:3px;color:#777;'),
								$lang['docs_56'] . " " .
								RCView::span(array('style'=>'color:#800000;'),
									DateTimeRC::format_ts_from_ymd($attr['stored_date'])
								)
							) .
							($attr['username'] == '' ? '' :
								RCView::div(array('style'=>'color:#777;'),
									$lang['mobile_app_15'] . " " .
									RCView::span(array('style'=>'color:#800000;'),
										$attr['username'] . " " .
										$lang['leftparen'] . $attr['name'] . $lang['rightparen']
									)
								)
							) .
							RCView::div(array('style'=>'color:#777;'),
								"{$lang['docs_57']} $filesize_kb KB"
							);
			if (strtolower(substr($attr['doc_name'], -4)) != '.csv') {
				// File upload files and signature files
				$download_icon = 'download_file.gif';
				$type_name = $lang['mobile_app_19'];
			} elseif ($attr['type'] == 'LOGGING') {
				// Logging file
				$download_icon = 'download_file_log.gif';
				$type_name = $lang['mobile_app_18'];
			} else {
				// Data file
				$download_icon = 'download_csvexcel_raw.gif';
				$type_name = $lang['mobile_app_19'];
			}
			$row_data[] = array($description,
								RCView::div(array('class'=>'wrap', 'style'=>'color:#666;'), $type_name),
								RCView::div(array('style'=>'margin:5px 0;background:red;'),
									RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/file_download.php?pid=$project_id&doc_id_hash=".Files::docIdHash($attr['doc_id'])."&id=".$attr['doc_id'], 'target'=>'_blank'),
										RCView::img(array('src'=>$download_icon))
									)
								)
						  );
		}
		// Display note if table is empty
		if (empty($row_data)) {
			$row_data[] = array(RCView::div(array('style'=>'margin:10px 0;color:#888;'), $lang['docs_42']), '', '');
		}
		// Render the file list as a table
		$width = 606;
		// Set table headers and attributes
		$headers = array(
			array(420, $lang['mobile_app_16']),
			array(90, $lang['mobile_app_17'], 'center'),
			array(60, $lang['design_121'], 'center')
		);
		// Title
		$title = RCView::div(array('style'=>'color:#800000;font-size:18px;font-weight:bold;padding:5px;'), $lang['mobile_app_14']);
		// Build table html
		return renderGrid("mobile_app_file_list", $title, $width, 'auto', $headers, $row_data, true, false, false);
	}


	// Obtain count of current users in a project that have init'd the project in the Mobile App AND who still have Mobile App privileges
	public static function countUsersInitProject($project_id)
	{
		global $mobile_app_enabled;
		if (!$mobile_app_enabled) return 0;
		$sql = "select count(distinct(e.user)) from redcap_mobile_app_log a, ".Logging::getLogEventTable($project_id)." e, redcap_user_rights r
				where a.event = 'INIT_PROJECT' and a.log_event_id = e.log_event_id and r.username = e.user
				and r.mobile_app = 1 and r.project_id = a.project_id and a.project_id = $project_id";
		$q = db_query($sql);
		return db_result($q, 0);
	}

	public static function getNickname($uuid, $project_id) {
		$sql = "SELECT nickname FROM redcap_mobile_app_devices WHERE (project_id = ".$project_id.") AND (uuid = '".db_escape($uuid)."')";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($row['nickname'] !== NULL) {
				return $row['nickname'];
			} else {
				return "";
			}
		}
		return  "";
	}

	public static function makeTableHeader($nickname, $uuid, $id, $project_id, $doc_id = null) {
		global $lang;

		$link = "";
		if ($doc_id && ($doc_id !== ""))
		{
			$url = APP_PATH_WEBROOT."DataEntry/file_download.php?pid=$project_id&doc_id_hash=".Files::docIdHash($doc_id)."&id=".$doc_id;
			$link = RCView::button(array('class'=>'btn btn-defaultrc btn-xs', 'onclick'=>"window.open('$url','_blank');"),
						RCView::span(array('style'=>'vertical-align:middle', 'class'=>'fas fa-download'), '') .
						RCView::span(array('style'=>'vertical-align:middle;margin-left:4px;'), $lang['mobile_app_128'])
					);
		}
		$spac = "&nbsp;&nbsp;&nbsp;";
		if ($uuid == "") $uuid = $lang['mobile_app_127'];
		if ($nickname != "" && $uuid != "") $uuid = "($uuid)";
		$title = RCView::div(array('style'=>'font-size: 13px; font-weight: bold; color:#800000;', 'id'=>"nickname-$id"),
					$link . $nickname . $spac . $uuid . $spac .
					RCView::div(array('style'=>'float:right;padding:3px 10px 5px;'),
						RCView::a(array("href"=>'javascript;', 'style'=>'font-weight:normal;font-size:12px;', 'onclick'=>"$(this).parent().remove();$('#$id .hDiv table, #$id .bDiv table').show('fade');return false;"), $lang['mobile_app_126'])
					)
				  );
		return $title;
	}

	public static function displayAppDashboardTables($project_id) {
		global $lang;
		// Get all entries for this project
                $refresh_translations = array( "NONE" => $lang['mobile_app_64'],
                                               "PARTIAL" => $lang['mobile_app_65'],
                                               "COMPLETE" => $lang['mobile_app_66'],
                                               "DATA" => $lang['mobile_app_121']
                                             );
                $tables = array();
		$width = 606;
                $dashboards = self::getAppDeviceDashboards($project_id);
                $combine_tables = false;
                if (count($dashboards) <= 11)
                {
                        $combine_tables = true;
                }
                if ($combine_tables)
                {
                        $row_data_combined = array();
                }
                $revoked_list = array();
		foreach ($dashboards as $uuid => $table) {
                        $row_data = array();
                        $block_w = 90;
                        if ($uuid == "devices")
                        {
                                $row_data = array();
                                $id = "devices";
		                $title = RCView::div(array('style'=>'color:#800000;font-size:13px;padding:5px;'), $lang['mobile_app_96']);
                                $w = "250px";
                                foreach ($table as $device)
                                {
                                        if ($device['nickname'] == "")
                                        {
                                                $display1 = "display: none";
                                                $display2 = "display: ";
                                        }
                                        else
                                        {
                                                $display1 = "display: ";
                                                $display2 = "display: none";
                                        }
                                        $revoke_val = 0;
                                        $revoke_text = "display: none";
                                        $revoke_text2 = "display: ";
                                        if (($device['revoked'] !== 0) && ($device['revoked'] !== "0") && ($device['revoked'] !== ""))
                                        {
                                                $revoked_list[] = $device['uuid'];
                                                $revoke_val = 1;
                                                $revoke_text = "display: ";
                                                $revoke_text2 = "display: none";
                                                $block_w = 95;
                                        }
                                        $revoke = "<input id=\"".$device['device_id']."_revoke\" type=\"hidden\" value=\"".$revoke_val."\">" .
                                                "<div id=\"".$device['device_id']."_blocked\" style=\"color: red; text-align: center; $revoke_text; width: ".$block_w."px;\">".$lang['mobile_app_109']."</div>" .
                                                "<div id=\"".$device['device_id']."_unblocked\" style=\"color: black; text-align: center; $revoke_text2; width: ".$block_w."px;\">".$lang['mobile_app_114']."</div>" .
                                                "<div style='text-align: center; margin-top: 2px; width: ".$block_w."px;'><button class='btn btn-defaultrc btn-xs' style='font-size:11px;' id='".$device['device_id']."_revokelink' " .
                                                        " onclick='if ($(\"#".$device['device_id']."_revoke\").val() !== \"0\") { " .
                                                                "revokeDevice(false, \"".$device["uuid"]."\", ".$device['device_id'].", \"{$lang['mobile_app_119']}\", \"".RCView::escape($lang['mobile_app_106'])."\", \"".RCView::escape($lang['mobile_app_107'])."\"); " .
                                                        "} else { " .
                                                                "revokeDevice(true, \"".$device["uuid"]."\", ".$device['device_id'].", \"{$lang['mobile_app_120']}\", \"".RCView::escape($lang['mobile_app_107'])."\", \"".RCView::escape($lang['mobile_app_106'])."\"); " .
                                                        "}'>" .
                                                        (($revoke_val === 0) ? $lang['mobile_app_106'] : $lang['mobile_app_107']) .
                                                "</button>";

                                        $border = "border: 0;";
                                        $row_data[] = array(RCView::div(array('class'=>'wrap'), $device['uuid']),
                                                        "<table>" .
                                                        "<tr id='".$device['device_id']."_active' style='$border$display2;'><td style='width: ".$w.";$border'><input type='text' placeholder='".$lang['mobile_app_102']."' id='".$device['device_id']."_nickname' style='$display2; width: 100%;' value=\"".$device['nickname']."\">" .
                                                        "<td style='$border'>&nbsp;&nbsp;</td>" .
                                                        "<td style='$border'><button class='btn btn-primaryrc btn-xs' id='".$device['device_id']."_button' style='$display2;' onclick='changeDeviceNickname(".$device['device_id'].", $(\"#".$device['device_id']."_nickname\").val(), \"".$device['uuid']."\");'>".$lang['mobile_app_100']."</button></td></tr>",
                                                        "<tr id='".$device['device_id']."_namerow' style='$border$display1;'><td id='".$device['device_id']."_displayname' style='width: ".$w.";$border'>".RCView::escape($device['nickname'])."</td>" .
                                                        "<td style='$border'>&nbsp;&nbsp;</td>" .
                                                        "<td style='$border'><button class='btn btn-defaultrc btn-xs' style='$display1;' onclick='editDeviceNickname(\"".$device['device_id']."\");' id='".$device['device_id']."_edit'>".$lang['mobile_app_103']."</button></td></tr>" .
                                                        "</table>",
                                                $revoke);
                                }
                                if (count($row_data) === 0) {
                                        $headers = array();
				        $row_data[] = array($lang['mobile_app_97']);
                                        $show_headers = false;
                                }
                                else {
				        $headers = array(
                                                          array(150, $lang['mobile_app_98'], 'center'),
                                                          array(330, $lang['mobile_app_99']),
                                                          array($block_w, $lang['mobile_app_108'], 'center')
                                                        );
                                        $show_headers = true;
                                }
		                $tables[] = renderGrid($id, $title, $width, 'auto', $headers, $row_data, $show_headers, true, false, false);
                                if ((count($dashboards) > 1) && (!$combine_tables))
                                {
                                        $tables[] = RCView::div(array("style"=>"max-width: ".$width."px;"),
                                                RCView::table(array("style"=>"border: 0; margin: 0px auto; width: 100%;"),
                                                        RCView::tr(array("style"=>"border: 0;"),
                                                                RCView::td(array("style"=>"border: 0; width: 45%; text-align: center;"),
                                                                        RCView::a(array("href"=>"javascript:;", "onclick"=>"showDeviceTables();"), $lang['mobile_app_110'] )
                                                                ) .
                                                                RCView::td(array("style"=>"border: 0; width: 10%;"),
                                                                        "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
                                                                ) .
                                                                RCView::td(array("style"=>"border: 0; width: 45%; text-align: center;"),
                                                                        RCView::a(array("href"=>"javascript:;", "onclick"=>"hideDeviceTables();"), $lang['mobile_app_111'] )
                                                                )
                                                        )
                                                )
                                        );
                                }
                        }
                        else
                        {
                                $id = "table_".$uuid;
                                $title = self::makeTableHeader($table['nickname'] ? $table['nickname'] : "", $uuid, $id, $project_id);
                                foreach ($table['refresh'] as $row) {
                                        $row_data[] = array( $lang['mobile_app_62'],
                                                             DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts'])),
                                                             $refresh_translations[$row['type']]
                                                           );
                                        $devices[] = $row['device_id'];
                                }
                                $i = 1;
                                foreach ($table['sync'] as $row) {
                                        if ($row['steps'] == 1)
                                        {
                                                $stmt = $lang['mobile_app_71']." ".$row['steps']." ".$lang['mobile_app_118'];
                                        }
                                        else
                                        {
                                                $stmt = $lang['mobile_app_71']." ".$row['steps']." ".$lang['mobile_app_72'];
                                        }
                                        $loc = "";
                                        if ($row['latitude'] && $row['longitude'])
                                        {
                                                $loc = " (" . RCView::a(array("target"=>"_BLANK", "style"=>"font-size:11px;", "href"=>self::getMapURL($row['longitude'], $row['latitude'])), $lang['mobile_app_122']) . ")";
                                        }
                                        $row_data[] = array( $lang['mobile_app_63']." ".$i,
                                                             DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts'])),
                                                             $stmt . $loc
                                                           );
                                        $devices[] = $row['device_id'];
                                        $i++;
                                }
                                if (count($table['refresh']) + count($table['sync']) === 0) {
                                        $headers = array();
				        $row_data[] = array($lang['mobile_app_67']);
                                        $show_headers = false;
                                }
                                else {
				        $headers = array(
                                                          array(150, $lang['mobile_app_68'], 'center'),
                                                          array(150, $lang['mobile_app_69'], 'center'),
                                                          array(300, $lang['mobile_app_70'])
                                                        );
                                        $show_headers = true;
                                }
                                if ($combine_tables)
                                {
                                        $i = 0;
                                        foreach ($row_data as $row)
                                        {
                                                if (in_array($uuid, $revoked_list))
                                                {
                                                        $span = "<span class='wrap' id='row.$uuid.$i' style='color: red;'>";
                                                }
                                                else
                                                {
                                                        $span = "<span class='wrap' id='row.$uuid.$i'>";
                                                }
                                                $span .= ($table['nickname'] == "" ? $uuid : RCView::escape($table['nickname']) . " (".$uuid.")");
                                                $span .= "</span>";
                                                $row[] = $span;
                                                $row_data_combined[] = $row;
                                                $i++;
                                        }
                                }
                                else
                                {
									$tables[] = renderGrid($id, $title, $width, 'auto', $headers, $row_data, $show_headers, false, false, true);
                                }
                        }
                }
				$title = RCView::div(array('style'=>'color:#800000;font-size:13px;font-weight:bold;padding:5px;'), $lang['mobile_app_73']);
                if ($combine_tables)
                {
                    $id = "table_combined";
					$headers = array(
					  array(150, $lang['mobile_app_68'], 'center'),
					  array(150, $lang['mobile_app_69'], 'center'),
					  array(260, $lang['mobile_app_70']),
					  array(200, $lang['mobile_app_113'])
					);
					$tables[] = renderGrid($id, $title, 806, 'auto', $headers, $row_data_combined, true, false, false, false);
                }
                else
                {
                        if (count($tables) === 0)
                        {
							$tables[] = RCView::div(array('style'=>'color:#800000;font-size:13px;padding:5px;'), $lang['mobile_app_75']);
                        }
                }
                return implode(RCView::br().RCView::br(), $tables);
        }

	// Return html table all logged app activity for a given project
	public static function displayAppActivityTable($project_id)
	{
		global $lang;
		// Get all log entries for this project
		$row_data = array();
		foreach (self::getAppActivity($project_id) as $row) {
			// Convert event to text
			if ($row['event'] == 'INIT_PROJECT') {
				$thisevent = RCView::div(array('style'=>'color:green;'), $lang['mobile_app_25']);
			} elseif ($row['event'] == 'REINIT_PROJECT') {
				$thisevent = RCView::div(array('style'=>'color:green;'), $lang['mobile_app_26']);
			} elseif ($row['event'] == 'INIT_DOWNLOAD_DATA' || $row['event'] == 'REINIT_DOWNLOAD_DATA') {
				$thisevent = RCView::div(array('style'=>'color:#000066;'), $lang['mobile_app_27']);
			} elseif ($row['event'] == 'SYNC_DATA') {
				$detailsText = '';
				if (is_numeric($row['details'])) {
					$isNewRecord = ($row['event_type'] == 'INSERT');
					if ($row['details'] == '1') {
						$detailsText2 = ($isNewRecord ? $lang['mobile_app_32'] : $lang['mobile_app_30']);
					} else {
						$detailsText2 = ($isNewRecord ? $lang['mobile_app_31'] : $lang['mobile_app_29']);
					}
					$detailsText2 = " ({$row['details']} $detailsText2)";
				}
				$thisevent = RCView::div(array('style'=>'color:#800000;'),
								$lang['mobile_app_28'] . $detailsText2
							 );
			}
                        $device = "";
                        if ($row['nickname'] != "")
                        {
                                if ($row['uuid'] != "")
                                {
                                          $device = $row['nickname']." (".$row['uuid'].")";
                                }
                                else
                                {
                                          $device = $row['nickname'];
                                }
                        }
                        else
                        {
                                $device = $row['uuid'];
                        }
			// Add row to array
			$row_data[] = array(RCView::div(array('class'=>'wrap'), DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts']))),
								RCView::div(array('class'=>'wrap'), $row['user']),
								RCView::div(array('class'=>'wrap'), $thisevent),
                                RCView::div(array('class'=>'wrap'), $device)
						  );
		}
		// Display note if table is empty
		if (empty($row_data)) {
			$row_data[] = array(RCView::div(array('class'=>'wrap', 'style'=>'margin:10px 0;color:#888;'), $lang['mobile_app_23']), '', '', '');
		}
		// Render the file list as a table
		$width = 806;
		// Set table headers and attributes
		$headers = array(
			array(120, $lang['reporting_19'], 'center'),
			array(90, $lang['global_11'], 'center'),
			array(280, $lang['dashboard_21']),
            array(280, $lang['mobile_app_113'])
		);
		// Title
		$title = RCView::div(array('style'=>'color:#800000;font-size:13px;padding:5px;'), $lang['mobile_app_22']);
		// Build table html and return it
		return  renderGrid("mobile_app_log", $title, $width, 'auto', $headers, $row_data, true, false, false);
	}


	// Return html for app init page
	public static function displayInitPage()
	{
		global $lang, $api_token_request_type;
		// Get user's email address
		$userInfo = User::getUserInfo(USERID);
		// Video links
		print	RCView::div(array('style'=>'text-align:center; max-width: 800px;'),
                                RCView::span(array('style'=>'text-align:center;padding:6px; line-height:28px;border-radius: 8px; border:1px solid #EEEEEE;background-color:#EEEEEE;'),
					// VIDEO link
					RCView::span(array('class'=>'nowrap'),
                        '<i class="fas fa-film"></i> ' .
						RCView::a(array('href'=>'javascript:;', 'style'=>'margin-right:15px;font-weight:normal;text-decoration:underline;', 'onclick'=>"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=app_overview_01.mp4&referer=".SERVER_NAME."&title=REDCap Mobile App Overview','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');"),
							"{$lang['global_80']} {$lang['bottom_74']}"
						)
					) . " " .
					// VIDEO link
					RCView::span(array('class'=>'nowrap'),
                        '<i class="fas fa-film"></i> ' .
						RCView::a(array('href'=>'javascript:;', 'style'=>'margin-right:15px;font-weight:normal;text-decoration:underline;', 'onclick'=>"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=app_setup_01.mp4&referer=".SERVER_NAME."&title=Setting Up the Mobile App','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');"),
							"{$lang['global_80']} {$lang['bottom_82']}"
						)
					) . " " .
					// VIDEO link
					RCView::span(array('class'=>'nowrap'),
                        '<i class="fas fa-film"></i> ' .
						RCView::a(array('href'=>'javascript:;', 'style'=>'font-weight:normal;text-decoration:underline;', 'onclick'=>"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=app_install_01.mp4&referer=".SERVER_NAME."&title=Installing the Mobile App','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');"),
							"{$lang['global_80']} {$lang['bottom_75']}"
						)
					)
                                )
			) .
		        RCView::table(array('style'=>'border: 0; max-width:750px;'),
                                RCView::tr(array('style'=>'border: 0;'),
                                        RCView::td(array('style'=>'border: 0;'),
			                        RCView::div(array('class'=>'p', 'style'=>'font-weight:bold;font-size:14px;margin-top:15px;'),
				                        // First header
				                        $lang['rights_308']
			                        ) .
		                                RCView::div(array('class'=>'p', 'style'=>''),
				                        $lang['rights_310']
			                        ) .
			                        RCView::div(array('class'=>'p', 'style'=>''),
				                        $lang['rights_321']
                                                )
                                        ) .
                                        RCView::td(array('style'=>'padding-left: 10px; border: 0; text-align: right;'),
                                                RCView::img(array('src'=>APP_PATH_IMAGES.'app_logo.png', 'align'=>'right', 'style'=>'padding-left: 10px; width: 130px; height: 130px;'))
                                        )
                                )
                        );
		// Main instructions
	        $h =
			RCView::div(array('class'=>'p', 'style'=>'margin:15px 0 0 0;max-width:750px;'),
                                RCView::table(array('style' => 'margin-top:35px; margin-bottom:35px; border: 0;'),
                                        RCView::tr(array('style' => 'border: 0;'),
                                                RCView::td(array('style' => 'border: 0; vertical-align: top;'),
													RCView::div(array('style'=>'margin-bottom:10px;font-weight:bold;font-size:14px;'),
														$lang['survey_741'] . " " . $lang['mobile_app_42']
													) .
													$lang['mobile_app_43'] . " " .
													RCView::span(array('style'=>'color:#800000;'),
														$lang['mobile_app_51']
													)
                                                ) .
                                                RCView::td(array('style' => 'border: 0; width: 10px;'),
                                                    "&nbsp;"
                                                ) .
                                                RCView::td(array('style' => 'border: 0; padding-left: 10px;'),
                                                    RCView::div(array(),
														RCView::a(array('href'=>self::URL_IOS_APP_STORE, 'target'=>'_blank'),
															RCView::img(array('src' => 'app_store.png', 'style'=>'height:45px;', 'align'=>'left', 'alt'=>"Available on the App Store"))
                                                        )
													) .
													RCView::br().RCView::br().
													RCView::div(array('style'=>'margin-top: 18px;'),
														RCView::a(array('href'=>self::URL_GOOGLE_PLAY_STORE, 'target'=>'_blank'),
															RCView::img(array('src' => 'google_play_store.png', 'style'=>'height:45px;', 'alt'=>"Android app on Google Play"))
														)
													) .
													// Button to send app links via email
													RCView::div(array('style'=>'text-align:center;margin-top:20px;'),
														RCView::button(array('class'=>'btn btn-defaultrc btn-xs wrap', 'style'=>'max-width:150px;line-height: 12px;', 'onclick'=>"sendSingleEmail('".js_escape($userInfo['user_email'])."','".js_escape($userInfo['user_email'])."','".js_escape($lang['mobile_app_47'])."',trim($('#send_app_links_email_body').html()),true);"),
															RCView::img(array('src' => 'email.png', 'style'=>'vertical-align:middle;')) .
															RCView::span(array('style'=>'vertical-align:middle;'), $lang['mobile_app_46'])
														)
													) .
													// Hidden div used as email content when sending app links via email
													RCView::div(array('id'=>'send_app_links_email_body', 'style'=>'display:none;'),
														$lang['mobile_app_48'] . RCView::br() . RCView::br() .
														" - " . RCView::a(array('href'=>self::URL_IOS_APP_STORE, 'style'=>'text-decoration:underline;'),
															"iOS app on App Store"
														) .
														RCView::br() .
														" - " . RCView::a(array('href'=>self::URL_GOOGLE_PLAY_STORE, 'style'=>'text-decoration:underline;'),
															"Android app on Google Play"
														) .
														RCView::br() .
														" - " . RCView::a(array('href'=>self::URL_AMAZON_APP_STORE, 'style'=>'text-decoration:underline;'),
															"Amazon app for Android"
														)
													)
                                                )
                                        )
                                )
                        );

		// Check if user has an API token
		$db = new RedCapDB();
		if (strlen($db->getAPIToken(USERID, PROJECT_ID)) == 32) {
			// Add extra text if project contains CATs, which cannot be synced to the app
			$cat_list = PROMIS::getPromisInstruments();
			$cat_warning = "";
			if (!empty($cat_list)) {
				$cat_warning = RCView::span(array('style'=>'color:#800000;'),
								" " . RCView::b($lang['global_03'] . $lang['colon']) . " " . $lang['mobile_app_49'] . " "
								. count($cat_list) . " " . $lang['mobile_app_50']
							   );
			}
			// Display QR code and manual method
                        $h .= RCView::table(array('style' => 'max-width: 760px; margin-top:35px; margin-bottom:0px; border: 0;'),
                                        RCView::tr(array('style' => 'border: 0;'),
                                                RCView::td(array('style' => 'border: 0; vertical-align: top;'),
													RCView::div(array('class'=>'p', 'style'=>'margin-top:10px;font-weight:bold;font-size:14px;'),
																		$lang['survey_742'] . " " . $lang['mobile_app_44']
																) .
													RCView::p(array('style'=>'padding:0 0 5px;'),
																$lang['mobile_app_39'] . $cat_warning
													 ) .
													RCView::ol(array('style' => 'padding-left: 12px;'),
															RCView::li(array(), $lang['mobile_app_55']) .
															RCView::li(array(), $lang['mobile_app_56']) .
															RCView::li(array(), $lang['mobile_app_57']) .
															RCView::li(array(), $lang['mobile_app_58']) .
															RCView::li(array(), $lang['mobile_app_59'])
													) .
													(!SUPER_USER ? '' :
														RCView::p(array('class'=>'blue', 'style'=>'margin-top:40px;'),
															$lang['mobile_app_130']
														)
													)
                                                ) .
                                                RCView::td(array('style' => 'padding-left: 10px; vertical-align: top;'),
													RCView::div(array('style'=>'margin:0 0 0 75px; text-align: right;'),
														"<img align='right' style='height:190px; onload=\"$(this).css('height','auto');\" src='".APP_PATH_WEBROOT."API/project_api_ajax.php?pid=".PROJECT_ID."&action=getAppCode&qrcode=1'>"
													) .
													// Alternate Option: Get init code from Vanderbilt server
													RCView::div(array('style'=>'margin:15px 0 0 65px; padding-right: 10px;'),
														RCView::a(array('style'=>'text-decoration:underline;font-size:13px;', 'href'=>'javascript:;',
															'onclick'=>"getAppCode();$(this).hide();$('#appCodeAltDiv').show('fade');"),
															$lang['mobile_app_06']
														)
													)
                                                )
                                         ) .
                                         RCView::tr(array('style' => 'border: 0;'),
                                                RCView::td(array('colspan' => '2'),
					                RCView::div(array('id'=>'appCodeAltDiv', 'class'=>'p', 'style'=>'margin-top:20px;background-color:#f5f5f5;border:1px solid #ddd;padding:15px;display:none;'),
						                RCView::div(array('style'=>'font-size:13px;padding:0 0 6px;font-weight:bold;'),
							                $lang['mobile_app_07']
						                ) .
						                RCView::div(array('style'=>'padding:0 0 20px;'),
							                $lang['mobile_app_45'] . " " .
							                RCView::span(array('style'=>'color:#C00000;'),
								                $lang['mobile_app_09']
							                )
						                ) .
						                RCView::div(array('id'=>'app_user_codes_div', 'style'=>''),
							                RCView::span(array('style'=>'font-weight:bold;line-height:24px;'),
								                $lang['mobile_app_10'] . " "
							                ) .
							                RCView::text(array('id'=>'app_code', 'readonly'=>'readonly', 'class'=>'staticInput', 'onclick'=>"this.select();",
								                'style'=>'margin-left:10px;letter-spacing:1px;color:#111;padding:6px 8px;font-size:16px;width:130px;color:#C00000;',
								                'value'=>$lang['design_160']))
						                ) .
						                RCView::div(array('id'=>'app_user_codes_timer_div', 'class'=>'red', 'style'=>'display:none;'),
							                $lang['mobile_app_11']
						                )
					                )
                                                )
                                        )
                                ).
					// DELETE TOKEN (only display if user has at least initialized a project)
					(!MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID) ? '' :
						RCView::div(array('class'=>'p', 'style'=>'margin-top:20px;background-color:#f5f5f5;border:1px solid #ddd;padding:15px; width: 750px;'),
							RCView::div(array('style'=>'font-size:13px;padding:0 0 6px;font-weight:bold;'),
								$lang['mobile_app_34']
							) .
							RCView::div(array('style'=>''),
								$lang['mobile_app_35'] . " " . $lang['mobile_app_36']
							) .
							RCView::div(array('style'=>'margin-top:10px;'),
								RCView::button(array('class'=>'jqbuttonmed', 'style'=>'color:#800000;', 'onclick'=>"simpleDialog(null,null,'deleteTokenDialog',500,null,'".js_escape($lang['global_53'])."','deleteToken()','".js_escape($lang['mobile_app_34'])."');"), $lang['mobile_app_34'])
							)
						) .
						// Hidden delete dialog
						RCView::div(array('id'=>'deleteTokenDialog', 'title'=>$lang['edit_project_111'], 'class'=>'simpleDialog'),
							$lang['edit_project_112'].
							(!MobileApp::userHasInitializedProjectInApp(USERID, PROJECT_ID) ? '' :
								RCView::div(array('style'=>'margin-top:10px;font-weight:bold;color:#C00000;'), $lang['mobile_app_36']))
						)
					);
		} else {
			// User doesn't have API token yet. Tell them to get one.
			$userInfo = $db->getUserInfoByUsername(USERID);
			$ui_id = $userInfo->ui_id;
			$todo_type = 'token access';
			if(ToDoList::checkIfRequestExist(PROJECT_ID, $ui_id, $todo_type) > 0){
				$reqAPIBtn = RCView::button(array('class'=>'api-req-pending'),
					RCView::img(array('src'=>'phone.png', 'style'=>'vertical-align:middle;')).$lang['api_03']);
				$reqP = RCView::p(array('class' => 'api-req-pending-text'), $lang['edit_project_179']);
				$boxLanguage = '';
			}else{
				$requestAuto = ($api_token_request_type == 'auto_approve_all' || ($api_token_request_type == 'auto_approve_selected' && $userInfo->api_token_auto_request == '1')) ? 1 : 0;
				$reqAPIBtn = RCView::button(array('class'=>'jqbuttonmed', 'onclick'=>"requestToken($requestAuto);"),
					RCView::img(array('src'=>'phone.png', 'style'=>'vertical-align:middle;')) .
					RCView::span(array('style'=>'vertical-align:middle;'), ($requestAuto ? $lang['api_138'] : $lang['api_03']))
				);
				$boxLanguage = RCView::div(array('class'=>'mobile-token-alert-text'), $lang['mobile_app_41']);
				$reqP = '';
			}
			$h .= 	RCView::div(array('class'=>'yellow', 'style'=>''),
			RCView::img(array('src'=>'exclamation_orange.png')) .
			RCView::b($lang['mobile_app_40']) . RCView::br() . RCView::br() .
			$boxLanguage .
				RCView::div(array('style'=>'margin:15px 0 5px;'),
					$reqAPIBtn.$reqP
				)
			);

		}

		// Space for bottom of page
		$h .= 	RCView::div(array('class'=>'space', 'style'=>'margin:50px 0;'), '');

		/*
		// Accept validation code as REDCap App
		print	RCView::div(array('class'=>'darkgreen', 'style'=>'margin-top:5px;padding:10px;'),
					RCView::span(array('style'=>'font-weight:bold;color:#C00000;'),
						"[Simulated REDCap App Interface]"
					) .
					RCView::div(array('style'=>'margin:10px 0 2px;font-weight:bold;'),
						"Initialize project in app:"
					) .
					RCView::div(array('style'=>'margin:10px 0;'),
						"Initialization code: " . RCView::SP . RCView::SP . RCView::SP . RCView::SP .
						RCView::text(array('id'=>'app_validation_code', 'class'=>'x-form-text x-form-field', 'style'=>'width:150px;', $getAppCodeBtnDisabled=>$getAppCodeBtnDisabled)) .
						RCView::button(array('id'=>'app_validation_code_btn', 'class'=>'jqbuttonmed', $getAppCodeBtnDisabled=>$getAppCodeBtnDisabled, 'onclick'=>"validateAppCode($('#app_validation_code').val());"),
							"Validate"
						)
					) .
					RCView::div(array('style'=>'margin:10px 0;'),
						"REDCap URL: " .
						RCView::span(array('id'=>'app_redcap_url', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '') .
						RCView::br() .
						"API token: " .
						RCView::span(array('id'=>'app_redcap_token', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '') .
						RCView::br() .
						"Username: " .
						RCView::span(array('id'=>'app_redcap_username', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '') .
						RCView::br() .
						"Project ID: " .
						RCView::span(array('id'=>'app_redcap_project_id', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '') .
						RCView::br() .
						"Project Title: " .
						RCView::span(array('id'=>'app_redcap_project_title', 'style'=>'font-family:verdana;font-size:15px;color:#C00000;'), '')
					)
				);
		*/

		// Return html
		return $h;
	}

}
