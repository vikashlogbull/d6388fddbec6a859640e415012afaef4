<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class docker extends Model {

    /**
     * @var integer
     */
    protected $dockerid;

    /**
     * @var string
     */
    protected $docker_name;

    /**
     * @var integer
     */
    protected $pipelineid;

    /**
     * @var integer
     */
    protected $serverid;

    /**
     * @var integer
     */

    protected $server_port;

    /**
     * @var string
     */
    protected $status;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_docker");
    }

    // Validate the siteid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'dockerid'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_pipeline table.
    public function setDocker($docker_name, $pipelineid, $serverid, $server_port, $status)
    {
        $this->docker_name 	= $docker_name;
        $this->pipelineid 	= $pipelineid;
        $this->serverid 	= $serverid;
        $this->server_port 	= $server_port;
        $this->status 		= $status;

        if ($this->validation()) {
            $this->save();
        }
        return $this->dockerid;
    }

    // Update the epm_pipeline table
    public function updateDockerStatus($dockerid, $status)
    {
        if ($dockerid != null) {			
            $dockeritem = docker::find($dockerid);
            if ($dockeritem != null) { 
                $dockeritem->update(Array("status" => $status));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $dockerid;
    }

	public function portAlreadyInUse($server_id,$port){
		$dockeritem = docker::findFirst(array("serverid='$server_id' AND server_port=$port"));
		if ($dockeritem == null) {
			return false;
		} else {
			return true;
		}
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
