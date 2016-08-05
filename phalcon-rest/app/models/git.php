<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class git extends Model {

    /**
     * @var integer
     */
    protected $git_id;

    /**
     * @var string
     */
    protected $companyid;

    /**
     * @var string
     */
    protected $git_location;

    /**
     * @var string
     */
    protected $git_url;

    /**
     * @var string
     */
    protected $git_ssh;

    /**
     * @var string
     */
    protected $git_username;

    /**
     * @var string
     */
    protected $git_password;

    /**
     * @var string
     */
    protected $createdatetime;

    /**
     * @var string
     */
    protected $lastupdatedate;

    /**
     * @var string
     */
    protected $password_needs_update;

    /**
     * @var string
     */
    protected $github_token;

    /**
     * @var string
     */
    protected $repo_access_type;

    /**
     * @var string
     */
    protected $repo_name;

    /**
     * @var string
     */
    protected $connected_to;

    /**
     * @var string
     */
    protected $repo_visibility;

    /**
     * @var string
     */
    protected $hook_is_set;

    /**
     * @var string
     */
    protected $repo_path;


    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_git");
    }

    // Validate the gitid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'git_id'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_git table.
    public function setGit($companyid, $git_location, $git_url, $git_ssh, $git_username, $git_password, $password_needs_update, $github_token, $repo_access_type, $repo_name, $connected_to, $repo_visibility, $hook_is_set, $repo_path)
    {
        $this->companyid = $companyid;
        $this->git_location = $git_location;
        $this->git_url = $git_url;
        $this->git_ssh = $git_ssh;
        $this->git_username = $git_username;
        $this->git_password = $git_password;
        $this->password_needs_update = $password_needs_update;
        $this->github_token = $github_token;
        $this->repo_access_type = $repo_access_type;
        $this->repo_name = $repo_name;
        $this->connected_to = $connected_to;
        $this->repo_visibility = $repo_visibility;
        $this->hook_is_set = $hook_is_set;
        $this->repo_path = $repo_path;

        if ($this->validation()) {
            $this->save();
        }
        return $this->git_id;
    }

    public function getGitData($gitid)
    {
        $robot = git::findFirst("git_id='$gitid'");
        If ($robot!= null) {
            $git_url = $robot->git_url;
            $git_username = $robot->git_username;
            $github_token = $robot->github_token;
            return Array('git_url'=>trim($git_url), 'git_username'=>trim($git_username), 'github_token'=> trim($github_token));
        } else {
            return null;
        }
    }

    public function getGitDatabyCompanyId($companyid)
    {
        $robot = git::findFirst("companyid='$companyid'");
        If ($robot!= null) {
            $git_id = $robot->git_id;
            return $git_id;
        } else {
            return null;
        }
    }

}
?>
