<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class server extends Model {

    /**
     * @var integer
     */
    protected $serverid;

    /**
     * @var string
     */
    protected $connectioninformationid;

    /**
     * @var string
     */
    protected $cloudproviderid;

    /**
     * @var string
     */
    protected $servername;

    /**
     * @var string
     */
    protected $internalip;

    /**
     * @var string
     */
    protected $externalip;

    /**
     * @var string
     */
    protected $haseip;

    /**
     * @var string
     */
    protected $serverfunction;

    /**
     * @var string
     */
    protected $os_version;

    /**
     * @var string
     */
    protected $instancetype;

    /**
     * @var string
     */
    protected $keypairname;

    /**
     * @var integer
     */
    protected $backendcompanyid;

    /**
     * @var integer
     */
    protected $environmentid;

    /**
     * @var Boolean
     */
    protected $isprimary;

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
}
?>
