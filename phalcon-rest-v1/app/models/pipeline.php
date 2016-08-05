<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class pipeline extends Model {

    /**
     * @var integer
     */
    protected $pipelineid;

    /**
     * @var string
     */
    protected $pipeline_name;

    /**
     * @var integer
     */
    protected $xrefid;

    /**
     * @var string
     */
    protected $status;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_pipeline");
    }

    // Validate the pipelineid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'pipelineid'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_pipeline table.
    public function setPipeline($pipeline_name, $xrefid, $status)
    {
        $this->pipeline_name 	= $pipeline_name;
        $this->xrefid 			= $xrefid;
        $this->status 			= $status;

        if ($this->validation()) {
            $this->save();
        }
        return $this->pipelineid;
    }

    // Update the epm_pipeline table
    public function updatePipelineStatus($pipelineid, $status)
    {
        if ($pipelineid != null) {			
            $pipelineitem = pipeline::find($pipelineid);
            if ($pipelineitem != null) { 
                $pipelineitem->update(Array("status" => $status));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $pipelineid;
    }


    /*
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
	*/
}
?>
