<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class privaterepository extends Model {

    /**
     * @var integer
     */
    public $priv_git_id;

    /**
     * @var string
     */
    public $git_type;

    /**
     * @var string
     */
    public $footprint_style;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $repo_url;

    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $token;

    /**
     * @var timestamp
     */
    public $create_date;

    /**
     * @var timestamp
     */
    public $lastupdate_date;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $git_status;


    // Initialize the table name
    public function initialize()
    {
        $this->setSource("epm_private_repository");
    }

    // Validate the gitid
    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'priv_git_id'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        } 
        return true;
    }

    public function getRepoAccount($priv_git_id, $git_status)
    {
	$conditions = ['priv_git_id'=>$priv_git_id, 'git_status'=>$git_status];
        $robots = privaterepository::findFirst([
            'conditions' =>'priv_git_id=:priv_git_id: AND git_status=:git_status:',
            'bind' => $conditions,
        ]);
        If ($robots!= null) {
            $repo_url = trim($robots->repo_url);
            $username = trim($robots->username);
            $description = trim($robots->description);
            $token = trim($robots->token);
            return Array('repo_url'=>$repo_url, 'username'=>$username, 'token'=> $token, 'description'=>$description);
        } else {
            return null;
        }
    }


}
?>
