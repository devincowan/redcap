<?php


/**
 * LOCKING
 */
class Locking
{
	// Array of record-event-fields that are locked in a project
	public $locked = array();
	public $lockedWhole = array();

	public function lockWholeRecord($project_id, $record, $arm=1)
	{
		$Proj = new Project($project_id);
		if ($record == '' || !isset($Proj->events[$arm]['id'])) return false;
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "insert into redcap_locking_records (project_id, record, arm_id, username, timestamp)
				values ($project_id, '" . db_escape($record) . "', $arm_id, '" . db_escape(USERID) . "', '".NOW."')";
		return db_query($sql);
	}

	public function unlockWholeRecord($project_id, $record, $arm=1)
	{
		$Proj = new Project($project_id);
		if ($record == '' || !isset($Proj->events[$arm]['id'])) return false;
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "delete from redcap_locking_records where project_id = $project_id and record = '" . db_escape($record) . "' and arm_id = $arm_id";
		return db_query($sql);
	}

	public function isWholeRecordLocked($project_id, $record, $arm=1)
	{
		$Proj = new Project($project_id);
		if ($record == '' || !isset($Proj->events[$arm]['id'])) return false;
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "select 1 from redcap_locking_records where project_id = $project_id and record = '" . db_escape($record) . "' and arm_id = $arm_id";
		$q = db_query($sql);
		return (db_num_rows($q) > 0);
	}

	public function getWholeRecordLockTimeUser($project_id, $record, $arm=1)
	{
		$Proj = new Project($project_id);
		if ($record == '' || !isset($Proj->events[$arm]['id'])) return false;
		$arm_id = $Proj->events[$arm]['id'];
		$sql = "select username, timestamp from redcap_locking_records where project_id = $project_id and record = '" . db_escape($record) . "' and arm_id = $arm_id";
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		return array($row['username'], $row['timestamp']);
	}

