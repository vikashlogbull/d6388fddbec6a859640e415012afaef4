<?php

use Phalcon\Mvc\Model;

class gitCommits extends Model {

    /**
     * @var integer
     */
    protected $commit_id;

    /**
     * @var integer
     */
    protected $gitid;

    /**
     * @var string
     */
    protected $commit_sha_hash;

    /**
     * @var string
     */
    protected $commit_hash;

    /**
     * @var string
     */
    protected $commit_msg;

    /**
     * @var string
     */
    protected $commit_datetime;

    /**
     * @var string
     */
    protected $createdatetime;

    /**
     * @var string
     */
    protected $lastupdatedate;

    /**
     * @var integer
     */
    protected $branch_id;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_git_commits");
    }
}
?>
