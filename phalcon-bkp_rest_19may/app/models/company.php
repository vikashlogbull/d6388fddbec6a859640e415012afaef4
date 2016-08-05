<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class company extends Model {

    /**
     * @var backendcompanyid 
     */
    protected $backendcompanyid;

    /**
     * @var createdatetime
     */
    protected $createdatetime;

    /**
     * @var companyname
     */
    protected $companyname;

    /**
     * @var companystatus
     */
    protected $companystatus;

    /**
     * @var lastupdate_datetime
     */
    protected $lastupdate_datetime;

    /**
     * @var isdedicated
     */
    protected $isdedicated;

    /**
     * @var frontendcompanyid
     */
    protected $frontendcompanyid;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_company");
    }

    // Validate the backendcompanyid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'backendcompanyid'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_company table.
    public function setCompany($companyname, $companystatus, $createdatetime, $lastupdate_datetime, $isdedicated, $frontendcompanyid)
    {
        $this->companyname = $companyname;
        $this->companystatus = $companystatus;
        $this->createdatetime = $createdatetime;
        $this->lastupdate_datetime = $lastupdate_datetime;
        $this->isdedicated = $isdedicated;
        $this->frontendcompanyid = $frontendcompanyid;

        if ($this->validation()) {
            $this->save();
        }

        return $this->backendcompanyid;
    }

    // Update the epm_company table
    public function updateCompany( $backendcompanyid, $companyname, $companystatus, $createdatetime, $lastupdate_datetime, $isdedicated, $frontendcompanyid)
    {
        $backendcompany_id = $this->backendcompanyid;
        $this->companyname = $companyname;
        $this->companystatus = $companystatus;
        $this->createdatetime = $createdatetime;
        $this->lastupdate_datetime = $lastupdate_datetime;
        $this->isdedicated = $isdedicated;
        $this->frontendcompanyid = $frontendcompanyid;
        if ($this->validation() && ($backendcompany_id == $backendcompanyid)) {
            $this->save();
        } else {
            return null;
        }

        return $this->backendcompanyid;
    }

    public function getCompany()
    {
        $return_company = [];
        $return_company['backendcompanyid'] = $this->backendcompanyid;
        $return_company['companyname'] = $this->companyname;
        $return_company['companystatus'] = $this->companystatus;
        $return_company['createdatetime'] = $this->createdatetime;
        $return_company['lastupdate_datetime'] = $this->lastupdate_datetime;
        $return_company['isdedicated'] = $this->isdedicated;
        $return_company['frontendcompanyid'] = $this->frontendcompanyid;
        return $return_company;
    }

    public function getCompanyList()
    {
        $return_companies = array();
        foreach ($this->find() as $company) {
            $return_company = array();
            $return_company['backendcompanyid'] = $company->backendcompanyid;
            $return_company['companyname'] = $company->companyname;
            $return_company['companystatus'] = $company->companystatus;
            $return_company['createdatetime'] = $company->createdatetime;
            $return_company['lastupdate_datetime'] = $company->lastupdate_datetime;
            $return_company['isdedicated'] = $company->isdedicated;
            $return_company['frontendcompanyid'] = $company->frontendcompanyid;
            $return_companies[] = $return_company;
        }
        return $return_companies;
    }


    public function getBackendCompanyName($backendcompanyid)
    {
        $robot = company::findFirst(array("backendcompanyid='$backendcompanyid'"));
        If ($robot!= null) {
            $companyname = $robot->companyname;
            return $companyname;
        } else {
            return null;
        }
    }

    public function getBackendCompanyIdbyFrontend($frontendcompanyid)
    {
        $robot = company::findFirst(array("frontendcompanyid='$frontendcompanyid'"));
        If ($robot!= null) {
            $backendcompanyid = $robot->backendcompanyid;
            return $backendcompanyid;
        } else {
            return null;
        }
    }

    public function getBackendCompanyId($companyname, $companystatus)
    {
        $robot = company::findFirst(array("companyname='$companyname'","companystatus='$companystatus'"));
        If ($robot!= null) {
            $companyid = $robot->backendcompanyid;
            return $companyid;
        } else {
            return null;
        }
    }

    public function getFrontendCompanyId($backendcompanyid, $companystatus)
    {
        $robot = company::findFirst(array("backendcompanyid='$backendcompanyid'","companystatus='$companystatus'"));
        If ($robot!= null) {
            $companyid = $robot->frontendcompanyid;
            return $companyid;
        } else {
            return null;
        }
    }

}
?>
