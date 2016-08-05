<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class server extends Model {

    /**
     * @var integer
     */
    public $serverid;

    /**
     * @var string
     */
    public $connectioninformationid;

    /**
     * @var string
     */
    public $cloudproviderid;

    /**
     * @var string
     */
    public $servername;

    /**
     * @var string
     */
    public $internalip;

    /**
     * @var string
     */
    public $externalip;

    /**
     * @var string
     */
    public $haseip;

    /**
     * @var string
     */
    public $serverfunction;

    /**
     * @var string
     */
    public $os_version;

    /**
     * @var string
     */
    public $instancetype;

    /**
     * @var string
     */
    public $keypairname;

    /**
     * @var integer
     */
    public $backendcompanyid;

    /**
     * @var integer
     */
    public $environmentid;

    /**
     * @var Boolean
     */
    public $isprimary;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_server");
    }

    // Validate the serverid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'serverid'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    public function getServerIP($backendcompanyid, $environmentid)
    {
        $conditions = ['backendcompanyid'=>$backendcompanyid, 'environmentid'=>$environmentid, 'serverstatus'=>'active', 'isprimary'=>'TRUE'];  
        $robot = server::findFirst([
            'conditions' =>'backendcompanyid=:backendcompanyid: AND environmentid=:environmentid: AND serverstatus=:serverstatus: AND isprimary=:isprimary:',
            'bind' => $conditions,
        ]);
        If ($robot!= null) {
            $externalip = $robot->externalip;
            return $externalip;
        } else {
            return null;
        }
    }

    public function getServerID($backendcompanyid, $environmentid)
    {
	$conditions = ['backendcompanyid'=>$backendcompanyid, 'environmentid'=>$environmentid, 'serverstatus'=>'active','isprimary'=>'TRUE'];
        $robot = server::findFirst([
            'conditions' =>'backendcompanyid=:backendcompanyid: AND environmentid=:environmentid: AND serverstatus=:serverstatus: AND isprimary=:isprimary:',
            'bind' => $conditions,
        ]);
        If ($robot!= null) {
            $serverid = $robot->serverid;
            return $serverid;
        } else {
            return null;
        }
    }

	public function getServerBaseURL($backendcompanyid, $environmentid)
    {
	$conditions = ['backendcompanyid'=>$backendcompanyid, 'environmentid'=>$environmentid, 'serverstatus'=>'active','isprimary'=>'TRUE'];
        $robot = server::findFirst([
            'conditions' =>'backendcompanyid=:backendcompanyid: AND environmentid=:environmentid: AND serverstatus=:serverstatus: AND isprimary=:isprimary:',
            'bind' => $conditions,
        ]);
        If ($robot!= null) {
            $app_basepath = $robot->app_basepath;
            return $app_basepath;
        } else {
            return null;
        }
    }

	public function getServerBaseURLByServerID($id)
    {
		$conditions = ['serverid'=>$id];
        $robot = server::findFirst([
            'conditions' =>'serverid=:serverid:',
            'bind' => $conditions,
        ]);
        If ($robot!= null) {
            $app_basepath = $robot->app_basepath;
            return $app_basepath;
        } else {
            return null;
        }
    }

    public function getAllServerIP($backendcompanyid, $environmentid)
    {
	$conditions = ['backendcompanyid'=>$backendcompanyid, 'environmentid'=>$environmentid, 'serverstatus'=>'active', 'serverfunction'=>'ApacheWeb'];
        $robots = server::find([
            'conditions' =>'backendcompanyid=:backendcompanyid: AND environmentid=:environmentid: AND serverstatus=:serverstatus: AND serverfunction=:serverfunction:','bind' => $conditions,
        ]);
        If ($robots!= null) {
            $serverips = [];
            $count = 0;
            foreach($robots as $rob)
            {
                $serverips[$count] = trim($rob->externalip);
                $count += 1;
            }
            return $serverips;
        } else {
            return null;
        }
    }

    public function getServerIDByIP($externalip)
    {
        $robot = server::findFirst(array("externalip='$externalip'"));
        if ($robot!=null) {
            $serverid = $robot->serverid;
            return $serverid;
        }
        else{
           return null;
        }
    }

    public function getServerIPByID($id)
    {
        $robot = server::findFirst(array("serverid='$id'"));
        if ($robot!=null) {
            $externalip = $robot->externalip;
            return $externalip;
        }
        else{
           return null;
        }
    }

    public function getPrimaryServerID($backendcompanyid, $environmentid, $isprimary, $serverfunction)
    {
	$conditions = ['backendcompanyid'=>$backendcompanyid, 'environmentid'=>$environmentid, 'serverstatus'=>'active', 'isprimary'=>$isprimary, 'serverfunction'=>$serverfunction];
        $robots = server::find([
            'conditions' =>'backendcompanyid=:backendcompanyid: AND environmentid=:environmentid: AND serverstatus=:serverstatus: AND isprimary=:isprimary: AND serverfunction=:serverfunction:',
            'bind' => $conditions,
        ]);
        If ($robots!= null) {
            $serverid = trim($robots->serverid);
            return $serverid;
        } else {
            return null;
        }
    }

    public function getAllDBServerIP($backendcompanyid, $environmentid)
    {
	$conditions = ['backendcompanyid'=>$backendcompanyid, 'environmentid'=>$environmentid, 'serverstatus'=>'active', 'serverfunction'=>'dbserver'];
        $robots = server::find([
            'conditions' =>'backendcompanyid=:backendcompanyid: AND environmentid=:environmentid: AND serverstatus=:serverstatus: AND serverfunction=:serverfunction:','bind' => $conditions,
        ]);
        If ($robots!= null) {
            $serverips = [];
            $count = 0;
            foreach($robots as $rob)
            {
                $serverips[$count] = trim($rob->externalip);
                $count += 1;
            }
            return $serverips;
        } else {
            return null;
        }
    }
	
    public function getServerDetails($serverid)
    {
        $robot = server::findFirst(array("serverid='$serverid'"));
        if ($robot!=null) {
            $details["externalip"] = $robot->externalip;
            $details["port"] = $robot->port;
			return $details;
        }
        else{
           return null;
        }
    }	
}
?>
