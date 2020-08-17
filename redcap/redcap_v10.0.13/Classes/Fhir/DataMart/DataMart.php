<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart
{
    use Vanderbilt\REDCap\Classes\Fhir\FhirUser;

    class DataMart
    {
        /**
         * datetime in FHIR compatible format
         * https://www.hl7.org/fhir/datatypes.html#dateTime
         */
        const FHIR_DATETIME_FORMAT = "Y-m-d\TH:i:s\Z";

        /**
         * type of request for To-Do List
         *
         * @var string
         */
        const TODO_REQUEST_TYPE = "Clinical Data Mart revision";
        
        /**
         * get revisions and return them as array
         * 
         * @param int $project_id
         * @return DataMartRevision[] 
         */
        public function getRevisions($project_id)
        {
            return DataMartRevision::all($project_id);
        }

        /**
         * get revision using request_id
         * 
         * @param int $request_id
         * @return DataMartRevision
         */
        public function getRevisionFromRequest($request_id)
        {
            return DataMartRevision::getRevisionFromRequest($request_id);
        }

        /**
         * get a revision by id
         *
         * @return DataMartRevision
         */
        public function getRevision($id)
        {
            return DataMartRevision::get($id);
        }

        /**
         * get the FHIR metadata fields and render them in a tree structure (DataMartExternalFieldNode)
         *
         * @return DataMartExternalFieldNode
         */
        public function getDataMartExternalFieldNode()
        {
            $external_fields = \DynamicDataPull::getFhirMetadata();
            $root = new DataMartExternalFieldNode('root');
            foreach ($external_fields as $key => $field) {
                $category = $field['category'] ?: false;
                $subcategory = $field['subcategory'] ?: false;
                $containers = array();
                if(!empty($category)) $containers[] = $category;
                if(!empty($subcategory)) $containers[] = $subcategory;
                $node = new DataMartExternalFieldNode($key,  $field);
                $root->addChild($node, $containers);
            }
            // save data to cache
            return $root;
        }

        /**
         * get time (in seconds) elapsed since
         * teh alst time a file was modified
         *
         * @param [type] $filename
         * @return void
         */
        private function getElapsedTimeSinceLastModified($filename)
        {
            if(!file_exists($filename)) return false;
            $last_modified_time = filemtime($filename);
            return time() - $last_modified_time;
        }

        /**
         * proxy to get the FHIR metadata fields and render them in a tree structure (DataMartExternalFieldNode)
         * serve a cached version if available and if the metadata file has not been modified recently
         *
         * @return DataMartExternalFieldNode
         */
        public function getExternalFields()
        {
            $max_life_time = 60*60*1;
            $redcap_version = defined('REDCAP_VERSION') ? sprintf("_%s", REDCAP_VERSION) : '';
            $cache_file_name = "datamart_fields{$redcap_version}.json";
            $cache_file_path = \FileManager::getCachedFilePath($cache_file_name);
            $cache_file_lifespan = $this->getElapsedTimeSinceLastModified($cache_file_path);
            $cache_file = \FileManager::getCachedFile($cache_file_name);
            // use cached version for at least 1 hours
            if($cache_file && $cache_file_lifespan < $max_life_time) {
                $metadata_csv_filepath = APP_PATH_DOCROOT . "Resources/misc/redcap_fhir_metadata.csv";
                $metadata_lifespan = $this->getElapsedTimeSinceLastModified($metadata_csv_filepath);
                // check if metadata file has been modified recently
                if($metadata_lifespan>$cache_file_lifespan) {
                    // $cache_file_content = file_get_contents($cache_file_path);
                    // Serve from the cache if it is younger than $cachetime
                    return json_decode($cache_file);
                }
            }
            $root = $this->getDataMartExternalFieldNode();
            // save data to cache
            \FileManager::cacheFile($cache_file_name, json_encode($root));
            return $root;
        }

        /**
         * get info for the instance project
         *
         * @param int $project_id
         * @return object
         */
        public function getProjectInfo($project_id)
        {
            $db = new \RedCapDB();
            return $db->getProject($project_id);
        }

        /**
         * return info and privileges of a user
         *
         * @return array
         */
        public function getUser($username=null, $project_id=null)
        {        
            return new FhirUser($username, $project_id);
        }

        /**
         * get user information
         *
         * @param int|string $id can be a username or the ui_id
         * @return object
         */
        private function getUserInfo($id)
        {
            $userInfo = intval($id) ? \User::getUserInfoByUiid($id) : \User::getUserInfo($id);
            return (object)$userInfo;
        }

        /**
         * get REDCap configuration values
         *
         * @return void
         */
        private function getConfigVals()
        {
            $config_vals = \System::getConfigVals();
        }

        /**
         * delete a revision
         *
         * @param int $revision_id
         * @return void
         */
        public function deleteRevision($revision_id)
        {
            global $userid;
            
            $userInfo = $this->getUserInfo($userid);
            $superUser = $userInfo->super_user==='1';
            if($superUser) {
                $revision = DataMartRevision::get($revision_id);
                if(!$revision) return false; // no revision found
                $revision->delete();
                // archive any pending revision request
                $this->archivePendingRequest($revision);
                // return $revision;
                return true;
            }else {
                return false;
            }
        }



        /**
         * Undocumented function
         *
         * @param integer $revision_id
         * @param array $fields associative array with keys to be exported
         * @param string $format 
         * @param string $csv_delimiter
         * @return void
         */
        public function exportRevision($revision_id, $fields=array(), $format='csv', $csv_delimiter=",")
        {
            $revision = DataMartRevision::get($revision_id);
            $data = $revision->getData();
            $dataString = array();
            foreach($data as $key => $value)
            {
                // check if the key must be exported
                if(!in_array($key, $fields)) continue;

                $toString = is_array($value) ? implode(' ', $value) : strval($value);
                $dataString[$key] = $toString;
            }
            $filename = sprintf('datamart_revision_%u', $revision_id);
            if($format=='csv')
            {
                \FileManager::exportCSV(array($dataString), $filename, $extract_headers=true,$delimiter=$csv_delimiter,$enclosure='"' );
            }else if($format=='json')
            {
                \FileManager::exportJSON($data, $filename);
            }
        }

        /**
         * parse a file for a revision.
         * 
         * automatically detect file type (json or CSV)
         * if file type is CSV it can automatically detect the delimiter
         *
         * @param array $file
         * @return array
         */
        public function importRevision($file)
        {
            // $file_type = $file['type'];
            $file_name = $file['name'];
            $file_path = $file['tmp_name'];
            $file_info = pathinfo($file_name);
            $file_extension = $file_info['extension'];
            // check file extension
            switch ($file_extension) {
                case 'json':
                    $file_content = file_get_contents($file_path);
                    $data = json_decode($file_content, true);
                    break;
                case 'csv':
                    $rows = \FileManager::readCSV($file_path, $length=0, $delimiter='auto');
                    if(count($rows)>1)
                    {
                        // convert the CSV rows to an associative array using the first line for the keys
                        $data = array_combine($rows[0], $rows[1]);
                        // convert mrns and fields from string to array
                        foreach ($data as $key => $value) {
                            if(in_array($key, array('fields', 'mrns')))
                                $data[$key] = explode(' ', $value);
                        }
                    }
                    break;
                default:
                    $data = array();
                    break;
            }
            return $data;
        }

        /**
         * add a revision to the current data mart project
         * 
         * @param array $settings
         * @return DataMartRevision
         */
        public function addRevision($settings)
        {
            global $userid;

            $project_id = $settings['project_id'];
            $user = new FhirUser($userid, $project_id);
            // check if the user can create a revision
            if(!$user->can_use_datamart || !$user->can_create_revision) return false;

            $settings['user_id'] = $user->id;

            $revision = DataMartRevision::create($settings);
            // automtically approve revision for super users
            if($user->super_user) $revision = $this->approveRevision($revision);

            /**
             * send a revision request if the revision
             * - is assigned to a project 
             * - is not approved
             * 
             * you do no want to create/send a revision request if the project ID is not available
             * because the admin has already been sent a project creation request
             */
            if($revision->project_id && !$revision->approved)
            {
                $response = $this->createRevisionRequest($revision);
                return $response['revision'];
            }
            return $revision;
        }

        /**
         * helper function to get the URL used to access the DataMart app
         */
        private function getDataMartUrl($query_params) {
            $URL = APP_PATH_WEBROOT_FULL . "redcap_v".REDCAP_VERSION."/index.php?route=DataMartController:index";
            $action_url = $URL . '&' . http_build_query($query_params);
            return $action_url;
        }

        /**
         * send an email to the admin when a new revision request is submitted
         *
         * @param DataMartRevision $revision
         * @return bool
         */
        private function sendRevisionRequestEmail($revision)
        {
            global $lang;
            // project information
            $projectInfo = $this->getProjectInfo($revision->project_id);
            // user information
            $user = $revision->getCreator();
            $admin_email = $projectInfo->project_contact_email;
            $user_email = $user->user_email;
            $user_fullname = sprintf("%s %s", $user->user_firstname, $user->user_lastname);
            
            // get url for Revision creation
            $action_url = $this->getDataMartUrl(array(
                'pid' => $revision->project_id,
                'request_id' => $revision->request_id
            ));
            /**
             * send an email
             */
            $email = new \Message();
            $emailSubject = "[REDCap] Request to Approve New Data Mart Revision";
            $email->setFrom($user_email);
            $email->setFromName($GLOBALS['user_firstname']." ".$GLOBALS['user_lastname']);
            $email->setTo($admin_email);

            ob_start();
            ?>
            <html>
                <head><title>$emailSubject</title></head>
                <body style='font-family:arial,helvetica;font-size:10pt;'>
                    <?php echo $lang['global_21'] ?>
                    <br><br>
                    <?php echo $lang['email_admin_03'] ?> <b><?php echo html_entity_decode($user_fullname, ENT_QUOTES) ?></b>
                    (<a href="mailto:<?php echo $user_email ?>"><?php echo $user_email ?></a>)
                    <?php echo $lang['email_admin_21'] ?>:
                    <b> ID <?php echo html_entity_decode($revision->id, ENT_QUOTES) ?></b><?php echo $lang['period'] ?>
                    <br><br>
                    <?php echo $lang['email_admin_05'] ?><br>
                    <a href="<?php echo $action_url ?>"><?php echo $lang['email_admin_22'] ?></a>
                </body>
            </html>
            <?php
            $contents = ob_get_contents();
            ob_end_clean();

            // Finalize email
            $email->setBody($contents);
            $email->setSubject($emailSubject);
            
            return $email->send();
        }

        /**
         * Send a confirmation email to the user that requested a revision approval
         *
         * @param DataMartRevision $revision
         * @return void
         */
        private function sendRevisionApprovedEmail($revision)
        {
            global $lang;
            $projectInfo = $this->getProjectInfo($revision->project_id);
            $project_title = $projectInfo->app_title;
            $user = $revision->getCreator();

            $admin_email = $projectInfo->project_contact_email;
            $user_email = $user->user_email;
            $action_url = $this->getDataMartUrl(array('pid' => $revision->project_id));

            /**
             * send an email
             */
            $email = new \Message();
            $emailSubject = "[REDCap] Data Mart Revision approved";
            $email->setFrom($admin_email);
            $email->setFromName($projectInfo->project_contact_name);
            $email->setTo($user_email);

            ob_start();
            ?>
            <html>
                <head><title>$emailSubject</title></head>
                <body style='font-family:arial,helvetica;font-size:10pt;'>
                    <?php echo $lang['global_21'] ?>
                    <br><br>
                    The Data Mart revision that you requested for your project has been approved:
                    <b><?php echo html_entity_decode($project_title, ENT_QUOTES) ?></b>.
                    <br><br>
                    <a href="<?php echo $action_url ?>" target="_blank">View your new Data Mart Revision</a>
                </body>
            </html>
            <?php
            $contents = ob_get_contents();
            ob_end_clean();
            // Finalize email
            $email->setBody($contents);
            $email->setSubject($emailSubject);
            
            return $email->send();
        }

        /**
         * Send a confirmation email to the user that requested a revision approval
         *
         * @param DataMartRevision $revision
         * @return void
         */
        private function sendRevisionRejectedEmail($revision)
        {
            global $lang;
            $projectInfo = $this->getProjectInfo($revision->project_id);
            $project_title = $projectInfo->app_title;
            $user = $revision->getCreator();

            $admin_email = $projectInfo->project_contact_email;
            $user_email = $user->user_email;
            $action_url = $this->getDataMartUrl(array('pid' => $revision->project_id));

            /**
             * send an email
             */
            $email = new \Message();
            $emailSubject = "[REDCap] Clinical Data Mart Revision rejected";
            $email->setFrom($admin_email);
            $email->setFromName($projectInfo->project_contact_name);
            $email->setTo($user_email);

            ob_start();
            ?>
            <html>
                <head><title>$emailSubject</title></head>
                <body style='font-family:arial,helvetica;font-size:10pt;'>
                    <?php echo $lang['global_21'] ?>
                    <br><br>
                    The Data Mart revision that you requested for your project has been rejected:
                    <b><?php echo html_entity_decode($project_title, ENT_QUOTES) ?></b>.
                    <p>For more info contact your administrator.</p>
                </body>
            </html>
            <?php
            $contents = ob_get_contents();
            ob_end_clean();
            // Finalize email
            $email->setBody($contents);
            $email->setSubject($emailSubject);
            
            return $email->send();
        }


        /**
         * creates revision request that must be approved by an admin
         * - create the request (if the revision has not a request_id aready)
         * - send an email
         *
         * @param DataMartRevision $revision
         * @return DataMartRevision
         */
        public function createRevisionRequest($revision)
        {
            global $send_emails_admin_tasks;

            $projectInfo = $this->getProjectInfo($revision->project_id);

            /**
             * check if the revision has a request_id.
             * only create a ToDoList request if the
             * revision has not been already assigne to one
             */
            if (!$revision->request_id) {
                $request_id = \ToDoList::insertAction(
                    $revision->user_id,
                    $projectInfo->project_contact_email,
                    self::TODO_REQUEST_TYPE,
                    $this->getDataMartUrl(array('pid' => $revision->project_id)), // request_id is automatically appended to end of action URL after insert to keep as a reference during admin processing
                    $revision->project_id
                );
                // update the revision with the request ID
                $revision->setRequestId($request_id);
                $revision->save(); // persist changes to the database
            }
            
            /**
             * send an email to the admin
             */
            \Logging::logEvent("","redcap_data","MANAGE",$revision->project_id,"revision_id = $revision->id","Send request to approve a Clinical Data Mart Revision");
            $response = array();
            if ($send_emails_admin_tasks) $response['email_sent'] = $this->sendRevisionRequestEmail($revision);
            $response['revision'] = $revision;
            $response['request_id'] = $request_id;

            return $response;
        }

        /**
         * get the file content of a file
         * if the file is PHP, it is evaluated
         *
         * @param string $path
         * @return string|false
         */
        private function get_include_contents($path) {
            if (is_file($path)) {
                ob_start();
                include $path;
                $contents = ob_get_contents();
                ob_end_clean();
                return $contents;
            }
            return false;
        }
        
        /**
         * run a revision and fetches data from remote
         *
         * @param int $revision_id
         * @param string $mrn medical record number
         * @return void
         */
        public function runRevision($revision_id, $mrn)
        {
            global $userid;
            $time_start = microtime(true); // track script execution time
            
            $revision = DataMartRevision::get($revision_id);
            if(!is_a($revision, DataMartRevision::class)) throw new \Exception(sprintf("No revision found with the provided ID (%s)", $revision_id), 400);
            $project_id = $revision->project_id;
            $project_mrn_list = $revision->getProjectMrnList();
            // check if MRN exists in the project
            if(!in_array($mrn, $project_mrn_list)) {
                $message = sprintf("The MRN '%s' does not exist in the project ID %u", $mrn, $project_id);
                throw new \Exception($message, 400);
            }
            
            $active_revision = DataMartRevision::getActive($project_id);
            if(!$active_revision) throw new \Exception("There are no active revisions for this project", 400);
            // check if the revision we are trying to run is the active one
            if($revision->id !== $active_revision->id) throw new \Exception("This is not the active revision for this project", 400);
            // cannot run a revision if it is not approved
            if(!$revision->approved) throw new \Exception("This revision has not been approved", 400);

            // check if the user can run repeat revision
            $user = new FhirUser($userid, $project_id);
            if( !$user->can_use_datamart ) throw new \Exception("The user is not allowed to use Data Mart in this project", 400);
            if(!$user->can_repeat_revision)
            {
                $valid_mrns = $revision->getFetchableMrnList();
                $message = sprintf("This revision has already been run by the user %s", $userid);
                if(!in_array($mrn, $valid_mrns)) throw new \Exception($message, 400);
            }

            // Why should we add this here? It's complicated.
            define("CREATE_PROJECT_ODM", true);

            // fetch data
            $fetcher = new DataMartFetcher();
            $fhir_data = $fetcher->fetchData($revision, $mrn);
            try {
                // try to save data to the project using an adapter
                $adapter = new DataMartRecordAdapter($revision);
                $data = $fhir_data->getData(); // extract array with fecthed data
                $stats = $adapter->saveData($mrn, $data); // save data in project
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $fhir_data->addError($message, $e);
            }

            $revision->setExecutionTime();
            $revision->save(); // persist changes to the database
            \Logging::logEvent('', "redcap_ehr_datamart_revisions", "MANAGE", $project_id, sprintf("revision_id = %u", $revision->id), 'Fetch data for Clinical Data Mart');

            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start); // in seconds
            
            // compose the response object
            $response = array(
                'data' => $fhir_data->getData(),
                'errors' => $fhir_data->getErrors(),
                'metadata' => array(
                    'execution_time' => $execution_time,
                    'stats' => $stats ?: array(), // use empty array if no stats are available
                ),
            );
            return $response;
        }

        /**
         * set a ToDoList request as completed
         * and email the user
         *
         * @param DataMartRevision $revision
         * @return void
         */
        private function completePendingRequest($revision)
        {
            if($revision->request_id && $revision->project_id)
            {
                \ToDoList::updateTodoStatusNewProject($revision->request_id, $revision->project_id);
                $this->sendRevisionApprovedEmail($revision);
            }
        }

        /**
         * archive a ToDoList request
         * and email the user
         *
         * @param DataMartRevision $revision
         * @return void
         */
        private function archivePendingRequest($revision)
        {
            if($revision->request_id && $revision->project_id)
            {
                \ToDoList::updateTodoStatus($revision->project_id, self::TODO_REQUEST_TYPE, $status='archived');
                $this->sendRevisionRejectedEmail($revision);
            }
        }

        /**
         * approve a revision and archive the revision request
         *
         * @param DataMartRevision|int $revision
         * @return DataMartRevision|false
         */
        public function approveRevision($revision)
        {
            global $userid;

            
            $userInfo = $this->getUserInfo($userid);
            $superUser = $userInfo->super_user==='1';
            if($superUser) {
                if(!is_a($revision, DataMartRevision::class))
                {
                    $revision = DataMartRevision::get($revision);
                    if(!$revision) return false; // exit if the revision is not found
                }
                $revision->approve();
                // archive any pending revision request
                $this->completePendingRequest($revision);
                $revision->save(); // persist changes to the database
                return $revision;
            }else {
                return false;
            }
        }

        /**
         * check if a project is enabled for datamart
         *
         * @param int $project
         * @return boolean
         */
        public static function isEnabled($project_id)
        {
            $Proj = new \Project($project_id);
            return ($Proj->project['datamart_enabled'] == '1');
        }


        /**
         * check if REDCap has at least 1 active Data Mart project
         *
         * @return boolean
         */
        public static function isEnabledInSystem()
        {
            $query_string = "SELECT project_id, datamart_enabled from redcap_projects
                            WHERE date_deleted IS NULL
                            AND datamart_enabled=1";
            $result = db_query($query_string);
            $total_rows = mysqli_num_rows($result);
            return $total_rows>0;
        }

        
        /**
         * get the record number for an MRN
         * if the record is not found return the first available record_id
         *
         * @param integer $project_id
         * @param string $mrn
         * @return string
         */
        public function getRecordID($project_id, $mrn)
        {
            $project = new \Project($project_id);
            $event_info = (array) $project->eventInfo;
            $events_ids =  array_keys($event_info);
            if(count($events_ids)>0)
                $events_query = ' AND event_id IN ('. implode(', ',$events_ids) . ')';
                
            $query_string = sprintf("SELECT record, value FROM redcap_data
                                        WHERE value='%s'
                                        AND field_name='mrn' %s
                                        AND project_id=%s LIMIT 1",
                                        db_real_escape_string($mrn),
                                        db_real_escape_string($events_query),
                                        db_real_escape_string($project_id));

            $result = db_query($query_string);
            $row = db_fetch_assoc($result);
                
            if($row) return $row['record'];
            return \DataEntry::getAutoId($project_id);
        }

        /**
         * check if a revision is already in the list of the stored revisions
         *
         * @param object $settings
         * @return void
         */
        private function checkForDuplicateRevision($settings)
        {
            $revisions = DataMartRevision::all($this->project->project_id);
            $is_duplicate = false;
            for ($i=0; !$is_duplicate && $i < count($revisions) ; $i++) {
                $revision = $revisions[$i];
                $is_duplicate = $revision->isDuplicate($settings);
            }
            return $is_duplicate;
        }
        
        /**
         * get the most recent, approved, non-deleted revision
         * which belongs to a CRON enabled project
         *
         * @return DataMartRevision[]
         */
        public function getCronEnabledRevisions()
        {
            $query_string = sprintf(
                'SELECT r.id FROM redcap_ehr_datamart_revisions AS r
                # get the most recent, approved, non-deleted revision
                INNER JOIN (
                    SELECT project_id,  MAX(id) AS id FROM redcap_ehr_datamart_revisions
                    WHERE is_deleted=0 GROUP BY project_id
                ) AS latest_ids ON latest_ids.id = r.id
                # only get CRON-enabled projects
                WHERE r.project_id IN (
                    SELECT project_id FROM redcap_projects
                    WHERE datamart_enabled=1 AND datamart_cron_enabled=1
                )
                # only get approved revisions
                AND r.approved=1'
            );
            $result = db_query($query_string);
            $revisions = array();
            while($row = db_fetch_assoc($result))
            {
                if($id = $row['id'])
                    $revisions[] = DataMartRevision::get($id);
            }
            return $revisions;
        }

    }
}