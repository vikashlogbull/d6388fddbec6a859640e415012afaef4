<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class gitsubsite extends Model {

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    protected $subsitesid;

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
        $this->setSource("epm_xref_git_subsites");
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

    public function setGitsubsite($subsitesid, $gitid, $lastupdatetime, $status)
    {
        $this->subsitesid = $subsitesid;
        $this->gitid = $gitid;
	$this->lastupdatetime = $lastupdatetime;
        $this->status = $status;
        if ($this->validation()) {
            $this->save();
        }
        return $this->id;
    }

    public function updateGitsubsite($id, $subsitesid, $gitid, $lastupdatetime, $status)
    {
        $gitsubsite_id = $this->id;
        $this->id = $id;
        $this->subsitesid = $subsitesid;
        $this->gitid = $gitid;
	$this->lastupdatetime = $lastupdatetime;
        $this->status = $status;
        if ($this->validation() && ($gitsubsite_id == $id)) {
            $this->save();
        } else {
            return null;
        }
        return $this->id;
    }
    public function getGitsubsite($subsitesid)
    {
        $robot = subsitesid::findFirst("subsitesid='$subsitesid'");
        If ($robot!= null) {
            $id = $robot->subsitesid;
            return $id;
        } else {
            return null;
        }
    }
}
?>
