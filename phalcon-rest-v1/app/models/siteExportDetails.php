<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class siteExportDetails extends Model {

    /**
     *@var integer
     */
    protected $id;

    /**
     * @var integer
     */
    protected $siteid;

    /**
     * @var integer
     */
    protected $subsiteid;

    /**
     * @var integer
     */
    protected $environmentid;

    /**
     * @var string
     */
    protected $file_type;

    /**
     * @var string
     */
    protected $file_path;
    
    /**
     * @var timestamp with timezone
     */
    protected $uploaded_timestamp;

    /**
     * @var integer
     */
    protected $filesize;
    
    /**
     * @var string
     */
    protected $bucket_name;
    
    /**
     * @var string
     */
    protected $file_key;
    
    /**
     * @var integer
     */
    protected $is_backup;

    /**
     * @var integer
     */
    protected $deleted;


    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_site_export_details");
    }

    // Validate the xrefid
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

    // Insert the data on epm_environment table.
    public function addRecord($siteid,$subsiteid,$environmentid,$file_type,$file_path,$uploaded_timestamp,$filesize,$BucketName,$AWSURI,$is_backup=0,$deleted=0)
    {
        $this->siteid 				= $siteid;
        $this->subsiteid 			= $subsiteid;
        $this->environmentid 		= $environmentid;
        $this->file_type 			= $file_type;
        $this->file_path 			= $file_path;
        $this->uploaded_timestamp 	= $uploaded_timestamp;
        $this->filesize 			= $filesize;
        $this->bucket_name 			= $BucketName;
        $this->file_key 			= $AWSURI;
        $this->is_backup 			= $is_backup;
        $this->deleted 				= $deleted;

        if ($this->validation()) {
            $this->save();
        }
        return $this->id;
    }
}
?>