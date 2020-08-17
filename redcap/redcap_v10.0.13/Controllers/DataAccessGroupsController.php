<?php

class DataAccessGroupsController extends Controller
{
	// Save User-DAG assignment
	public function saveUserDAG()
	{
		global $user_rights;
		header("Content-Type: application/json");
		if ($user_rights['data_access_groups'] != 1) {
			$result = '0'; // user must have DAG page permission
		} else {
			try {
				$user = $_POST['user'];
				$dag = $_POST['dag'];
				$enabled = $_POST['enabled']=='true';
				$module = new DAGSwitcher();
				$result = $module->saveUserDAG($user, $dag, $enabled);
			} catch (Exception $ex) {
				http_response_code(500);
				$result = 'Exception: '.$ex->getMessage();
			}
		}
		print json_encode(array('result'=>$result));
	}

	// Render the DAG Switcher table
	public function getDagSwitcherTable()
	{
		if (isset($_GET['rowoption']) && $_GET['rowoption'] == 'users') {
			UIState::saveUIStateValue(PROJECT_ID, 'data_access_groups', 'rowoption', 'users');
		} else {
			UIState::removeUIStateValue(PROJECT_ID, 'data_access_groups', 'rowoption');
		}
		$module = new DAGSwitcher();
		print $module->renderDAGPageTableContainer();
	}

	// Switch DAG
	public function switchDag()
	{
		$module = new DAGSwitcher();
		print $module->switchToDAG($_POST['dag']);
	}

	// Render main DAG page
	public function index()
	{
		DataAccessGroups::renderPage();
	}

	// DAG ajax requests on main DAG page
	public function ajax()
	{
		DataAccessGroups::ajax();
	}
}