<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class xrefGitSites extends Model {

    /**
     * @var integer
     */
    protected $id;

    /**
     * @var integer
     */
    protected $sitesid;

    /**
     * @var integer
     */
    protected $gitid;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $lastupdatetime;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_xref_git_sites");
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

    public function setGitSite($sitesid, $gitid, $status)
    {
        $this->sitesid = $sitesid;
        $this->gitid = $gitid;
        $this->status = $status;

        if ($this->validation()) {
            $this->save();
        }
        return $this->id;        
    }

    public function getGitSitebySiteID($sitesid)
    {
        $robot = xrefGitSites::findFirst(array("sitesid='$sitesid'"));
        if ($robot!= null) {
            $return_gitsite = [];
            $return_gitsite['id'] = trim($robot->id);
            $return_gitsite['sitesid'] = trim($robot->sitesid);
            $return_gitsite['gitid'] = trim($robot->gitid);
            $return_gitsite['status'] = trim($robot->status);
            return $return_gitsite;
        } else {
            return null;
        }
    }
}
?>
