<?php

use Phalcon\Mvc\Model;

class xrefSiteEnvironment extends Model {

    /**
     * @var integer
     */
    protected $xrefid;

    /**
     * @var integer
     */
    protected $siteid;

    /**
     * @var integer
     */
    protected $environmentid;

    /**
     * @var string
     */
    protected $create_date;

    /**
     * @var string
     */
    protected $drupalfolderpath;

    /**
     * @var integer
     */
    protected $serverid;

    /**
     * @var string
     */
    protected $sitedomainname;

    /**
     * @var string
     */
    protected $drup_dbname;

    /**
     * @var string
     */
    protected $dbconnection_string;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var integer
     */
    protected $git_branch_id;


    /**
     * @var integer
     */
    protected $database_uses_global_password;

    /**
     * @var integer
     */
    protected $database_id;

    /**
     * @var integer
     */
    protected $gitid;

    /**
     * @var string
     */
    protected $update_date;

    /**
     * @var string
     */
    protected $site_status;

    /**
     * @var string
     */
    protected $db_username_secondary;

    /**
     * @var string
     */
    protected $db_password_secondary;


    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_xref_site_environment");
    }
}
?>
