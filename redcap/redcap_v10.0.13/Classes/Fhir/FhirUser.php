<?php
namespace Vanderbilt\REDCap\Classes\Fhir;

use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartRevision;

class FhirUser implements \JsonSerializable
{

    /**
     * All user information
     *
     * @var object
     */
    private $info;
    
    /**
     * user ID
     *
     * @var int
     */
    public $id;
    
    /**
     * username
     *
     * @var string
     */
    public $username;

    /**
     * email
     *
     * @var string
     */
    public $user_email;

    /**
     * first name
     *
     * @var string
     */
    public $user_firstname;

    /**
     * last name
     *
     * @var string
     */
    public $user_lastname;

    /**
     * determine if user is administrator
     *
     * @var bool
     */
    public $super_user;

    /**
     * privilege to repeat an already executed revision
     *
     * @var bool
     */
    public $can_repeat_revision = false;
    
    /**
     * privilege to create a new revision (beside the first one)
     * when a project has not yet been created, the (standard) user can submit a revision for approval
     *
     * @var bool
     */
    public $can_create_revision = true; // the user can always create the first revision for a project

    /**
     * privilege to use datamart functionalities
     *
     * @var boolean
     */
    public $can_use_datamart = false;

    /**
     * create a FhirUser
     *
     * @param int|string $id can be a username or the ui_id
     * @param int $project_id
     */
    function __construct($id, $project_id=null)
    {
        global $fhir_standalone_authentication_flow;
        // If this is the cron job, then there is no real user, so allow all
        if (defined("CRON")) {
            $this->can_repeat_revision = true;
            $this->can_create_revision = true;
            $this->can_use_datamart = true;
            return;
        }

        // Get user info
        $this->info = $this->getUserInfo($id);
        $this->id = $this->info->ui_id;
        $this->username = $this->info->username;
        $this->user_email = $this->info->user_email;
        $this->user_firstname = $this->info->user_firstname;
        $this->user_lastname = $this->info->user_lastname;
        $this->super_user = boolval($this->info->super_user);
        $this->can_use_datamart = boolval($this->info->super_user || $this->info->fhir_data_mart_create_project);

        if ($project_id) {
            
            // check the project settings if a project is specified
            $project = new \Project($project_id);
            $projectInfo = $project->project;
            if ($projectInfo) {
                $this->can_repeat_revision = ($this->super_user || boolval($projectInfo['datamart_allow_repeat_revision']));
                // get active revisions for this project; a user can always create a revision if there are no active revisions
                $activeRevision = DataMartRevision::getActive($project_id);
                $this->can_create_revision = ((!$activeRevision && $this->can_use_datamart) || $this->super_user || boolval($projectInfo['datamart_allow_create_revision']));
            }
        } else if ($this->super_user) {
            // if no project is specified give maximum privileges to super users
            $this->can_repeat_revision = true;
            $this->can_create_revision = true;
        }

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
     * check for a valid access token
     * the access token could be owned by another user 
     * 
     * @return boolean
     */
    public function hasValidToken()
    {
        $user_id = $this->info->ui_id;
        $query_string = sprintf(
            'SELECT * FROM redcap_ehr_access_tokens
            WHERE
            (
                #access token not expired
                (access_token IS NOT NULL AND expiration >"%1$s")
                OR
                #has refresh token and is not older than 30 days
                (refresh_token IS NOT NULL AND expiration > DATE_SUB("%1$s", INTERVAL 30 DAY))
            )',
            NOW
        );
        if($user_id) $query_string .= sprintf(" AND token_owner=%u", db_real_escape_string($user_id));
        $query_string .= " ORDER BY expiration DESC";
        $result = db_query($query_string);
        if(!$result) return false;
        return db_num_rows($result) > 0;
    }

    /**
     * Returns data which can be serialized
     * this format is used in the client javascript app
     *
     * @return array
     */
    public function jsonSerialize() {
        
        $serialized = array(
            'id' => $this->id,
            'username' => $this->username,
            'user_email' => $this->user_email,
            'user_firstname' => $this->user_firstname,
            'user_lastname' => $this->user_lastname,
            'super_user' => $this->super_user,
            'can_repeat_revision' => $this->can_repeat_revision,
            'can_create_revision' => $this->can_create_revision,
            'can_use_datamart' => $this->can_use_datamart,
            'has_valid_access_token' => $this->hasValidToken(),
        );
        return $serialized;
    }

}