	// Get array of all records/arms that are locked in a project using record-level locking
	public function findLockedWholeRecord($project_id, $records=array(), $arms=array())
	{
		// Build SQL
		$Proj = new Project($project_id);
		if (!is_array($records)) $records = array($records);
		$sql_records = empty($records) ? "" : " and record in (".prep_implode($records).")";
		if (!is_array($arms)) $arms = array($arms);
		$sql_arms = "";
		if (!empty($arms)) {
			$arm_ids = array();
			foreach ($arms as $this_arm) {
				if (!is_numeric($this_arm)) continue;
				$arm_ids[] = $Proj->getArmIdFromArmNum($this_arm);
			}
			if (!empty($arm_ids)) {
				$sql_arms = " and arm_id in (".prep_implode($arm_ids).")";
			}
		}
		## LOCKING CHECK: Get all forms that are locked for the uploaded records
		$sql = "select record, arm_id, timestamp from redcap_locking_records
				where project_id = $project_id $sql_arms";
		// Deal with long queries
		if (strlen($sql.$sql_records) > 1000000) {
			$checkRecordNameEachLoop = true;
		} else {
			$sql .= $sql_records;
			$checkRecordNameEachLoop = false;
		}
		$locked = array();
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// If we need to validate the record in each loop, then check.
			if ($checkRecordNameEachLoop && !in_array($row['record'], $records)) continue;
			$this->lockedWhole[$row['record']][$row['arm_id']] = $row['timestamp'];
		}
	}


	// Display record-level locking PDF confirmation page, if applicable
	public static function renderRecordLockingPdfFrame($record)
	{
		global $lang, $isTablet, $isMobileDevice, $isIOS;
		$pdfUrlReal = APP_PATH_WEBROOT."index.php?route=PdfController:index&pid=".PROJECT_ID."&id=".htmlspecialchars($record, ENT_QUOTES)."&compact=1&display=inline";
		$pdfUrl = APP_PATH_WEBROOT . "DataEntry/empty.php?pid=" . PROJECT_ID;
		// Output iframe html
		$height = ($isTablet && $isIOS) ? "700px" : "400px";
		print  "<div id=\"recordLockPdfConfirmDialog\" class=\"simpleDialog\" title=\"".js_escape2($lang['data_entry_476'])."\">
					<div style='margin:10px;font-size:14px;'>{$lang['data_entry_477']}</div>
					<div style='margin:5px;padding:3px;border:1px solid #ccc;'>
						".($isIOS || $isMobileDevice
						? "<div class='p-2'>This browser does not support inline PDFs. Please open the PDF <b>in a new tab</b> to view it: 
							<button class='btn btn-xs btn-defaultrc ml-2 fs14' style='color:#C00000;' onclick='window.open(\"$pdfUrlReal\", \"_blank\");'><i class='far fa-file-pdf'></i> View PDF</button>
							</div>"
						: "<object data='$pdfUrl' type='application/pdf' style='width:100%;height:$height;'>
							<iframe id='record_lock_pdf_confirm_iframe' src='$pdfUrl' style='border:none;width:100%;height:$height;'>
							This browser does not support PDFs. Please download the PDF <b>in a new tab</b> to view it: <a target='_blank' href='$pdfUrlReal'>Download PDF</a>
							</iframe>
							</object>"
					)."
					</div>
					<div id='record_lock_pdf_confirm_checkbox_div' class='yellow' style='font-size:14px;margin:20px 20px 10px;'>
						<label id='record_lock_pdf_confirm_checkbox_label' class='opacity50' style='margin:5px 0;text-indent:-22px;margin-left:40px;cursor:pointer;'>
							<input type='checkbox' id='record_lock_pdf_confirm_checkbox'> {$lang['data_entry_490']} 
						</label>
					</div>
				</div>";
	}


	// Get array of all record-event-fields that are locked in a project, and add to $locked array
	public function findLocked($Proj, $records=array(), $fields=array(), $events=array())
	{
		// Build SQL
		$project_id = $Proj->project_id;
        if (!is_array($events)) $events = array($events);
        $eventSql = empty($events) ? "" : " and l.event_id in (".prep_implode($events).")";
		if (!is_array($records)) $records = array($records);
        $sql_records = empty($records) ? "" : " and l.record in (".prep_implode($records).")";
		if (!is_array($fields)) $fields = array($fields);
        $sql_fields = empty($fields) ? "" : " and m.field_name in (".prep_implode($fields).")";
		## LOCKING CHECK: Get all forms that are locked for the uploaded records
		$sql = "select l.record, l.event_id, l.instance, m.field_name, m.element_type, m.element_enum
				from redcap_locking_data l, redcap_metadata m
				where m.project_id = $project_id $eventSql
				and l.project_id = m.project_id and m.form_name = l.form_name";
        // Deal with long queries
        if (strlen($sql.$sql_fields) > 1000000) {
            $checkFieldNameEachLoop = true;
        } else {
            $sql .= $sql_fields;
            $checkFieldNameEachLoop = false;
        }
        if (strlen($sql.$sql_records) > 1000000) {
            $checkRecordNameEachLoop = true;
        } else {
            $sql .= $sql_records;
            $checkRecordNameEachLoop = false;
        }
		$locked = array();
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
            // If we need to validate the field name in each loop, then check.
            if ($checkFieldNameEachLoop && !in_array($row['field_name'], $fields)) continue;
            // If we need to validate the record in each loop, then check.
            if ($checkRecordNameEachLoop && !in_array($row['record'], $records)) continue;

			if ($row['element_type'] == 'checkbox') {
				foreach (array_keys(parseEnum($row['element_enum'])) as $this_code) {
					$chkbox_field_name = $row['field_name'] . "___" . Project::getExtendedCheckboxCodeFormatted($this_code);
					$this->locked[$row['record']][$row['event_id']][$row['instance']][$chkbox_field_name] = "";
				}
			} else {
				$this->locked[$row['record']][$row['event_id']][$row['instance']][$row['field_name']] = "";
			}
		}
	}

	// Get all files stored by PDF Record-locking. If provide $doc_id, then return just that file's attributes as an array.
	public static function getLockedRecordPdfFiles(&$Proj, $group_id=null, $doc_id=null)
	{
		// Filter by DAG, if needed
		$dagsql = "";
		if (is_numeric($group_id)) {
			$dagsql = "and a.record in (" . prep_implode(Records::getRecordList($Proj->project_id, $group_id)) . ")";
		}
		// Query table
		$files = array();
		$sql = "select e.stored_date, e.doc_size, e.doc_name, a.*
				from redcap_locking_records_pdf_archive a, redcap_edocs_metadata e
				where e.doc_id = a.doc_id $dagsql
				and e.delete_date is null and e.project_id = " . $Proj->project_id;
		if (is_numeric($doc_id)) {
			$sql .= " and e.doc_id = $doc_id";
		}
		$sql .= " order by e.doc_id desc";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($doc_id !== null) return $row;
			else $files[] = $row;
		}
		return $files;
	}

	// Render the File Repository table for displaying files for PDF Auto-Archiver
	public static function renderLockedRecordPdfArchiveFileTable(&$Proj)
	{
		global $lang, $user_rights;
		// Put file info in array
		$files = self::getLockedRecordPdfFiles($Proj, (isset($user_rights['group_id']) ? $user_rights['group_id'] : null));
		// Build drop-down list of records to filter on
		$recordDropdown = "";
		$recordList = array();
		if (isset($files[0])) {
			$recordList[''] = $lang['docs_68'];
			foreach ($files as $attr) {
				$recordList[$attr['record']] = $attr['record'];
			}
		}
		natcaseksort($recordList);
		if (count($recordList) > 2) {
			$recordDropdown = RCView::select(array('class'=>'x-form-text x-form-field', 'id'=>'filterRecord', 'onchange'=>"showProgress(1);window.location.href=app_path_webroot+'FileRepository/index.php?pid='+pid+'&type=record_lock_pdf_archive&record='+$('#filterRecord').val()+($('#filterEconsent').length ? '&econsentOnly='+$('#filterEconsent').val() : '');"),
				$recordList, $selectedRecord);
		}
		$rows = '';
		$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($recordList, false);
		foreach ($files as $file)
		{
			// Secondary ID / CRL
			$record_extra_label = isset($extra_record_labels[$file['record']]) ? " ".$extra_record_labels[$file['record']] : "";
			// Get arm of this record
			$this_arm = 1;
			if ($Proj->multiple_arms) {
				foreach ($Proj->events as $this_arm=>$attr) {
					if ($attr['id'] == $file['arm_id']) break;
				}
			}
			// Output row
			$rows .= "<tr>
						<td style='text-align:center;'>
							<span style='display:none;'>".$file['stored_date']."</span>
							".DateTimeRC::format_ts_from_ymd($file['stored_date'])."
						</td>
						<td style='text-align:center;'>
							<a style='text-decoration:underline;' href='" . APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=".$Proj->project_id."&id={$file['record']}&arm=$this_arm'>".RCView::escape($file['record']).($Proj->multiple_arms ? " ".$lang['leftparen'].$lang['global_08']." #{$this_arm}".$lang['rightparen'] : "")."</a>$record_extra_label
						</td>
						<td style='text-align:center;'>
							".($user_rights['data_export_tool'] == '1'
							? "<a href='" . APP_PATH_WEBROOT . "FileRepository/file_download.php?pid=".$Proj->project_id."&lock_doc_id={$file['doc_id']}' title='".js_escape($lang['docs_58'])."'
											style='text-decoration:none;'><img src='".APP_PATH_IMAGES."download_pdf.gif'></a>"
							: "<img class='opacity35' src='".APP_PATH_IMAGES."download_pdf.gif'>"
						)."
						</td>
					  </tr>";
		}
		// Set table headers
		$hdrs = "<thead><tr>
					<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;border-left:1px solid #ccc;'>{$lang['data_entry_491']}</th>
					<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['global_49']}</th>
					<th class='wrap' style='font-size:12px;padding:5px 8px;background-color:#FFFFE0;border-top:1px solid #ccc;border-right:1px solid #ccc;'>{$lang['api_46']}</th>
				</tr></thead>";
		// Build table
		$html = "<div style='max-width:800px;margin:10px 0;'>
				<div style='margin-bottom:20px;'>{$lang['data_entry_492']}</div>
				<table id='pdf_autoarchive_table' class='dataTable cell-border' style='table-layout:fixed;margin:10px 0;'>
					$hdrs
					<tbody>$rows</tbody>
				</table>
				</div>";
		// JavaScript
		$downloadAllDisabled = ($user_rights['data_export_tool'] == '1') ? "" : "disabled";
		$html .= "<script type='text/javascript'>
				$(function() {
					$('#pdf_autoarchive_table').DataTable( {
						'autoWidth': false,
						'processing': true,
						'paging': true,
						'pageLength': 10,
						'info': false,
						'aaSorting': [],
						'fixedHeader': { header: true, footer: false },
						'searching': true,
						'ordering': true,
						'oLanguage': { 'sSearch': '' }
					} );
					$('#pdf_autoarchive_table_filter input[type=search]').attr('type','text').prop('placeholder','Search');
					$('#pdf_autoarchive_table_filter').parent().parent().find('div.col-md-6:eq(0) label').css('font-weight','normal');
					$('#pdf_autoarchive_table_filter').parent().parent().find('div.col-md-6:eq(0)').removeClass('col-md-6').addClass('col-md-3');
					$('#pdf_autoarchive_table img.opacity35').click(function(){
						simpleDialog('".js_escape($lang['docs_67'])."');
					});
				});
				</script>";
		// Return html
		return $html;
	}

}
