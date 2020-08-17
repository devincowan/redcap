<?php


include dirname(dirname(__FILE__)) . '/ControlCenter/header.php';
if (!SUPER_USER) redirect(APP_PATH_WEBROOT);

loadJS('ToDoList.js');
//sorting
if (isset($_GET['sort'])){
  $sort = $_GET['sort'];
  $direction = $directionArchived = ($_GET['direction'] == 'asc') ? 'asc' : 'desc';
}else{
  $sort = 'request_time';
  $direction = 'asc';
  $directionArchived = 'desc';
}
//set up pagination variables for archived section
$page = intval($_GET['page']);
if ($page <= 0) $page = 1;
$total_records = ToDoList::getTotalNumberArchivedRequests();
$per_page = 10;//number of records to show per page
$total_pages = ceil($total_records / $per_page);
if ($page > $total_pages) $page = $total_pages;
$start_from = ($page-1) * $per_page;
?>
<style type="text/css">
#pagecontainer { max-width: 1130px; }
</style>
<div style="position:relative;" data-debug=<?php echo 'debugger'?>>
  <h4 class="page-title"><i class="fas fa-tasks"></i> <?php echo $lang['dashboard_111'] ?></h4>
  <div class="toggle-email-notifications-wrapper">
    <input class="toggle-notifications-cm" type="checkbox" <?php echo $send_emails_admin_tasks ? "checked" : "" ?>> 
	<?php echo $lang['control_center_4533'] ?>&nbsp;
	(<a href="javascript:;" style="text-decoration:underline;color:#666;font-size:11px;" onclick="simpleDialog('<?php echo js_escape($lang['control_center_4541']) ?>','<?php echo js_escape($lang['control_center_4533']) ?>');"><?php echo $lang['ws_22'] ?></a>)
  </div>
  <div class="download-csv-wrapper">
    <button class="jqbuttonmed" onclick="window.location.href='<?php echo APP_PATH_WEBROOT.'ToDoList/download_csv.php'; ?>';"><?php echo RCView::img(array('src'=>'xls.gif')) . RCView::span(array('style'=>'vertical-align:middle;'), $lang['survey_229']); ?></button>
  </div>
  <p style='margin:20px 0 10px;'>
	<?php print $lang['control_center_4546'] ?>
	<a href="javascript:;" style="text-decoration:underline;" onclick="$(this).hide();$('#todo-instr').show('fade');"><?php print $lang['scheduling_78'] ?></a>
	<span id='todo-instr' style='display:none;'><?php print $lang['control_center_4547'] ?></span>
</p>
</div>

<h2 class="pending-title" style="background-color:#eee;"><?php echo $lang['control_center_4534'] . "<div class='number-req-by-status'>(".ToDoList::getTotalNumberRequestsByStatus('pending').")</div>" ?>
  <?php echo "
  <div class='collapse-section-icon collapse-arrow-up'>".RCView::img(array('src'=>'minus.png','class'=>'collapse-up-arrow'))."</div>
  <div class='collapse-section-icon collapse-arrow-down'>".RCView::img(array('src'=>'plus.png','class'=>'collapse-down-arrow'))."</div>
  ";?>
</h2>
<div class="labels-container pending-section">
  <span class="todo-label todo-req-num" data-direction="asc" data-sort="request_id"><?php echo $lang['control_center_4537'] ?></span>
  <span class="todo-label todo-type" data-direction="asc" data-sort="todo_type"><?php echo $lang['control_center_4539'] ?></span>
  <span class="todo-label todo-req-time" data-direction="asc" data-sort="request_time"><?php echo $lang['control_center_4538'] ?></span>
  <span class="todo-label todo-username" data-direction="asc" data-sort="username"><?php echo $lang['global_17'] ?></span>
  <span class="todo-label todo-actions"><?php echo $lang['control_center_4540'] ?></span>
</div>
<?php
$list = ToDoList::retrieveToDoListByStatus('pending', $sort, $direction);
print ToDoList::renderList($list, 'pending');
?>

<h2 class="pending-title low-priority-title" style="background-color:#eee;"><?php echo $lang['control_center_4535'] . "<div class='number-req-by-status'>(".ToDoList::getTotalNumberRequestsByStatus('low-priority').")</div>" ?>
  <?php echo "
  <div class='collapse-section-icon collapse-arrow-up'>".RCView::img(array('src'=>'minus.png','class'=>'collapse-up-arrow'))."</div>
  <div class='collapse-section-icon collapse-arrow-down'>".RCView::img(array('src'=>'plus.png','class'=>'collapse-down-arrow'))."</div>
  ";?>
