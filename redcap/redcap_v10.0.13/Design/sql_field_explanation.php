<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Display text
$title = 'REDCap\'s "sql" field type for dynamically querying data';
$content = <<<EOT
<div class="wikipage searchable">
<p style="margin-top:0;">
<b>The "sql" field type is a very advanced type of field that requires technical database knowledge and can only be created or modified by
a REDCap administrator. An "sql" field allows one to populate a drop-down list on a data entry form or survey by providing an SQL query ("select" queries only)
in the Online Designer for a field or in the Select Choices column of the Data Dictionary.</b> 
</p>
<p>
Using an "sql" field can allow you to simulate a one-to-many
relationship from one REDCap project to another, or it can simply allow you to have a drop-down field populated with a dynamic list of choices
that originate from a number of different places, such as various database tables.
Any query can be used for an "sql" field so long as it is a SELECT query and so long as the database table(s) being queried exists 
in the same MySQL database as the REDCap database tables.
Also, ONLY REDCap administrators may create or modify "sql" fields, which can be done either via the Data Dictionary or the Online Designer. You must know how to
construct an SQL query in order to use this field type. <b>NOTE:</b> The only field in a project that cannot be set as an "sql" field is
the record ID field (i.e., the first field in the project).
</p>
<p>
The advantage of the "sql" field type is that it allows you to populate a drop-down from a dynamic source (i.e. a database table) 
rather than a static source (i.e. the choices provided in the Select Choices metadata column). 
When constructing the query itself, only 1 or 2 fields may be used in the query (which must be a "SELECT" query). 
If only one field exists in the SQL statement, the values retrieved from the query will serve as both the values AND the displayed text for the drop-down that is populated. If two fields are queried, the first field serves as the unseen values of the drop-down list while the second field gets displayed as the visible text inside the drop-down as seen by the user.
</p>
<p>
NOTE: If you are using an "sql" field to query REDCap's data table (redcap_data), remember that the table is an EAV model table, 
so it is not a flat table like an Excel spreadsheet, such as with the data exported out of REDCap in CSV data format. So it may be necessary 
to use sub-queries or multiple joins in order to effectively provide limiters on your query to return the exact data you want.
</p>


<h4 style="font-size:14px;font-weight:bold;">Examples</h4>
<p>
Here is an example of how one might query the redcap_data table by using a sub-query inside the query to work as a filter so that it returns the record name and institution name for *only* the records that have a 'consortium_status' value of '1'.
</p>
<pre style="padding:3px;border:1px solid #ddd;background-color:#f5f5f5;">select record, value from redcap_data where project_id = 390 and field_name = 'institution'
and record in (select distinct record from redcap_data where project_id = 390
and field_name = 'consortium_status' and value = '1') order by value
</pre><p>
But the query above could also be constructed instead using a JOIN rather than a sub-query.
</p>
<pre style="padding:3px;border:1px solid #ddd;background-color:#f5f5f5;">select a.record, a.value from redcap_data a left join redcap_data b
on a.project_id = b.project_id and a.record = b.record and a.event_id = b.event_id
where a.project_id = 390 and a.field_name = 'institution'
and b.field_name = 'consortium_status' and b.value = '1' order by a.value
</pre><p>
If the redcap_data table were a "flat" formatted table (or if you are querying any kind of flat table), the query above might look something like the one below.
</p>
<pre style="padding:3px;border:1px solid #ddd;background-color:#f5f5f5;">select auto_num, institution from FLAT_TABLE where project_id = 390
and consortium_status != '4' order by auto_num
</pre>



<h4 style="font-size:14px;margin-top:20px;font-weight:bold;">Complex Examples</h4>
<p>
Here is an example where we are bringing in a bunch of patient details into a dropdown in another project.  
It uses both CONCAT and CONCAT_WS.  CONCAT_WS is very useful because it will leave out any NULL parameters whereas CONCAT 
will return NULL if any part of the expression is NULL.  So for the example query below, if values for the 'mrn' field are
not defined for a record, they will not appear in the list.
</p>
<pre style="padding:3px;border:1px solid #ddd;background-color:#f5f5f5;">SELECT a.record,
   CONCAT_WS(' | ',
      CONCAT('R: ', max(if(a.field_name = 'record_id', a.value, NULL))),
      CONCAT('MRN: ', max(if(a.field_name = 'mrn', a.value, NULL))),
      CONCAT_WS(', ',
         max(if(a.field_name = 'last_name', a.value, NULL)),
         max(if(a.field_name = 'first_name', a.value, NULL))
      ),
      CONCAT('DOB: ', max(if(a.field_name = 'dob', a.value, NULL))),
      CONCAT('TX: ', max(if(a.field_name = 'tx', a.value, NULL))),
      CONCAT('DATE: ', max(if(a.field_name = 'date_tx', a.value, NULL))),
      CONCAT('ID: ', max(if(a.field_name = 'study_id', a.value, NULL)))
   ) as value
