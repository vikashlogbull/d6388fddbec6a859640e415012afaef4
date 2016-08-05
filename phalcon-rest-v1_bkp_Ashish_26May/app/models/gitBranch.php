<?php

use Phalcon\Mvc\Model;

class gitBranch extends Model {

    /**
     * @var integer
     */
    public $id;

    /**
     * @var integer
     */
    protected $git_id;

    /**
     * @var string
     */
    protected $branch_name;

    /**
     * @var string
     */
    protected $created_date;

    /**
     * @var string
     */
    protected $updated_date;

    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_git_branch");
    }
}
?>
