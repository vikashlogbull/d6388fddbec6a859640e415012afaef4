<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class apikey extends Model {

    /**
     * @var apikeyid 
     */
    protected $apikeyid;

    /**
     * @var dev_sites
     */
    protected $dev_sites;

    /**
     * @var test_sites
     */
    protected $test_sites;

    /**
     * @var live_sites
     */
    protected $live_sites;

    /**
     * @var apikey
     */
    protected $apikey;

    /**
     * @var apikeysite_xref
     */
    protected $apikeysite_xref;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_apikeyid");
    }

    // Validate the apikeyid
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

    // Insert the data on epm_apikeyid table.
    public function setApikey($dev_sites, $test_sites, $live_sites, $apikey, $apikeysite_xref)
    {
        $this->dev_sites = $dev_sites;
        $this->test_sites = $test_sites;
        $this->live_sites = $live_sites;
        $this->apikey = $apikey;
        $this->apikeysite_xref = $apikeysite_xref;

        if ($this->validation()) {
            $this->save();
        }

        return $this->id;
    }

    // Update the epm_environment table
    public function updateApikey( $apikeyid, $dev_sites, $test_sites, $live_sites, $apikey, $apikeysite_xref)
    {
        $apikey_id = $this->apikeyid;
        $this->dev_sites = $dev_sites;
        $this->test_sites = $test_sites;
        $this->live_sites = $live_sites;
        $this->apikey = $apikey;
        $this->apikeysite_xref = $apikeysite_xref;
        if ($this->validation() && ($apikey_id == $apikeyid)) {
            $this->save();
        } else {
            return null;
        }
        return $this->apikeyid;
    }

    public function getApikey()
    {
        $return_apikey = [];
        $return_apikey['apikeyid'] = $this->apikeyid;
        $return_apikey['dev_sites'] = $this->dev_sites;
        $return_apikey['test_sites'] = $this->test_sites;
        $return_apikey['live_sites'] = $this->live_sites;
        $return_apikey['apikey'] = $this->apikey;
        $return_apikey['apikeysite_xref'] = $this->apikeysite_xref;
        return $return_apikey;
    }

    public function getApikeyList()
    {
        $return_apikeys = array();
        foreach ($this->find() as $apikey) {
            $return_apikey = array();
            $return_apikey['apikeyid'] = $apikey->apikeyid;
            $return_apikey['dev_sites'] = $apikey->dev_sites;
            $return_apikey['test_sites'] = $apikey->test_sites;
            $return_apikey['live_sites'] = $apikey->live_sites;
            $return_apikey['apikey'] = $apikey->apikey;
            $return_apikey['apikeysite_xref'] = $apikey->apikeysite_xref;
            $return_apikeys[] = $return_apikey;
        }
        return $return_apikeys;
    }

    public function getApikeyId($apikey)
    {
        $robot = apikey::findFirst("apikey='$apikey'");
        If ($robot!= null) {
            $apikeyid = $robot->id;
            return $apikeyid;
        } else {
            return null;
        }
    }
}
?>
