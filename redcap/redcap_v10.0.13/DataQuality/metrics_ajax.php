<?php



// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Page is only usable if DRW is enabled
if ($data_resolution_enabled != '2') exit('');

// Instantiate DataQuality object
$dq = new DataQuality();

// Validate the chart
if (!isset($dq->drw_metrics_charts[$_POST['chart_name']])) exit('');

// Set chart name
$chartname = $_POST['chart_name'];

// Initialize vars
$min = $max = 0;
$data = array();


/**
 * GATHER DATA FOR EACH CHART
 */

// Top queried fields
if ($chartname == 'top_fields')
{
	// Query tables
	$sql = "select field_name, count(*) as field_count from redcap_data_quality_status where project_id = $project_id
			and non_rule = 1 and field_name is not null and query_status in ('OPEN','CLOSED')
			group by field_name having count(*) > 0 order by count(*) desc, field_name limit 5";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Get new max, if applicable
		if ($row['field_count'] > $max) $max = $row['field_count'];
		// Add to data array
		$data[] = array($row['field_name'], (int)$row['field_count']);
	}
}

// Top queried DQ rules
elseif ($chartname == 'top_rules')
{
	// Get list of DQ rules
	$dq_rules = $dq->getRules();
	// Query tables
	$sql = "select rule_id, count(*) as rule_count from redcap_data_quality_status
			where project_id = $project_id and rule_id is not null and query_status in ('OPEN','CLOSED')
			group by rule_id having count(*) > 0 order by count(*) desc, rule_id limit 5";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Get new max, if applicable
		if ($row['rule_count'] > $max) $max = $row['rule_count'];
		// Get rule name
		$rule_num = $dq_rules[$row['rule_id']]['order'];
		$rule_name = $lang['dataqueries_14']." #".$rule_num.$lang['colon']." ".$dq_rules[$row['rule_id']]['name'];
		// Add to data array
		$data[] = array($rule_name, (int)$row['rule_count']);
	}
}

// Top queried records
elseif ($chartname == 'top_records')
{
	// Query tables
	$sql = "select record, count(*) as count from redcap_data_quality_status
			where project_id = $project_id and query_status in ('OPEN','CLOSED')
			group by record having count(*) > 0 order by count(*) desc, abs(record), record limit 5";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Get new max, if applicable
		if ($row['count'] > $max) $max = $row['count'];
		// Add to data array
		$data[] = array( $row['record'], (int)$row['count']);
	}
}

// Count of open queries by DAG
elseif ($chartname == 'num_open_queries_by_dag')
{
	// Obtain array of DAGs
	$dags = $Proj->getGroups();
	// Query tables
	$sql = "select x.group_id, count(1) as count from (
			select distinct d.value as group_id, s.status_id
			from redcap_data_quality_status s, redcap_data d where s.project_id = $project_id
			and s.query_status = 'OPEN' and s.project_id = d.project_id and s.record = d.record
			and d.field_name = '__GROUPID__') x
			group by x.group_id order by count(*) desc";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Get new max, if applicable
		if ($row['count'] > $max) $max = $row['count'];
		// Validate the DAG id
		if (!isset($dags[$row['group_id']])) continue;
		// Add to data array
		$data[] = array($dags[$row['group_id']], (int)$row['count']);
		// Remove DAG from array so we know which ones have no open queries
		unset($dags[$row['group_id']]);
	}
	// If any DAGs have no open queries, add them with 0 queries at end of $data
	foreach ($dags as $group_name) {
		// Add to data array
		$data[] = array($group_name, 0);
	}
}

// Count of closed queries by DAG
elseif ($chartname == 'num_closed_queries_by_dag')
{
	// Obtain array of DAGs
	$dags = $Proj->getGroups();
	// Query tables
	$sql = "select x.group_id, count(1) as count from (
			select distinct d.value as group_id, s.status_id
			from redcap_data_quality_status s, redcap_data d where s.project_id = $project_id
			and s.query_status = 'CLOSED' and s.project_id = d.project_id and s.record = d.record
			and d.field_name = '__GROUPID__') x
			group by x.group_id order by count(*) desc";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Get new max, if applicable
		if ($row['count'] > $max) $max = $row['count'];
		// Validate the DAG id
		if (!isset($dags[$row['group_id']])) continue;
		// Add to data array
		$data[] = array($dags[$row['group_id']], (int)$row['count']);
		// Remove DAG from array so we know which ones have no open queries
		unset($dags[$row['group_id']]);
	}
	// If any DAGs have no open queries, add them with 0 queries at end of $data
	foreach ($dags as $group_name) {
		// Add to data array
		$data[] = array($group_name, 0);
	}
}

