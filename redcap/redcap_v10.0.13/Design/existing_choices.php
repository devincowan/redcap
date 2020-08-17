<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";
$is_matrix = (isset($_GET['is_matrix']) && $_GET['is_matrix'] == '1') ? '1' : '0';
// Query to get choices
$sql = db_query("SELECT field_name, element_enum
				FROM		$metadata_table
				WHERE		project_id = $project_id
				AND			element_type IN ('radio', 'select', 'checkbox')
				AND 		field_name != concat(form_name, '_complete')
				GROUP BY	element_enum
				ORDER BY	field_order");

$content = RCView::div(array('style'=>'margin-bottom:10px;'), $lang['design_519']);
$content .= "<table cellpadding=0 cellspacing=0 style='width:100%;border-bottom:1px solid #ccc;'>";
if (db_num_rows($sql) == 0) {
	$content .= "<tr>
					<td valign='top' colspan='2' style='background:#f3f3f3;padding:15px;color:#666;text-align:center;border:1px solid #ccc;border-bottom:0;'>
						{$lang['design_521']}
					</td>
				</tr>";
} else {
	while ($row = db_fetch_assoc($sql)) {
		if ($row['element_enum'] == '') continue;
		$row['element_enum'] = str_replace("\\n", "\n", $row['element_enum']);
		$content .= "<tr>
						<td valign='top' style='background:#f3f3f3;padding:12px 8px 4px;width:60px;text-align:center;border:1px solid #ccc;border-right:0;border-bottom:0;'>
							<button type=\"button\" onclick='existingChoicesClick(\"" . $row['field_name'] . "\",$is_matrix);'>" . $lang['design_520'] . "</button>
						</td>
						<td valign='top' style='padding:6px 0;line-height:13px;background:#f3f3f3;border:1px solid #ccc;border-left:0;border-bottom:0;'>
							<div id='ec_{$row['field_name']}' style='max-height:100px;overflow-y:auto;'>" . str_replace("\n", "<br>", RCView::escape($row['element_enum'])) . "</div>
						</td>
					</tr>";
	}
}
$content .= "</table>";

// Return title and content as JSON
print json_encode_rc(array(
	'title'		=> $lang['design_522'],
	'content'	=> $content
));
