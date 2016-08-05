<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class gitsitesubsite extends Model {

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    protected $sitetype;

    /**
     * @var string
     */
    protected $sitessubsiteid;

    /**
     * @var string
     */
    protected $gitid;

     /**
     * @var string
     */
    protected $lastupdatetime;

     /**
     * @var string
     */
    protected $status;

    public function initialize()
    {
        $this->setSource("epm_xref_git_sites_subsites");
    }

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

    public function setGitsitesubsite($sitetype, $sitessubsiteid, $gitid, $status)
    {
        $this->sitetype = $sitetype;
        $this->sitessubsiteid = $sitessubsiteid;
	$this->gitid = $gitid;
        $this->status = $status;
        if ($this->validation()) {
            $this->save();
        }
        return $this->id;
    }

    public function updateGitsitesubsite($id, $sitetype, $sitessubsiteid, $gitid, $status)
    {
        $sitesubsite_id = $this->id;
	$this->id = $id;
        $this->sitetype = $sitetype;
        $this->sitessubsiteid = $sitessubsiteid;
	$this->gitid = $gitid;
        $this->status = $status;
        if ($this->validation() && ($sitesubsite_id == $id)) {
            $this->save();
        } else {
            return null;
        }
        return $this->id;
    }

    public function getApikeyId($sitessubsiteid)
    {
        $robot = sitessubsiteid::findFirst("sitessubsiteid='$sitessubsiteid'");
        If ($robot!= null) {
            $id= $robot->sitessubsiteid;
            return $id;
        } else {
            return null;
        }
    }
}
?>
