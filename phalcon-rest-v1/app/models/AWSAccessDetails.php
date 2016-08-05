<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class AWSAccessDetails extends Model {

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    protected $access_key;

    /**
     * @var string
     */
    protected $secret_key;


    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_aws_access_details");
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

    // Insert the data on epm_pipeline table.
    public function getAWSAccessDetailsByAppUse($internalappuse)
    {
		$robot = AWSAccessDetails::findFirst(array("internalappuse='$internalappuse'"));
		if ($robot != null) {
			$AWSAccessDetails["awsAccessKey"] = trim($robot->access_key);
			$AWSAccessDetails["awsSecretKey"] = trim($robot->secret_key);
			return $AWSAccessDetails;
		} else {
			return null;
		}
    }
}
?>
