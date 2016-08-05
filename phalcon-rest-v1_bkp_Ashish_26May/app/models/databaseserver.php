<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class databaseserver extends Model {

    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $db_ip;

    /**
     * @var string
     */
    public $db_internal_hostname;

    /**
     * @var string
     */
    public $db_external_hostname;

    /**
     * @var string
     */
    public $db_port;

    /**
     * @var string
     */
    public $db_type;

    /**
     * @var string
     */
    public $db_version;

    /**
     * @var string
     */
    public $db_server_username;

    /**
     * @var string
     */
    public $db_server_password;

    /**
     * @var integer
     */
    public $is_primary_db_server;

    /**
     * @var integer
     */
    public $primary_db_server_id;

    /**
     * @var string
     */
    public $status;

    /**
     * @var timestamp with timezone
     */
    public $create_date;

    /**
     * @var timestamp with timezone
     */
    public $update_date;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_database_server");
    }

    // Validate the siteid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'id'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    // Insert the data on epm_database_server table.
    public function setDatabaseServer($db_ip, $db_internal_hostname, $db_external_hostname, $db_port, $db_type, $db_version, $db_server_username, $db_server_password, $is_primary_db_server, $primary_db_server_id, $status)
    {
	$this->db_ip = $db_ip;
	$this->db_internal_hostname = $db_internal_hostname;
	$this->db_external_hostname = $db_external_hostname;
	$this->db_port = $db_port;
	$this->db_type = $db_type;
	$this->db_version = $db_version;
	$this->db_server_username = $db_server_username;
	$this->db_server_password = $db_server_password;
	$this->is_primary_db_server = $is_primary_db_server;
	$this->primary_db_server_id = $primary_db_server_id;
	$this->status = $status;

        if ($this->validation()) {
            $this->save();
        }
        return $this->id;
    }

    // Update the epm_sites table
    //public function updateSites($companyid, $sitename, $sitestatus, $siteid)
    //{
    //    if ($siteid != null) {
    //        $siteitem = sites::find($siteid);
    //        if ($siteitem != null) { 
    //            $siteitem->update(Array("companyid" => $companyid, "sitename" => $sitename, "sitestatus" => $sitestatus, "siteid" => $siteid));
    //        } else {
    //           return null;
    //        }
    //    } else {
    //        return null;
    //    }
    //    return $siteid;
    //}

    // Update the epm_database_server table status
    public function updateDBServerStatus($id, $status)
    {
        if ($id != null) {
            $siteitem = databaseserver::findFirst($id);
            if ($siteitem != null) { 
                $siteitem->update(Array("status" => $status));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $id;
    }

    //public function getSiteId($sitename)
    //{
    //    $robot = sites::findFirst(array("sitename='$sitename'"));
    //    if ($robot!= null) {
    //        $siteid = $robot->siteid;
    //        return $siteid;
    //    } else {
    //        return null;
    //    }
    //}

    //public function getSiteName($siteid)
    //{
    //    $robot = sites::findFirst(array("siteid='$siteid'"));
    //    if ($robot!= null) {
    //        $sitename = $robot->sitename;
    //        return $sitename;
    //    } else {
    //        return null;
    //    }
    //}

    //public function getCompanyId($siteid)
    //{
//	$robot = sites::findFirst(array("siteid='$siteid'"));
//	if ($robot!=null) {
//	    $companyid = $robot->companyid;
//	    return $companyid;
//	}
//	else{
//	   return null;
//	}
    //}
}
?>