</h2>
<div class="labels-container ignored-section">
  <span class="todo-label todo-req-num" data-direction="asc" data-sort="request_id"><?php echo $lang['control_center_4537'] ?></span>
  <span class="todo-label todo-type" data-direction="asc" data-sort="todo_type"><?php echo $lang['control_center_4539'] ?></span>
  <span class="todo-label todo-req-time" data-direction="asc" data-sort="request_time"><?php echo $lang['control_center_4538'] ?></span>
  <span class="todo-label todo-username" data-direction="asc" data-sort="username"><?php echo $lang['global_17'] ?></span>
  <span class="todo-label todo-actions"><?php echo $lang['control_center_4540'] ?></span>
</div>
<?php
$list = ToDoList::retrieveToDoListByStatus('low-priority', $sort, $direction);
print ToDoList::renderList($list, 'complete-ignore');
?>

<h2 class="pending-title" style="background-color:#eee;"><?php echo $lang['control_center_4536'] . "<div class='number-req-archived'>(".$total_records.")</div>" ?>
  <?php
	if ($total_pages > 1) {
		echo "<div class='pagination-wrapper'><a class='pagination-item return-to-first' href='".$_SERVER['PHP_SELF']."?sort=$sort&direction=$directionArchived&page=1'>".'|<'."</a>";
		echo "<a class='pagination-item return-to-first' href='".$_SERVER['PHP_SELF']."?sort=$sort&direction=$directionArchived&page=".($page-1)."'>".'<'."</a>";
		$renderedFirstEllipsis = $renderedLastEllipsis = false;
		$countFirstPages = 7;
		$countLastPages = ($total_pages >= 14) ? 4 : 7;
		for ($i=1; $i<=$total_pages; $i++) {
			$is_current_page = ($page == $i);
			$current = $is_current_page ? 'current-page' : '';
			if ($is_current_page || $i < $countFirstPages || $i > $total_pages-$countLastPages) {
				echo "<a class='pagination-item ".$current."' href='".$_SERVER['PHP_SELF']."?sort=$sort&direction=$directionArchived&page=".$i."'>".$i."</a> ";
			} elseif (!$renderedFirstEllipsis) {
				echo "<span style='margin-right:5px;'>...</span>";
				$renderedFirstEllipsis = true;
			} elseif (!$renderedLastEllipsis && $page > $countFirstPages && $page < $total_pages-$countLastPages && $i > $page) {
				echo "<span style='margin-right:5px;'>...</span>";
				$renderedLastEllipsis = true;
			}
		}
		echo "<a class='pagination-item return-to-first' href='".$_SERVER['PHP_SELF']."?sort=$sort&direction=$directionArchived&page=".($page+1)."'>".'>'."</a>";
		echo "<a class='pagination-item return-to-first' href='".$_SERVER['PHP_SELF']."?sort=$sort&direction=$directionArchived&page=".$total_pages."'>".'>|'."</a>
		</div>";
	}
  ?>
</h2>
<div class="labels-container archived-section">
  <span class="todo-label todo-req-num" data-direction="asc" data-sort="request_id"><?php echo $lang['control_center_4537'] ?></span>
  <span class="todo-label todo-type" data-direction="asc" data-sort="todo_type"><?php echo $lang['control_center_4539'] ?></span>
  <span class="todo-label todo-req-time" data-direction="asc" data-sort="request_time"><?php echo $lang['control_center_4538'] ?></span>
  <span class="todo-label todo-username" data-direction="asc" data-sort="username"><?php echo $lang['global_17'] ?></span>
  <span class="todo-label todo-status" data-direction="asc" data-sort="status"><?php echo $lang['dataqueries_23'] ?></span>
  <span class="todo-label todo-actions"><?php echo $lang['control_center_4540'] ?></span>
</div>
<?php
$list = ToDoList::retrieveArchivedToDoList($sort, $start_from, $per_page, $directionArchived);
print ToDoList::renderList($list, 'archived');

include APP_PATH_DOCROOT . 'ControlCenter/footer.php';