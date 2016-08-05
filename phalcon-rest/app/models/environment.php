<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class environment extends Model {

    /**
     * @var environmentid
     */
    protected $environmentid;

    /**
     * @var environmentname
     */
    protected $environmentname;

    /**
     * @var environmentstatus
     */
    protected $environmentstatus;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_environment");
    }

    // Validate the environmentid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'environmentid'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_environment table.
    public function setEnvironment($environmentname, $environmentstatus)
    {
        $this->environmentname = $environmentname;
        $this->environmentstatus = $environmentstatus;

        if ($this->validation()) {
            $this->save();
        }

        return $this->environmentid;
    }

    // Update the epm_environment table
    public function updateEnvironment( $environmentid, $environmentname, $environmentstatus)
    {
        $environment_id = $this->environmentid;        
        $this->environmentname = $environmentname;
        $this->environmentstatus = $environmentstatus;
        if ($this->validation() && ($environment_id == $environmentid)) {
            $this->save();
        } else {
            return null;
        }

        return $this->environmentid;
    }

    public function getenvironment()
    {
        $return_environment = [];
        $return_environment['environmentid'] = $this->environmentid;
        $return_environment['environmentname'] = $this->environmentname;
        $return_environment['environmentstatus'] = $this->environmentstatus;
        return $return_environment;
    }

    public function getEnvironmentList()
    {
        $return_environments = array();
        foreach ($this->find() as $environment) {
            $return_environment = array();
            $return_environment['environmentid'] = $environment->environmentid;
            $return_environment['environmentname'] = $environment->environmentname;
            $return_environment['environmentstatus'] = $environment->environmentstatus;
            $return_environments[] = $return_environment;
        }
        return $return_environments;
    }

    public function getEnvironmentId($environmentname, $environmentstatus)
    {
        $robot = environment::findFirst(array("environmentname='$environmentname'","environmentstatus='$environmentstatus'"));
        if ($robot!= null) {
            $envid = $robot->environmentid;
            return $envid;
        } else {
            return null;
        }
    }

    public function getEnvironmentName($environmentid)
    {
	$robot = environment::findFirst(array("environmentid= '$environmentid'"));
	if (robot != null) {
	   $envname = $robot->environmentname;
	   return $envname;
	}
	else{
	   return null;
	}
    }
}
?>
