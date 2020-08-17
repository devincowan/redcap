<?php

/**
 * REDCap External Module: DAG Switcher
 */
class DAGSwitcher
{
	private $lang;
	private $page;
	private $project_id;
	private $super_user;
	private $user;
	private $user_rights;
	private $Proj;

	public function __construct()
    {
			global $Proj, $lang, $user_rights;
			$this->lang = &$lang;
			$this->page = PAGE;
			$this->project_id = intval(PROJECT_ID);
			$this->super_user = SUPER_USER;
			$this->Proj = $Proj;
			// If user is being impersonated, then set user to be the user being impersonated
            if (UserRights::isImpersonatingUser()) {
				$this->user = $_SESSION['impersonate_user'][PROJECT_ID]['impersonating'];
				$this->user_rights = UserRights::getPrivileges(PROJECT_ID, $this->user)[PROJECT_ID][$this->user];
            } else {
				$this->user = USERID;
				$this->user_rights = &$user_rights;
            }
	}

	/**
	 * Read the current configuration of users and enabled DAGs from the 
	 * user-dag-mapping project setting (or fall back to most recent DAG 
	 * Switcher record in redcap_log_event where stored until v1.2.1 - 
	 * removed in v1.3.0)
	 * @return array 
	 *  keys: Usernames
	 *  values: Array of DAGids user may switch to
	 * [
	 *   "user1": [],
	 *   "user2: [0,123,124],
	 *   "user3": [123,124]
	 * ]
	 */
	public function getUserDAGs($user=null)
    {
        // User DAG assignments
		$userDags = array();

		// Get value in table
		$sql = "select group_id, username from redcap_data_access_groups_users where project_id = ".PROJECT_ID;
		if ($user != null) $sql .= " and username = '".db_escape($user)."'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$row['username'] = trim(strtolower($row['username']));
			$userDags[$row['username']][] = $row['group_id'];
		}