// Query resolution time by Data Access Group
elseif ($chartname == 'resolution_time_by_dag')
{
	// Obtain array of DAGs
	$dags = $Proj->getGroups();
	// Query tables
	$sql = "select d.value as group_id, round(avg(z.sec_to_resolve)/86400,1) as avg_days_to_resolve
			from (select x.record, x.instance, TIMESTAMPDIFF(SECOND, x.min_ts, max(y.ts)) as sec_to_resolve
			from (select s.status_id, s.record, s.instance, min(r.ts) as min_ts from redcap_data_quality_status s,
			redcap_data_quality_resolutions r where s.project_id = $project_id and s.query_status = 'CLOSED'
			and r.status_id = s.status_id and r.current_query_status = 'OPEN' group by s.status_id) x,
			redcap_data_quality_resolutions y where x.status_id = y.status_id group by y.status_id) z,
			redcap_data d where d.project_id = $project_id and z.record = d.record and d.field_name = '__GROUPID__'
			and z.instance = if(d.instance is null,'1',d.instance)
			group by d.value order by avg(z.sec_to_resolve) desc";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Get new max, if applicable
		if ($row['avg_days_to_resolve'] > $max) $max = $row['avg_days_to_resolve'];
		// Validate the DAG id
		if (!isset($dags[$row['group_id']])) continue;
		// Add to data array
		$data[] = array($dags[$row['group_id']], $row['avg_days_to_resolve']*1);
		// Remove DAG from array so we know which ones have no open queries
		unset($dags[$row['group_id']]);
	}
	// If any DAGs have no open queries, add them with 0 queries at end of $data
	foreach ($dags as $group_name) {
		// Add to data array
		$data[] = array($group_name, 0);
	}
}

// Query response time by Data Access Group
elseif ($chartname == 'response_time_by_dag')
{
	// Obtain array of DAGs
	$dags = $Proj->getGroups();
	// Query tables
	$sql = "select d.value as group_id, round(avg(z.sec_to_respond)/86400,1) as avg_days_to_respond
			from (select x.record, x.instance, TIMESTAMPDIFF(SECOND, y.open_ts, x.response_ts) as sec_to_respond
			from (select s.status_id, s.record, s.instance, min(r.ts) as response_ts from redcap_data_quality_status s,
			redcap_data_quality_resolutions r where s.project_id = $project_id and s.query_status in ('OPEN', 'CLOSED')
			and r.status_id = s.status_id and r.current_query_status = 'OPEN' and r.response is not null
			group by s.status_id) x, (select s.status_id, min(r.ts) as open_ts
			from redcap_data_quality_status s, redcap_data_quality_resolutions r
			where s.project_id = $project_id and s.query_status in ('OPEN', 'CLOSED') and r.status_id = s.status_id
			and r.current_query_status = 'OPEN' group by s.status_id) y
			where x.status_id = y.status_id group by x.status_id) z, redcap_data d
			where d.project_id = $project_id and z.record = d.record and d.field_name = '__GROUPID__'
			and z.instance = if(d.instance is null,'1',d.instance)
			group by d.value order by avg(z.sec_to_respond) desc";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Get new max, if applicable
		if ($row['avg_days_to_respond'] > $max) $max = $row['avg_days_to_respond'];
		// Validate the DAG id
		if (!isset($dags[$row['group_id']])) continue;
		// Add to data array
		$data[] = array($dags[$row['group_id']], $row['avg_days_to_respond']*1);
		// Remove DAG from array so we know which ones have no open queries
		unset($dags[$row['group_id']]);
	}
	// If any DAGs have no open queries, add them with 0 queries at end of $data
	foreach ($dags as $group_name) {
		// Add to data array
		$data[] = array($group_name, 0);
	}
}

// Query response time by responding user
elseif ($chartname == 'response_time_by_user')
{
	// Query tables
	$sql = "select i.username, round(avg(z.sec_to_respond)/86400,1) as avg_days_to_respond from
			(select x.user_id, TIMESTAMPDIFF(SECOND, y.open_ts, x.response_ts) as sec_to_respond
			from (select s.status_id, r.user_id, min(r.ts) as response_ts
			from redcap_data_quality_status s, redcap_data_quality_resolutions r
			where s.project_id = $project_id and s.query_status in ('OPEN', 'CLOSED') and r.status_id = s.status_id
			and r.current_query_status = 'OPEN' and r.response is not null group by s.status_id) x,
			(select s.status_id, min(r.ts) as open_ts from redcap_data_quality_status s,
			redcap_data_quality_resolutions r where s.project_id = $project_id and s.query_status in ('OPEN', 'CLOSED')
			and r.status_id = s.status_id and r.current_query_status = 'OPEN' group by s.status_id) y
			where x.status_id = y.status_id group by x.status_id) z, redcap_user_information i
			where i.ui_id = z.user_id group by z.user_id order by avg(z.sec_to_respond) desc";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q)) {
		// Get new max, if applicable
		if ($row['avg_days_to_respond'] > $max) $max = $row['avg_days_to_respond'];
		// Add to data array
		$data[] = array($row['username'], $row['avg_days_to_respond']*1);
	}
}


// Return as JSON
print json_encode_rc(array('min'=>(int)$min, 'max'=>(int)$max, 'data'=>$data));