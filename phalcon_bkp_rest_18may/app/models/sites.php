<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class sites extends Model {

    /**
     * @var integer
     */
    protected $companyid;

    /**
     * @var string
     */
    protected $sitename;

    /**
     * @var string
     */
    protected $sitestatus;

    /**
     * @var integer
     */
    protected $siteid;

    /**
     * @var timestamp with timezone
     */
    protected $createsite_date;

    /**
     * @var timestamp with timezone
     */
    protected $updatesite_date;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_sites");
    }

    // Validate the siteid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'siteid'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_sites table.
    public function setSites($companyid, $sitename, $sitestatus)
    {
        $this->companyid = $companyid;
        $this->sitename = $sitename;
	$this->sitestatus = $sitestatus;

        if ($this->validation()) {
            $this->save();
        }
        return $this->siteid;
    }

    // Update the epm_sites table
    public function updateSites($companyid, $sitename, $sitestatus, $siteid)
    {
        if ($siteid != null) {
            $siteitem = sites::find($siteid);
            if ($siteitem != null) { 
                $siteitem->update(Array("companyid" => $companyid, "sitename" => $sitename, "sitestatus" => $sitestatus, "siteid" => $siteid));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $siteid;
    }

    // Update the epm_sites table status
    public function updateSiteStatus($sitestatus, $siteid)
    {
        if ($siteid != null) {
            $siteitem = sites::find($siteid);
            if ($siteitem != null) { 
                $siteitem->update(Array("sitestatus" => $sitestatus, "siteid" => $siteid));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $siteid;
    }

    public function getSiteId($sitename)
    {
        $robot = sites::findFirst(array("sitename='$sitename'"));
        if ($robot!= null) {
            $siteid = $robot->siteid;
            return $siteid;
        } else {
            return null;
        }
    }

    public function getSiteName($siteid)
    {
        $robot = sites::findFirst(array("siteid='$siteid'"));
        if ($robot!= null) {
            $sitename = $robot->sitename;
            return $sitename;
        } else {
            return null;
        }
    }

    public function getCompanyId($siteid)
    {
	$robot = sites::findFirst(array("siteid='$siteid'"));
	if ($robot!=null) {
	    $companyid = $robot->companyid;
	    return $companyid;
	}
	else{
	   return null;
	}
    }

    public function getSiteData($siteid)
    {
        $robot = sites::findFirst(array("siteid='$siteid'"));
        if ($robot!= null) {
            $return_site = [];
            $return_site['companyid'] = trim($robot->companyid);
            $return_site['sitename'] = trim($robot->sitename);
            $return_site['sitestatus'] = trim($robot->sitestatus);
            $return_site['siteid'] = trim($robot->siteid);
            $return_site['createsite_date'] = trim($robot->createsite_date);
            $return_site['updatesite_date'] = trim($robot->updatesite_date);
            return $return_site;
        } else {
            return null;
        }
    }

    public function getsitestatus($siteid)
    {
        $robot = sites::findFirst(array("siteid='$siteid'"));
        if ($robot!=null) {
            $sitestatus = $robot->sitestatus;
            return $sitestatus;
        }
        else{
            return null;
        }
    }
}
?>
