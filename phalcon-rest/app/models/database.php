<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class database extends Model {

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    protected $database_name;

    /**
     * @var string
     */
    protected $database_username;

    /**
     * @var string
     */
    protected $database_password;

    /**
     * @var timestamp with timezone
     */
    protected $created_date;

    /**
     * @var timestamp with timezone
     */
    protected $updated_date;

    /**
     * @var string
     */
    protected $databaseip;

    /**
     * @var integer
     */
    protected $dbport;

    /**
     * @var string
     */
    protected $dbtype;

    /**
     * @var string
     */
    protected $dbversion;

    /**
     * @var string
     */
    protected $dbhostname;

    /**
     * @var integer
     */
    protected $environmentid;

    /**
     * @var boolean
     */
    protected $isprimary;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $external_hostname;

    /**
     * @var integer
     */
    protected $primary_db_id;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_database");
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

    // Insert the data on epm_database table.
    public function setDatabase($database_name, $database_username, $database_password, $databaseip, $dbport, $dbtype, $dbversion, $dbhostname, $environmentid, $isprimary, $status, $external_hostname, $primary_db_id)
    {
        $this->database_name = $database_name;
        $this->database_username = $database_username;
        $this->database_password = $database_password;
        $this->databaseip = $databaseip;
        $this->dbport = $dbport;
        $this->dbtype = $dbtype;
        $this->dbversion = $dbversion;
        $this->dbhostname = $dbhostname;
        $this->environmentid = $environmentid;
        $this->isprimary = $isprimary;
        $this->status = $status;
        $this->external_hostname = $external_hostname;
        $this->primary_db_id = $primary_db_id;

        if ($this->validation()) {
            $this->save();
        }
        return $this->id;
    }

    public function updateDatabaseName($id, $database_name)
    {
        if ($id != null) {
             $robot = database::find($id);
             if ($robot != null) {
                 $robot->update(Array("database_name" => $database_name));
             } else {
                 return null;
             }
        } else {
            return null;
        }
        return $id;
    }

    public function getDatabase($id)
    {
        $robot = database::findFirst("id='$id'");
        If ($robot!= null) {
            $databaseip = $robot->databaseip;
            $database_username = $robot->database_username;
            $database_password = $robot->database_password;
            $dbport = $robot->dbport;
            return Array('databaseip'=>trim($databaseip), 'database_username'=>trim($database_username), 'database_password'=> trim($database_password), 'dbport'=>trim($dbport));
        } else {
            return null;
        }
    }

}
?>
