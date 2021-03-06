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

}
?>
