<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class siteenvironment extends Model {

    /**
     *@var integer
     */
    protected $xrefid;

    /**
     * @var integer
     */
    protected $siteid;

    /**
     * @var integer
     */
    protected $environmentid;

    /**
     * @var timestamp with timezone
     */
    protected $create_date;
    
    /**
     * @var timestamp with timezone
     */
    protected $update_date;

    /**
     * @var string
     */
    protected $drupalfolderpath;
    
    /**
     * @var integer
     */
    protected $serverid;

    /**
     * @var string
     */
    protected $sitedomainname;

    /**
     * @var string
     */
    protected $drup_dbname;

    /**
     * @var string
     */
    protected $dbconnection_string;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var integer
     */
    protected $git_branch_id;

    /**
     * @var boolean
     */
    protected $database_uses_global_password;

    /**
     * @var integer
     */
    protected $database_id;

    /**
     * @var integer
     */
    protected $gitid;

    /**
     * @var string 
     */
    protected $site_status;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_xref_site_environment");
    }

    // Validate the xrefid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'xrefid'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_environment table.
    public function setSite($siteid, $environmentid, $drupalfolderpath, $serverid, $sitedomainname, $drup_dbname, $dbconnection_string, $username, $password, $git_branch_id, $database_uses_global_password, $database_id, $gitid, $site_status)
    {
        $this->siteid = $siteid;
	$this->environmentid = $environmentid;
	$this->drupalfolderpath = $drupalfolderpath;
        $this->serverid = $serverid;
	$this->sitedomainname = $sitedomainname;
        $this->dbconnection_string = $dbconnection_string;
	$this->drup_dbname = $drup_dbname;
        $this->username = $username;
        $this->password = $password;
        $this->git_branch_id = $git_branch_id;
        $this->database_uses_global_password = $database_uses_global_password;
        $this->database_id = $database_id;
        $this->gitid = $gitid;
        $this->site_status = $site_status;
        
        if ($this->validation()) {
            $this->save();
        }
        return $this->xrefid;
    }

    // Update the epm_environment table
    public function updateSite($xrefid, $siteid, $environmentid, $drupalfolderpath, $serverid, $sitedomainname, $dbconnection_string, $drup_dbname, $username, $password, $git_branch_id, $database_uses_global_password, $database_id, $gitid, $site_status)
    {
        if ($xrefid != null) {
            $siteenvitem = siteenvironment::find($xrefid);
            if ($siteenvitem != null) { 
                $siteenvitem->update(Array("siteid" => $siteid, "environmentid" => $environmentid, "drupalfolderpath" => $drupalfolderpath, "serverid" => $serverid, "sitedomainname" => $sitedomainname, "dbconnection_string" => $dbconnection_string, "drup_dbname" => $drup_dbname, "username" => $username, "password" => $password, "git_branch_id" => $git_branch_id, "database_uses_global_password" => $database_uses_global_password, "database_id" => $database_id, "gitid" => $gitid, "site_status" => $site_status));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $xrefid;
    }

    // Update site status
    public function updateSiteStatus($xrefid, $site_status)
    {
        if ($xrefid != null) {
            $siteenvitem = siteenvironment::find($xrefid);
            if ($siteenvitem != null) { 
                $siteenvitem->update(Array("site_status" => $site_status));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $xrefid;
    }
    // Update site status
    public function updateGitData($siteid, $environmentid, $gitid, $git_branch_id)
    {
        if ($siteid != null) {
            $siteenvitem = siteenvironment::findFirst(array("siteid='$siteid' AND environmentid='$environmentid'"));
            if ($siteenvitem != null) { 
                $siteenvitem->update(Array("gitid" => $gitid, "git_branch_id" => $git_branch_id));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $siteid;
    }

    public function getSiteEnvironmentId($siteid, $environmentid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid' AND environmentid='$environmentid'"));
        If ($robot!= null) {
            $xrefid = $robot->xrefid;
            return $xrefid;
        } else {
            return null;
        }
    }

    public function getEnvironmentId($siteid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $environmentid = $robot->environmentid;
            return $environmentid;
        } else {
            return null;
        }
    }

    public function getSiteUrl($siteid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $sitedomainname = $robot->sitedomainname;
            return $sitedomainname;
        } else {
            return null;
        }
    }

    public function getSiteDrupalPath($siteid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $drupalfolderpath = $robot->drupalfolderpath;
            return $drupalfolderpath;
        } else {
            return null;
        }
    }

    public function getXrefID($siteid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $xrefid = $robot->xrefid;
            return $xrefid;
        } else {
            return null;
        }
    }

    public function getDrupDBName($siteid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $drup_dbname = $robot->drup_dbname;
            return $drup_dbname;
        } else {
            return null;
        }
    }
    public function getXrefSiteData($siteid,$environmentid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid' AND environmentid='$environmentid'"));
        If ($robot!= null) {
            $return_xrefsite = [];
            $return_xrefsite['xrefid'] = trim($robot->xrefid);
            $return_xrefsite['environmentid'] = trim($robot->environmentid);
            $return_xrefsite['drupalfolderpath'] = trim($robot->drupalfolderpath);
            $return_xrefsite['serverid'] = trim($robot->serverid);
            $return_xrefsite['sitedomainname'] = trim($robot->sitedomainname);
            $return_xrefsite['drup_dbname'] = trim($robot->drup_dbname);
            $return_xrefsite['dbconnection_string'] = trim($robot->dbconnection_string);
            $return_xrefsite['username'] = trim($robot->username);
            $return_xrefsite['password'] = trim($robot->password);
            $return_xrefsite['git_branch_id'] = trim($robot->git_branch_id);
            $return_xrefsite['database_uses_global_password'] = trim($robot->database_uses_global_password);
            $return_xrefsite['database_id'] = trim($robot->database_id);
            $return_xrefsite['gitid'] = trim($robot->gitid);
            $return_xrefsite['site_status'] = trim($robot->site_status);
            $return_xrefsite['db_username_secondary'] = trim($robot->db_username_secondary);
            $return_xrefsite['db_password_secondary'] = trim($robot->db_password_secondary);
            return $return_xrefsite;
        } else {
            return null;
        }
    }

    public function checkSiteUrl($sitedomainname)
    {
        $conditions = ['sitedomainname'=>$sitedomainname, 'site_status'=>'Completed'];
        $robots = siteenvironment::findFirst([
            'conditions' =>'sitedomainname=:sitedomainname: AND site_status=:site_status:',
            'bind' => $conditions,
        ]);
        If ($robots!= null || !empty($robots)) {
                $siteid = $robots->siteid;
                return $siteid;
        } else {
            return null;
        }
    }

    public function getBranchId($siteid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $git_branch_id = $robot->git_branch_id;
            return $git_branch_id;
        } else {
            return null;
        }
    }

    public function getSiteEnvData($siteid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid'"));
        If ($robot!= null) {
            $return_xrefsite = [];
            $return_xrefsite['xrefid'] = trim($robot->xrefid);
            $return_xrefsite['siteid'] = trim($robot->siteid);
            $return_xrefsite['environmentid'] = trim($robot->environmentid);
            $return_xrefsite['create_date'] = trim($robot->create_date);
            $return_xrefsite['drupalfolderpath'] = trim($robot->drupalfolderpath);
            $return_xrefsite['serverid'] = trim($robot->serverid);
            $return_xrefsite['sitedomainname'] = trim($robot->sitedomainname);
            $return_xrefsite['drup_dbname'] = trim($robot->drup_dbname);
            $return_xrefsite['dbconnection_string'] = trim($robot->dbconnection_string);
            $return_xrefsite['username'] = trim($robot->username);
            $return_xrefsite['password'] = trim($robot->password);
            $return_xrefsite['git_branch_id'] = trim($robot->git_branch_id);
            $return_xrefsite['database_uses_global_password'] = trim($robot->database_uses_global_password);
            $return_xrefsite['database_id'] = trim($robot->database_id);
            $return_xrefsite['gitid'] = trim($robot->gitid);
            $return_xrefsite['update_date'] = trim($robot->update_date);
            $return_xrefsite['site_status'] = trim($robot->site_status);
            $return_xrefsite['db_username_secondary'] = trim($robot->db_username_secondary);
            $return_xrefsite['db_password_secondary'] = trim($robot->db_password_secondary);
            return $return_xrefsite;
        } else {
            return null;
        }
    }

    // Update site status
    public function updateDBUser($siteid, $dbuser, $dbpassword)
    {
        if ($siteid != null) {
            $siteenvitem = siteenvironment::findFirst(array("siteid='$siteid'"));
            if ($siteenvitem != null) { 
                $siteenvitem->update(Array("username" => $dbuser, "password" => $dbpassword));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $siteid;
    }
	
	public function deleteRecordBySiteIdAndEnvironmentId($siteid,$environmentid){
		
        $robot = siteenvironment::findFirst(array("siteid='$siteid' AND environmentid='$environmentid'"));
        If ($robot!= null) {
			$robot->delete();
		}
	}
}
?>