FROM redcap_data a
WHERE a.project_id=1360
   AND a.event_id=5854
GROUP BY a.record
ORDER BY a.record
</pre>

<h4 style="font-size:14px;margin-top:20px;font-weight:bold;">Using Smart Variables in SQL Fields</h4>
<p>
<a href="smart_variable_explain.php" target="_blank" style="text-decoration:underline;">Smart Variables</a> can be used in the SQL query, and utilizing them can be very powerful because
they allow the query to be truly dynamic and change from context to context or record to record,
rather than it always being a static query that gets executed against the database. It should be noted that when using Smart Variables inside the query of an SQL field, <b>you do NOT
need to wrap the Smart Variable in quotes or apostrophes because the Smart Variable itself will be replaced with a value already wrapped in single quotes</b>. Also,
the value of the Smart Variable will be SQL-escaped when placed inside the query so that no user can inject values to manipulate the query. This has no effect on how one constructs the query, 
but for security purposes it is good to know that this is being done.
</p>
<p>
Below is a simple example of using a Smart Variable to show how one can query values that belong ONLY to the current record. In this example from a longitudinal project,
a provider's name is recorded as a text field on different events, and so the SQL field was created to provide a list of provider names that have been entered on previous events but
ONLY for the current record.
</p>
<pre style="padding:3px;border:1px solid #ddd;background-color:#f5f5f5;">
select value, concat('Dr. ', value) from redcap_data where project_id = 113 
and field_name = 'provider_name' and record = [record-name] order by value
</pre>
<p>
Below is a slightly different version of the example above that illustrates an important point of using Smart Variables in SQL field queries, that is,
that the SQL field's query will often get executed in many different contexts/pages in a REDCap project. <b>So while the SQL field will primarily be executed on a data entry form or survey page, 
in which a record and event will be in context, the same query may also be executed on reports, Data Quality rules, Project XML file downloads, and other places where the SQL field drop-down
choice list needs to be displayed in which there is no record or event in context, thus
[record-name] defaults to a blank value '' in those places.</b> (Note: Even though Data Quality rules and reports can display many records and thus many record contexts, it is not very efficient
performance-wise to have the server execute the query on a per-record basis. So for those places, the query is only executed once, in which there is not a record/event/instance in context.) 
If the Smart Variable is simply replaced with a blank value because there is no context to fully utilize the Smart Variable, the query might return a very different set of results,
which may not be desired.
This is why the IF() statement has been added to the example query below to compensate for the loss of the record context on certain pages. 
But in many cases, not compensating for the loss of context may not cause any adverse affects. For example,
not including the IF() statement below would only result in the SQL field drop-down's choice labels not getting displayed in a report (the saved value would still be displayed in the report though)
and in other places would simply cause the drop-down to be displayed with no choices, which might be fine if the user understands that this is the expected behavior. 
</p>
<pre style="padding:3px;border:1px solid #ddd;background-color:#f5f5f5;">
select value, concat('Dr. ', value) from redcap_data where project_id = 113 
and field_name = 'provider_name' and if ([record-name] = '', 1=1, record = [record-name]) order by value
</pre>
<p>
Here is a different example where you might want to alter the query based on the Data Access Group of the current record.
</p>
<pre style="padding:3px;border:1px solid #ddd;background-color:#f5f5f5;">
select value from redcap_data where project_id = 853 and field_name = 'result' 
and if ([record-dag-name] = 'vanderbilt_group', value > 100, value <= 100) order by value
</pre>
<p style='margin-top:20px;'>
<b>Note: While the instructions above only illustrate how to use just a couple different Smart Variables, many more Smart Variables can be utilized for SQL fields, most notably those
that reference repeating instance, event, record, user, and data access group.</b>
</p>

</div>
<br><br>
EOT;

// Output text
print json_encode_rc(array('title'=>$title, 'content'=>$content));