<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class subsiteenv extends Model {

    /**
     *@var integer
     */
    protected $id; 

    /**
     *@var integer
     */
    protected $subsite_id; 

    /**
     *@var integer
     */
    protected $environment_id;

    /**
     *@var string
     */
    protected $subsite_path;

    /**
     *@var integer
     */
    protected $serverid;

    /**
     *@var string
     */
    protected $subsite_domain_name;

    /**
     *@var integer
     */
    protected $database_id;

    /**
     *@var string
     */
    protected $database_name;

    /**
     *@var string
     */
    protected $database_username;

    /**
     *@var string
     */
    protected $database_password;

    /**
     *@var integer
     */
    protected $git_branch_id;

    /**
     *@var timezone with timestamp
     */
    protected $created_date;

    /**
     *@var timezone with timestamp
     */
    protected $updated_date;

    /**
     *@var boolean
     */
    protected $database_uses_global_password;

    /**
     *@var boolean
     */
    protected $using_separate_branch;

    /**
     *@var integer
     */
    protected $gitid;

    /**
     *@var boolean
     */
    protected $using_own_db;

    /**
     *@var string
     */
    protected $subsite_status;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_xref_subsite_environment");
    }

    // Validate the xrefid
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

    // Insert the data on epm_environment table.
    public function setSubSiteEnv($subsite_id, $environment_id, $subsite_path, $serverid, $subsite_domain_name, $database_id, $database_name, $database_username, $database_password, $git_branch_id, $database_uses_global_password, $using_separate_branch, $gitid, $using_own_db, $subsite_status)
    {
        $this->subsite_id = $subsite_id;
        $this->environment_id = $environment_id; 
        $this->subsite_path = $subsite_path; 
        $this->serverid = $serverid; 
        $this->subsite_domain_name = $subsite_domain_name; 
        $this->database_id = $database_id; 
        $this->database_name = $database_name; 
        $this->database_username = $database_username; 
        $this->database_password = $database_password; 
        $this->git_branch_id = $git_branch_id; 
        $this->database_uses_global_password = $database_uses_global_password; 
        $this->using_separate_branch = $using_separate_branch; 
        $this->gitid = $gitid; 
        $this->using_own_db = $using_own_db; 
        $this->subsite_status = $subsite_status; 
        
        if ($this->validation()) {
            $this->save();
        }
        return $this->id;
    }

    // Update the epm_environment table
    public function updateSubSite($id, $siteid, $environmentid, $drupalfolderpath, $serverid, $sitedomainname, $dbconnection_string, $drup_dbname, $username, $password, $git_branch_id, $database_uses_global_password, $database_id, $gitid, $using_own_db, $subsite_status)
    {
        if ($id != null) {
            $siteenvitem = subsiteenv::find($id);
            if ($siteenvitem != null) { 
                $siteenvitem->update(Array("siteid" => $siteid, "environmentid" => $environmentid, "drupalfolderpath" => $drupalfolderpath, "serverid" => $serverid, "sitedomainname" => $sitedomainname, "dbconnection_string" => $dbconnection_string, "drup_dbname" => $drup_dbname, "username" => $username, "password" => $password, "git_branch_id" => $git_branch_id, "database_uses_global_password" => $database_uses_global_password, "database_id" => $database_id, "gitid" => $gitid, "using_own_db" => $using_own_db, "subsite_status" => $subsite_status));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $id;
    }

    public function getSiteEnvironmentId($siteid, $environmentid)
    {
        $robot = subsiteenv::findFirst(array("siteid='$siteid'","environmentid='$environmentid'"));
        If ($robot!= null) {
            $id = $robot->id;
            return $id;
        } else {
            return null;
        }
    }

    public function getEnvironmentIdSite($siteid)
    {
        $robot = subsiteenv::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $environmentid = $robot->environment_id;
            return $environmentid;
        } else {
            return null;
        }
    }

    public function getEnvironmentId($subsiteid)
    {
        $robot = subsiteenv::findFirst(array("subsite_id='$subsiteid'"));
        If ($robot!= null) {
            $environmentid = $robot->environment_id;
            return $environmentid;
        } else {
            return null;
        }
    }

    public function getSiteUrl($siteid)
    {
        $robot = subsiteenv::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $sitedomainname = $robot->sitedomainname;
            return $sitedomainname;
        } else {
            return null;
        }
    }

    public function getSiteDrupalPath($siteid)
    {
        $robot = subsiteenv::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $drupalfolderpath= $robot->drupalfolderpath;
            return $drupalfolderpath;
        } else {
            return null;
        }
    }

    public function updateSubSiteStatus($id, $subsite_status)
    {
        $robot = subsiteenv::findFirst("id='$id'");
        If ($robot!= null) {
            $robot->update(Array("subsite_status" => $subsite_status));
            return $id;
        } else {
            return null;
        }
    }

    public function updateSubSiteEnvStatus($subsiteid, $subsite_status)
    {
        $robot = subsiteenv::findFirst("subsite_id='$subsiteid'");
        If ($robot!= null) {
            $robot->update(Array("subsite_status" => $subsite_status));
            return $subsiteid;
        } else {
            return null;
        }
    }

    public function getSubSiteUrl($subsiteid)
    {
        $robot = subsiteenv::findFirst(array("subsite_id='$subsiteid'"));
        if($robot!=null){
            $subsite_domain_name = $robot->subsite_domain_name;
            return $subsite_domain_name;
        }else{
            return null;
        }
    }

    public function getXrefSubSiteData($subsite_id)
    {
        $robot = subsiteenv::findFirst(array("subsite_id='$subsite_id'"));
        if ($robot!= null) {
            $return_xrefsubsite = [];
            $return_xrefsubsite['id'] = trim($robot->id);
            $return_xrefsubsite['subsite_path'] = trim($robot->subsite_path);
            $return_xrefsubsite['serverid'] = trim($robot->serverid);
            $return_xrefsubsite['subsite_domain_name'] = trim($robot->subsite_domain_name);
            $return_xrefsubsite['database_id'] = trim($robot->database_id);
            $return_xrefsubsite['database_name'] = trim($robot->database_name);
            $return_xrefsubsite['database_username'] = trim($robot->database_username);
            $return_xrefsubsite['database_password'] = trim($robot->database_password);
            $return_xrefsubsite['git_branch_id'] = trim($robot->git_branch_id);
            $return_xrefsubsite['database_uses_global_password'] = trim($robot->database_uses_global_password);
            $return_xrefsubsite['using_separate_branch'] = trim($robot->using_separate_branch);
            $return_xrefsubsite['gitid'] = trim($robot->gitid);
            $return_xrefsubsite['using_own_db'] = trim($robot->using_own_db);
            $return_xrefsubsite['db_username_secondary'] = trim($robot->db_username_secondary);
            $return_xrefsubsite['db_password_secondary'] = trim($robot->db_password_secondary);
            return $return_xrefsubsite;
        } else {
            return null;
        }   
    }

    public function checkSubSiteUrl($subsite_domain_name)
    {
        $conditions = ['subsite_domain_name'=>$subsite_domain_name, 'subsite_status'=>'Completed'];
        $robot = subsiteenv::findFirst([
            'conditions' =>'subsite_domain_name=:subsite_domain_name: AND subsite_status=:subsite_status:',
            'bind' => $conditions,
        ]);
        If ($robot!= null) {
            $subsite_id = $robot->subsite_id;
            return $subsite_id;
        } else {
            return null;
        }   
    }

    // Update subsite DB User and Password
    public function updateDBUser($subsite_id, $database_username, $database_password)
    {
        if ($subsite_id != null) {
            $siteenvitem = subsiteenv::findFirst(array("subsite_id='$subsite_id'"));
            if ($siteenvitem != null) { 
                $siteenvitem->update(Array("database_username" => $database_username, "database_password" => $database_password));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $subsite_id;
    }
}
?>