        return $userDags;
	}
	
	/**
	 * Print table container to DAGs page. Table and user/DAG data fetched by ajax call.
	 */
	public function renderDAGPageTableContainer()
    {
		global $lang;
		$dagTableBlockInfo = $lang['data_access_groups_09'];
		$dagTableRowOptionDags = $lang['data_access_groups_10'];
		$dagTableRowOptionUsers = $lang['data_access_groups_11'];

		// Users vs DAGs as rows?
		$rowoption = UIState::getUIStateValue(PROJECT_ID, 'data_access_groups', 'rowoption');
		if ($rowoption != null) $_GET['rowoption'] = $rowoption;
		$rowsAreDags = !(isset($_GET['rowoption']) && $_GET['rowoption']==='users'); // rows as dags is default

		if (!$rowsAreDags) {
            $rowOptionCheckedD = '';
            $rowOptionCheckedU = 'checked'; // rows are users, columns are dags
		} else {
            $rowOptionCheckedD = 'checked'; // rows are dags, columns are users
            $rowOptionCheckedU = '';
		}

		return RCView::div(array('id'=>'dag-switcher-config-container', 'class'=>'blue'),
                    RCView::div(array('class'=>'clearfix'),
                        RCView::div(array('class'=>'float-right mr-2'),
                            "<input type='radio' name='rowoption' id='rowoptiondags' value='dags' $rowOptionCheckedD>&nbsp;&nbsp;<label for='rowoptiondags' style='margin:0;cursor: pointer;'>$dagTableRowOptionDags</label><br>
                             <input type='radio' name='rowoption' id='rowoptionusers' value='users' $rowOptionCheckedU>&nbsp;&nbsp;<label for='rowoptionusers' style='margin:0;cursor: pointer;'>$dagTableRowOptionUsers</label>"
                        ).
                        RCView::div(array('class'=>'float-right mr-3 font-weight-bold'),
                            $lang['data_access_groups_19']
                        ).
                        RCView::div(array('class'=>'fs15 float-left'),
                            RCView::span(array('class'=>'font-weight-bold'), RCView::i(array('class'=>'fas fa-random fs14 mr-1')) . $lang['data_access_groups_08']) .
                            RCView::SP . RCView::span(array('class'=>'boldish'), $lang['data_access_groups_20'])
                        )
                    ) .
                    RCView::div(array('style'=>'margin:10px 0;'), $dagTableBlockInfo).
                    RCView::div(array('id'=>'dag-switcher-spin'),
                        RCView::img(array('src'=>'progress_circle.gif'))
                    ).
                    RCView::div(array('id'=>'dag-switcher-table-container'),
                        $this->getUserDAGsTable($rowsAreDags)
                    )
            );
	}

	/**
	 * Get HTML markup for table, including header columns of the selected 
	 * type (dags or users)
	 * @param bool $rowsAreDags Set to <i>true</i> to get column per user.
	 * Set to <i>false</i> to get column per DAG.
	 * @return string HTML 
	 */
	public function getUserDAGsTable($rowsAreDags=true)
    {
			global $lang;
			$superusers = array();
		    $allUsers = REDCap::getUsers();
		    $allDags = REDCap::getGroupNames(false);
			
			if ($rowsAreDags) { // columns are users
					// column-per-user, row-per-dag (load via ajax)
					$col0Hdr = $this->lang['global_22']; // Data Access Groups
					$colGroupHdr = $this->lang['control_center_132']; // Users
					$colSet = $allUsers;
					uasort($colSet, array($this,'value_compare_func')); // sort in ascending order by value, case-insensitive, preserving keys
					$superusers = $this->readSuperUserNames();
			} else { // $rowsAreDags===false // columns are dags
					// column-per-dag, row-per-user (load via ajax)
					$col0Hdr = $this->lang['control_center_132']; // Users
					$colGroupHdr = $this->lang['global_22']; // Data Access Groups
					$colSet = $allDags;
					uasort($colSet, array($this,'value_compare_func')); // sort in ascending order by value, case-insensitive, preserving keys
					$colSet = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)$colSet; // [No Assignment]
			}
			
			$colhdrs = RCView::tr(array(),
					RCView::th(array('rowspan'=>2, 'class'=>'font-weight-bold fs14 px-4 py-4 text-center', 'style'=>'background-color: #fafafa;color:#000066; border-top: 0px none; padding:3px; white-space:normal; vertical-align:bottom;'),
                        $col0Hdr
					).
					RCView::th(array('colspan'=>count($colSet), 'class'=>'font-weight-bold fs14 pl-3 pr-5 py-2', 'style'=>'background-color: #fafafa;color:#000066; border-top: 0px none; padding:3px; white-space:normal; vertical-align:bottom;'),
                        $colGroupHdr
					)
			);
			foreach ($colSet as $col) {
					if ($rowsAreDags && in_array($col, $superusers)) {
							$col = RCView::span(array('style'=>'color:#777;','title'=>'Super users see all!'),$col);
					}
					$colhdrs .= RCView::th(array('class'=>'fs13 p-1 pt-4', 'style'=>'background-color: #fafafa;border-top: 0px none;text-align: center; white-space: normal; vertical-align: bottom; width: 22px;'),
							RCView::div(array('style'=>'font-weight:normal;'),
									RCView::span(array('class'=>'vertical-text'),
											RCView::span(array('class'=>'vertical-text-inner'),
													$col
											)
									)
							)
					);
			}

			// DAG Switcher table becomes unwieldy and crashes browsers than when >10k checkboxes are displayed, so disable it if so
			if (count($allDags) * count($allUsers) > 10000) {
				$dagSwitcherRows =  RCView::thead(array(), '') .
                                    RCView::tbody(array(),
                                        RCView::td(array('class'=>'yellow wrap text-left text-danger'), '<i class="fas fa-exclamation-triangle"></i> '.$lang['data_access_groups_24'])
                                    );
            } else {
				$dagSwitcherRows = RCView::thead(array(), $colhdrs) . RCView::tbody(array(), $this->getUserDAGsTableRowData($rowsAreDags));
            }

			$html = RCView::table(array('class'=>'table table-striped table-bordered display nowrap compact no-footer','id'=>'dag-switcher-table'), $dagSwitcherRows);

			return $html;
	}

	/**
	 * Get table row data - rows as DAGs or rows as users, as appropriate
	 * @param bool $rowsAreDags Set to <i>true</i> to get column per user.
	 * Set to <i>false</i> to get column per DAG.
	 * @return array
	 */
	public function getUserDAGsTableRowData($rowsAreDags=true)
    {
        global $lang;
        // [
        //   "user1": []
        //   "user2: [0,123,124]
        //   "user3": [123,124]
        // ]
        $usersEnabledDags = $this->getUserDAGs();

        $users = REDCap::getUsers();
        uasort($users, array($this,'value_compare_func')); // sort in ascending order by value, case-insensitive, preserving keys

        $dags = REDCap::getGroupNames(false);
        uasort($dags, array($this,'value_compare_func')); // sort in ascending order by value, case-insensitive, preserving keys
        $dags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)$dags; // [No Assignment]

        $rows = array();
        $superusers = $this->readSuperUserNames();

        if (count($users)===0) { // can only be a superuser viewing an orphan project so don't need anything fancy returned
            // $rows = null;
        } else if ($rowsAreDags) {
            foreach ($dags as $dagId => $dagName) {
                $row = array();
                $row[] = array('rowref'=>$dagName);
                foreach ($users as $user) {
                    $row[] = array(
                        'rowref' => $dagName,
                        'dagid' => $dagId,
                        'dagname' => $dagName,
                        'user' => $user,
                        'enabled' => (in_array($dagId, $usersEnabledDags[$user]))?1:0,
                        'is_super' => (in_array($user, $superusers))?1:0
                    );
                }
                $rows[] = $row;
            }
        } else {
            foreach ($users as $user) {
                $row = array();
                $row[] = array('rowref'=>$user,'is_super' => (in_array($user, $superusers))?1:0);
                foreach ($dags as $dagId => $dagName) {
                    $row[] = array(
                        'rowref' => $user,
                        'dagid' => $dagId,
                        'dagname' => $dagName,
                        'user' => $user,
                        'enabled' => (in_array($dagId, $usersEnabledDags[$user]))?1:0,
                        'is_super' => (in_array($user, $superusers))?1:0
                    );
                }
                $rows[] = $row;
            }
        }

        // Loop through rows and render their HTML
        $html = '';
        foreach ($rows as $rkey=>$row) {
			$rowhtml = '';
            foreach ($row as $kcol=>$col) {
                if ($kcol == 0) {
					$rowhtml .= RCView::td(array('class'=>"dag-switcher-table-left-col pl-4 pr-3 text-right"),
                                    $col['rowref']
                                );
                } else {
                    $title = $col['is_super'] ? $lang['data_access_groups_12'] : $col['dagname']." : ".$col['user'];
                    $checkAttr = array('data-dag'=>$col['dagid'], 'data-user'=>$col['user'], 'title'=>$title);
					if ($col['is_super']) $checkAttr['disabled'] = 'disabled';
					if ($col['enabled']) $checkAttr['checked'] = 'checked';
					$rowhtml .= RCView::td(array(),
						            RCView::span(array('class'=>'hidden'), ($checkAttr['checked'] ? '1' : '0')) .
                                    RCView::checkbox($checkAttr) .
                                    RCView::img(array('src'=>'progress_circle.gif', 'style'=>'display:none;'))
                                );
                }
            }
			$html .= RCView::tr(array('class'=>'', 'data-dt-row'=>$rkey), $rowhtml);
        }

        return $html;
	}

	/**
	 * Read the list of superusers' usernames
	 * @return array Usernames of superusers
	 */
	protected function readSuperUserNames() {
			$superusers = array();
			
			$r = db_query('select username from redcap_user_information where super_user=1');
			if ($r->num_rows > 0) {
					while ($row = $r->fetch_assoc()) {
							$superusers[] = trim(strtolower($row['username']));
					}
			}
			return $superusers;
	}
	
	/**
	 * Enable or disable a DAG for a user
	 * @param int $user Valid username for project
	 * @param int $dag Valid DAG id for project
	 * @param int $enabled 1 to enable, 0 to disable
	 * @return string '1' on successful save, '0' on failure
	 */
	public function saveUserDAG($user, $dag, $enabled)
	{
	    global $lang;
        $enabled = (bool)$enabled;
		$projDags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)REDCap::getGroupNames();
		$projUsers = REDCap::getUsers();
		$user = trim(strtolower($user));
        if (!array_key_exists($dag, $projDags) || !in_array($user, $projUsers)) {
            return '0'; // invalid dag or user
        }
		$projDags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)REDCap::getGroupNames(true);
		$group_name = $projDags[$dag];
        // Save value in table
		if ($enabled) {
			$dag_sql = ($dag == '0') ? "null" : "'".db_escape($dag)."'";
			$sql = "insert into redcap_data_access_groups_users (project_id, group_id, username) values ('".PROJECT_ID."', $dag_sql, '".db_escape($user)."')";
			$logtext = "DAG Switcher: Assign user to additional DAGs";
		} else {
			$dag_sql = ($dag == '0') ? "is null" : "= '".db_escape($dag)."'";
			$sql = "delete from redcap_data_access_groups_users where group_id $dag_sql and username = '".db_escape($user)."' and project_id = ".PROJECT_ID;
			$logtext = "DAG Switcher: Remove user from multiple DAG assignment";
		}
		if (db_query($sql)) {
			// Log it
			Logging::logEvent($sql,"redcap_data_access_groups_users","MANAGE",PROJECT_ID,"user = '$user',\ngroup = '" . $group_name . "'",$logtext);
			return '1';
		}
		return '0';
	}

	/**
	 * If user may access more than one DAG then include a block at the top 
	 * of the page which displays the current dag, and a button to switch to
	 * another enabled DAG.
	 */
	public function renderUserDAGInfo()
    {
        global $lang;
        if (UserRights::isSuperUserNotImpersonator()) return ''; // super user always sees all
		$dags = REDCap::getGroupNames(false);
        if (empty($dags)) return '';

        $dags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)$dags; // [No Assignment]

        $changeButton = '';
        $userDags = $this->getUserDAGs($this->user);
        $html = '';
        if (empty($userDags)) return $html;

        if (isset($userDags[$this->user]) && count($userDags[$this->user]) > 1)
        {
                $pageBlockTextPre = $lang['data_access_groups_13'];
                $dagSwitchDialogText = $lang['data_access_groups_14']." ";
                $dagSwitchDialogBtnText = $lang['data_access_groups_15'];

                $currentDagId = ($this->user_rights['group_id'] !== '') ? 1*$this->user_rights['group_id'] : 0;
                $currentDagName = $dags[$currentDagId];

                $thisUserOtherDags = array();

                foreach ($userDags[$this->user] as $id) {
                    if ($id == '') $id = 0;
                    if (array_key_exists($id, $dags) && intval($id) !== intval($currentDagId)) {
                            $thisUserOtherDags[$id] = $dags[$id];
                    }
                }

                uasort($thisUserOtherDags, array($this,'value_compare_func')); // sort dag names alphabetically in dialog, preserving keys

                // Disable the "Switch" button when in a record context (data entry page or record home page) and display popover
			    $switch_btn_array = array('id'=>'dag-switcher-change-button', 'class'=>'btn btn-xs btn-primaryrc fs13 nowrap');
			    $switch_btn_span_array = array('id'=>'dag-switcher-change-button-span');
                if ((PAGE == 'DataEntry/index.php' || PAGE == 'DataEntry/record_home.php') && isset($_GET['id'])) {
					$switch_btn_array['disabled'] = 'disabled';
					$switch_btn_array['style'] = 'pointer-events: none;';
					$switch_btn_span_array['data-trigger'] = 'hover';
					$switch_btn_span_array['data-toggle'] = 'popover';
					$switch_btn_span_array['data-placement'] = 'bottom';
					$switch_btn_span_array['data-content'] = htmlspecialchars($lang['data_access_groups_18'], ENT_QUOTES);
					$switch_btn_span_array['data-title'] = htmlspecialchars($lang['data_access_groups_16']." ".$lang['design_101'], ENT_QUOTES);
                }

                $changeButton = RCView::span($switch_btn_span_array,
                                    RCView::button($switch_btn_array, '<i class="fas fa-random mr-1"></i>'.$dagSwitchDialogBtnText)
                                );

                $dagSelect = RCView::select(array('id'=>'dag-switcher-change-select', 'class'=>'form-control fs14'), $thisUserOtherDags);

                $html .= RCView::div(
					array('id'=>'dag-switcher-change-dialog', 'class'=>'simpleDialog', 'title'=>$lang['data_access_groups_16']),
                    RCView::div(array('class'=>'my-2 fs14'), $dagSwitchDialogText).
                    $dagSelect
                );

                $html .=
                    RCView::div(array('id'=>'dag-switcher-current-dag-block', 'class'=>'blue py-2 pr-1 fs13 clearfix', 'style'=>'margin-left:-20px;max-width:100%;text-indent:-11px;padding-left:30px;'),
						RCView::div(array('class'=>'float-left', 'style'=>'margin-top:1px;'),
							RCView::fa('fas fa-info-circle fs15 mr-1') .
							$pageBlockTextPre.
							RCView::b(array('class'=>'ml-2 mr-4'), $currentDagName)
                        ) .
						RCView::div(array('class'=>'float-left'), $changeButton)
                    );

                addLangToJS(array('data_access_groups_15', 'global_53', 'data_access_groups_17'));
        }
        return $html;
	}


	/**
	 * Switch current user to the dag id provided
	 * @param string $newDag id or unique name of DAG to switch to
	 * @return string New DAG id on successful switch, error message on fail
	 */
	public function switchToDAG($newDag) {
			
			$userDags = $this->getUserDAGs($this->user);

			$projDags = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)REDCap::getGroupNames(true);
			
			if (!is_int($newDag)) {
					foreach ($projDags as $id => $uname) {
							if ($uname===$newDag) {
									$newDag = $id;
									break;
							}
					}
			}
			
			if (!array_key_exists($newDag, $projDags)) { return 'ERROR: Invalid DAG'; }
			if (!array_key_exists($this->user, $userDags)) { return 'ERROR: Invalid user'; }
			if (!in_array(($newDag == '0' ? null : $newDag), $userDags[$this->user])) { return 'ERROR: User/DAG assignment not permitted'; }

			if ($newDag == 0) {
					$newDagVal = "NULL";
					$logging_msg = "Remove user from data access group";
					$group_name = $projDags[$this->user_rights['group_id']]; // group removing _from_ i.e. to "No assignment"
			} else {
					$newDagVal = $newDag;
					$logging_msg = "Assign user to data access group";
					$group_name = $projDags[$newDag];
			}
			$sql = "update redcap_user_rights set group_id = ".db_escape($newDagVal)." where username = '".db_escape($this->user)."' and project_id = ".db_escape($this->project_id);
			$q = db_query($sql);

			if ($q) {
					Logging::logEvent($sql,"redcap_user_rights","MANAGE",$this->user,"user = '$this->user',\ngroup = '" . $group_name . "'",$logging_msg);
					return '1';
			}
		    return 'ERROR: Could not update user rights!';
	}
	
	/**
	 * Print DAG Switcher JavaScript code to User Rights page.
	 * Users' current DAG display is augmented to indicate where user may 
	 * switch to other DAGs.
	 */
	public function includeUserRightsPageJs()
    {
        $userDags = $this->getUserDAGs();
		if (empty($userDags)) return;
        $dagNames = array(0=>$this->lang['data_access_groups_ajax_23']) + (array)REDCap::getGroupNames(false);
        $dagNames = array_map('htmlentities', $dagNames); // encode quotes etc. in dag names
        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                var userDags = JSON.parse('<?php echo json_encode($userDags);?>');
                var dagNames = JSON.parse('<?php echo json_encode($dagNames, JSON_HEX_APOS);?>');
                DAG_Switcher_User_Rights.makePopovers(userDags, dagNames);
                DAG_Switcher_User_Rights.activatePopovers();
            });
        </script>
        <?php
	}
	
	/**
	 * value_compare_func
	 * Can't get asort($users, SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
	 * to sort user and dag names in natural, case insensitive, order, so 
	 * using user sort, uasort(), with this as compare function.
	 * @param string $a
	 * @param string $b
	 * @return string
	 */
	private static function value_compare_func(string $a, string $b) {
			return strcmp(strtolower($a), strtolower($b));
	}
}