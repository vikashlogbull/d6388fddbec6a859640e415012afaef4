<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class subsite extends Model {

    /**
     * @var integer
     */
    protected $subsiteid;

    /**
     * @var string
     */
    protected $subsitedbdeploymentschema;

    /**
     * @var string
     */
    protected $dbname;

    /**
     * @var string
     */
    protected $environmentid;

    /**
     * @var string
     */
    protected $siteid;

     /**
     * @var string
     */
    protected $subsitename;

     /**
     * @var string
     */
    protected $gitbranchsubsitestatus;

     /**
     * @var string
     */
    protected $file_systempath;

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
     * @var string
     */
    protected $eipaddressserver;

     /**
     * @var string
     */
    protected $subsite_status;

     /**
     * @var timestamp with timezone 
     */
    protected $create_date;

     /**
     * @var timestamp with timezone
     */
    protected $update_date;


    public function initialize()
    {
        $this->setSource("epm_sub_site");
    }

    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'subsiteid'
        )));
    if ($this->validationHasFailed() == true) {
            return false;
        }
        return true;
    }

    public function setSubsite($subsitedbdeploymentschema, $dbname, $environmentid, $siteid, $subsitename, $gitbranchsubsitestatus, $file_systempath, $drup_dbname, $dbconnection_string, $username, $password, $eipaddressserver, $subsite_status)
    {
        $this->subsitedbdeploymentschema = $subsitedbdeploymentschema;
        $this->dbname = $dbname;
	$this->environmentid = $environmentid;
	$this->siteid = $siteid;
	$this->subsitename = $subsitename;
	$this->gitbranchsubsitestatus = $gitbranchsubsitestatus;
	$this->file_systempath = $file_systempath;
	$this->drup_dbname = $drup_dbname;
	$this->dbconnection_string = $dbconnection_string;
	$this->username = $username;
	$this->password = $password;
	$this->eipaddressserver = $eipaddressserver;
	$this->subsite_status = $subsite_status;
        if ($this->validation()) {
            $this->save();
        }
        return $this->subsiteid;
    }

    public function updateSubSite($subsiteid, $subsitename, $file_systempath, $subsite_status)
    {
        if ($subsiteid != null) {
            $subsiteitem = subsite::find($subsiteid);
            if ($subsiteitem != null) { 
                $subsiteitem->update(Array("subsitedbdeploymentschema" => $subsite_status, "subsite_status" => $subsite_status));
            } else {
               return null;
            }
            return $subsiteid;
        }
    }

    public function getSubsite($subsitename)
    {
        $robot = subsite::findFirst("subsitename='$subsitename'");
        If ($robot!= null) {
            $subsiteid = $robot->subsitename;
            return $subsiteid;
        } else {
            return null;
        }
    }

    public function getfilesystempath($subsiteid)
    {
        $robot = subsite::findFirst(array("subsiteid='$subsiteid'"));
        if ($robot!=null) {
            $file_systempath = $robot->file_systempath;
            return $file_systempath;
        }
        else{
            return null;
        }
    }

    public function getDrupDBName($subsiteid)
    {
        $robot = subsite::findFirst(array("subsiteid='$subsiteid'"));
        if ($robot!=null) {
            $drup_dbname = $robot->drup_dbname;
            return $drup_dbname;
        }
        else{
            return null;
        }
    }

    public function getSiteid($subsiteid)
    {
        $robot = subsite::findFirst(array("subsiteid='$subsiteid'"));
        if ($robot!=null) {
            $siteid = $robot->siteid;
            return $siteid;
        }
        else{
            return null;
        }
    }

    public function updatSubSiteStatus($subsite_status, $subsiteid)
    {
        if ($subsiteid != null) {
            $siteitem = subsite::find($subsiteid);
            if ($siteitem != null) {
                $siteitem->update(Array("subsite_status" => $subsite_status));
            } else {
                return null;
            }
        } else {
            return null;
        }
        return $subsiteid;
    }

    public function getSubsitename($subsiteid)
    {
        $robot = subsite::findFirst(array("subsiteid='$subsiteid'"));
        if ($robot!=null) {
            $subsitename = $robot->subsitename;
            return $subsitename;
        }
        else{
            return null;
        }
    }

    public function updateSubsiteDB($subsiteid, $dbname, $drup_dbname)
    {
        if ($subsiteid != null) {
            $siteitem = subsite::find($subsiteid);
            if ($siteitem != null) {
                $siteitem->update(Array("dbname" => $dbname, "drup_dbname" => $drup_dbname));
            } else {
                return null;
            }
        } else {
            return null;
        }
        return $subsiteid;
    }

    public function getSubSiteData($subsiteid)
    {
        $robot = subsite::findFirst(array("subsiteid='$subsiteid'"));
        if ($robot!= null) {
            $return_subsite = [];
            $return_subsite['subsiteid'] = trim($robot->subsiteid);
            $return_subsite['subsitedbdeploymentschema'] = trim($robot->subsitedbdeploymentschema);
            $return_subsite['dbname'] = trim($robot->dbname);
            $return_subsite['environmentid'] = trim($robot->environmentid);
            $return_subsite['siteid'] = trim($robot->siteid);
            $return_subsite['subsitename'] = trim($robot->subsitename);
            $return_subsite['gitbranchsubsitestatus'] = trim($robot->gitbranchsubsitestatus);
            $return_subsite['file_systempath'] = trim($robot->file_systempath);
            $return_subsite['drup_dbname'] = trim($robot->drup_dbname);
            $return_subsite['dbconnection_string'] = trim($robot->dbconnection_string);
            $return_subsite['username'] = trim($robot->username);
            $return_subsite['password'] = trim($robot->password);
            $return_subsite['eipaddressserver'] = trim($robot->eipaddressserver);
            $return_subsite['subsite_status'] = trim($robot->subsite_status);
            $return_subsite['create_date'] = trim($robot->create_date);
            $return_subsite['update_date'] = trim($robot->update_date);
            return $return_subsite;
        } else {
            return null;
        }
    }
    
    public function getSubsitestatus($subsiteid)
    {
        $robot = subsite::findFirst(array("subsiteid='$subsiteid'"));
        if ($robot!=null) {
            $subsite_status = $robot->subsite_status;
            return $subsite_status;
        }
        else{
            return null;
        }
    }

    public function updateDB($subsiteid, $drupuser, $druppassword)
    {
        if ($subsiteid != null) {
            $siteitem = subsite::find($subsiteid);
            if ($siteitem != null) {
                $siteitem->update(Array("username" => $drupuser, "password" => $druppassword));
            } else {
                return null;
            }
        } else {
            return null;
        }
        return $subsiteid;
    }
}
?>
