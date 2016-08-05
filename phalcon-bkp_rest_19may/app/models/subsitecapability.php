<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class subsitecapability extends Model {

    /**
     * @var integer
     */
    protected $subsiteid;

    /**
     * @var string
     */
    protected $roleid;

    /**
     * @var string
     */
    protected $collaboratorid;

    /**
     * @var string
     */
    protected $datetime;

     /**
     * @var string
     */
    protected $subsiteadmincapabilityrefid;

    public function initialize()
    {
        $this->setSource("epm_subsiteadmincapability_xref");
    }

    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'subsiteid'
        )));
    if ($this->validationHasFailed() == true) {
            return false;
        }
        return true;
    }

    public function setSubsitecapability($subsiteid, $roleid, $collaboratorid, $datetime)
    {
        $this->subsiteid = $subsiteid;
        $this->roleid = $roleid;
        $this->collaboratorid = $collaboratorid;
	$this->datetime = $datetime;
        if ($this->validation()) {
            $this->save();
        }
        return $this->subsiteadmincapabilityrefid;
    }

    public function updateSubsitecapability($subsiteid, $roleid, $collaboratorid, $datetime, $subsiteadmincapabilityrefid)
    {
        $subsiteadmincapabilityref_id = $this->subsiteadmincapabilityrefid;
	$this->subsiteid = $subsiteid;
        $this->roleid = $roleid;
        $this->collaboratorid = $collaboratorid;
	$this->subsiteadmincapabilityrefid = $subsiteadmincapabilityrefid;
	$this->datetime = $datetime;
        if ($this->validation() && ($subsite_id == $subsiteid)) {
            $this->save();
        } else {
            return null;
        }
        return $this->subsiteadmincapabilityrefid;
    }

    public function getSubsitecapability($subsiteid)
    {
        $robot = subsiteid::findFirst("subsiteid='$subsiteid'");
        If ($robot!= null) {
            $subsiteadmincapabilityrefid = $robot->subsiteadmincapabilityrefid;
            return $subsiteadmincapabilityrefid;
        } else {
            return null;
        }
    }
}
?>
