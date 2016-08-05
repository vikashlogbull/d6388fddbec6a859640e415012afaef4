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
                //$subsiteitem->update(Array("subsiteid" => $subsiteid, "subsitename" => $subsitename, "file_systempath" => $file_systempath, "subsite_status" => $subsite_status));
                $subsiteitem->update(Array("subsite_status" => $subsite_status));
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
}
?>
