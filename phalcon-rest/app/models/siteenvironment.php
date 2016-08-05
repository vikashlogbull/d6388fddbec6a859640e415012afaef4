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

    public function getSiteEnvironmentId($siteid, $environmentid)
    {
        $robot = siteenvironment::findFirst(array("siteid='$siteid'","environmentid='$environmentid'"));
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
}
?>
