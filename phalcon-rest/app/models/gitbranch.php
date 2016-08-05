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
    public function setGitBranch($git_id, $branch_name, $connected_to, $git_branch_status)
    {
        $this->git_id = $git_id;
        $this->branch_name = $branch_name;
        $this->connected_to = $connected_to;
        $this->git_branch_status = $git_branch_status;

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
            $gititem = gitbranch::find($id);
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

}
?>
