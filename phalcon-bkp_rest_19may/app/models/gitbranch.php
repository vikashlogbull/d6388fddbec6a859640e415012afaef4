<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class gitbranch extends Model {

    /**
     * @var integer
     */
    protected $git_id;

    /**
     * @var integer 
     */
    protected $id;

    /**
     * @var string
     */
    protected $branch_name;

    /**
     * @var timestamp with timezone
     */
    protected $created_date; 

    /**
     * @var timestamp with timezone
     */
    protected $updated_date;

    /**
     * @var integer
     */
    protected $connected_to;

    /**
     * @var string
     */
    protected $git_branch_status;

    /**
     * @var boolean
     */
    protected $is_private_master;
    /**
     * @var string
     */
    protected $hook_is_set;
    /**
     * @var string
     */
    protected $hook_url_hash;
    /**
     * @var string
     */
    protected $hookid;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_git_branch");
    }

    // Validate the siteid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'id'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_git table.
    public function setGitBranch($git_id, $branch_name, $connected_to, $git_branch_status, $is_private_master)
    {
        $this->git_id = $git_id;
        $this->branch_name = $branch_name;
        $this->connected_to = $connected_to;
        $this->git_branch_status = $git_branch_status;
        $this->is_private_master= $is_private_master;

        if ($this->validation()) {
            $this->save();
        }
        return $this->id;
    }

    public function getGitBranchData($id)
    {
        $robot = gitbranch::findFirst("id='$id'");
        If ($robot!= null) {
            $git_id = $robot->git_id;
            $git_branch_status = $robot->git_branch_status;
            $branch_name = $robot->branch_name;
            $connected_to = $robot->connected_to;
            return Array('git_id'=>trim($git_id), 'git_branch_status'=>trim($git_branch_status), 'branch_name'=> trim($branch_name), 'connected_to'=> trim($connected_to));
        } else {
            return null;
        }
    }

    // Update the epm_sites table status
    public function updateGitBranchReference($connected_to, $id)
    {   
        if ($id != null) {
            $gititem = gitbranch::findFirst(array("id='$id'"));
            if ($gititem != null) { 
                $gititem->update(Array("connected_to" => $connected_to));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $id;
    }

    public function updateGitBranchStatus($git_branch_id, $git_branch_status)
    {
        if ($git_branch_id != null) {
            $gititem = gitbranch::findFirst("id='$git_branch_id'");
            if ($gititem != null) {
                $gititem->update(Array("git_branch_status" => $git_branch_status));
            } else {
                return null;
            }
        } else {
            return null;
        }
        return $gititem;
    }

    public function updateGitBranchHookDetails($git_branch_id, $hook_is_set, $hook_url_hash, $hookid)
    {
        if ($git_branch_id != null) {
            $gititem = gitbranch::findFirst("id='$git_branch_id'");
            if ($gititem != null) {
                $gititem->update(Array("hook_is_set" => $hook_is_set, "hook_url_hash" => $hook_url_hash, "hookid" => $hookid));
            } else {
                return null;
            }
        } else {
            return null;
        }
        return $gititem;
    }

    public function getGitBranchAllData($git_branch_id)
    {
        $robot = gitbranch::findFirst("id='$git_branch_id'");
        if ($robot!= null) {
            $return_gitbranch = [];
            $return_gitbranch['id'] = trim($robot->id);
            $return_gitbranch['git_id'] = trim($robot->git_id);
            $return_gitbranch['branch_name'] = trim($robot->branch_name);
            $return_gitbranch['created_date'] = trim($robot->created_date);
            $return_gitbranch['updated_date'] = trim($robot->updated_date);
            $return_gitbranch['connected_to'] = trim($robot->connected_to);
            $return_gitbranch['git_branch_status'] = trim($robot->git_branch_status);
            $return_gitbranch['is_private_master'] = trim($robot->is_private_master);
            $return_gitbranch['hook_is_set'] = trim($robot->hook_is_set);
            $return_gitbranch['hook_url_hash'] = trim($robot->hook_url_hash);
            $return_gitbranch['hookid'] = trim($robot->hookid);
            $return_gitbranch['hook_create_date'] = trim($robot->hook_create_date);
            return $return_gitbranch;
        } else {
            return null;
        }
    }
}
?>
