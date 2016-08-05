<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class xrefServerCompany extends Model {

    /**
     * @var integer
     */
    public $id;

    /**
     * @var integer
     */
    public $serverid;

    /**
     * @var string
     */
    public $backendcompanyid;

    /**
     * @var timestamp with timezone
     */
    public $created_date;

    /**
     * @var timestamp with timezone
     */
    public $updated_date;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_xref_server_company");
    }

    // Validate the xref_dbserv_id
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

    // Insert the data on epm_xref_server_database table.
    public function setXrefDBServer($server_id, $database_server_id, $xrefstatus)
    {
        $this->server_id = $server_id;
        $this->database_server_id = $database_server_id;
	$this->xrefstatus = $xrefstatus;

        if ($this->validation()) {
            $this->save();
        }
        return $this->xref_dbserv_id;
    }

    // Update the epm_xref_server_database table status
    public function updateXrefStatus($xrefstatus, $xref_dbserv_id)
    {
        if ($xref_dbserv_id != null) {
            $siteitem = xrefServerDatabase::find($xref_dbserv_id);
            if ($siteitem != null) { 
                $siteitem->update(Array("xrefstatus" => $xrefstatus, "xref_dbserv_id" => $xref_dbserv_id));
            } else {
               return null;
            }
        } else {
            return null;
        }
        return $xref_dbserv_id;
    }

    public function getServerId($xref_dbserv_id)
    {
        $robot = xrefServerDatabase::findFirst(array("xref_dbserv_id='$xref_dbserv_id'"));
        if ($robot!= null) {
            $server_id = $robot->server_id;
            return $server_id;
        } else {
            return null;
        }
    }

    public function getDatabaseId($xref_dbserv_id)
    {
        $robot = xrefServerDatabase::findFirst(array("xref_dbserv_id='$xref_dbserv_id'"));
        if ($robot!= null) {
            $database_server_id = $robot->database_server_id;
            return $database_server_id;
        } else {
            return null;
        }
    }

    /*public function getXrefDBServId($server_id)
    {
        $robot = xrefServerDatabase::findFirst(array("server_id='$server_id'"));
        if ($robot!= null) {
            $xref_dbserv_id = $robot->xref_dbserv_id;
            return $xref_dbserv_id;
        } else {
            return null;
        }
    }*/
    public function getXrefDBServId($server_id)
    {
        $robot = xrefServerDatabase::findFirst(array("server_id='$server_id'"));
        if ($robot!= null) {
            $xref_dbserv_id = $robot->database_server_id;
            return $xref_dbserv_id;
        } else {
            return null;
        }
    }
}
?>
