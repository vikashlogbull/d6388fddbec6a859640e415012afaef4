<?php

require_once 'php-github-api-master/vendor/autoload.php';
require_once 'vendor/autoload.php';
use GuzzleHttp\Client;
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;

class GitIntegrationController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }
   
    #public function gitaction($repopath, $companyid, $clientgitrepo, $siteid,  $clientbranch, $clienttoken,$clientuser,$sitenameforgit){
    public function gitaction(){

    $repopath = '/var/www/html/';
    $companyid = 2;
    $clientgitrepo = 'https://github.com/vikashlogbull/sitevs6_5_1_358.git';
    $siteid = 358;
    $clienttoken = 'c9e35244a65b53f05c4a18d5a9305fb3a4f429ff';
    $clientuser = 'vikashlogbull';
    $sitenameforgit = 'sitevs6_5_1_358';
    $repo_name = 'gittest';
    $environmentname = 'Dev';
    $clientbranch = 'Master_'.$environmentname;
    $activestatus = 1;
    // Insert data on epm_git table for public git.
    $compobj = new company();
    $comapanyname = $compobj->getBackendCompanyName($companyid);
    echo 'CompanyName:'.trim($comapanyname);

    $gitObj = new git();
    $privategitid = $gitObj->getGitDatabyCompanyId($companyid);
    echo 'PrivateGit:'.$privategitid;

    // check if private git repo is exist.
    if ($privategitid == null) {
        $PvtUserName = 'evolver-paas';
        $PvtToken = 'c9e35244a65b53f05c4a18d5a9305fb3a4f429ff';
        $Description = 'First public clonning of code is updated.';
        $RepoGitUrl = 'https://github.com/evolver-paas';
        
        // Create private repo git.
        $client = new \Github\Client();
        $client->authenticate($PvtUserName, $PvtToken);
        $repo = new Github\Api\Repo($client);
        $myrepo = $repo->create($companyname, $Description, $RepoGitUrl, true);
        $myrepo_json = json_decode(json_encode($myrepo), true);
        $html_url = $myrepo_json["html_url"];
        $privategitid = $git0bj->setGit($companyid, $git_location, $git_url, $git_ssh, $git_username, $git_password, $password_needs_update, $github_token, $repo_access_type, $repo_name, $connected_to, $repo_visibility, $hook_is_set, $repo_path);
        echo $privategitid;
    }
    
    // Insert data on epm_git_branch for public git.
    $publicgitid = $gitObj->setGit($companyid, 'null', $clientgitrepo, 'null', $clientuser, 'null', 'No', $clienttoken, 'public', $repo_name, $privategitid, 'True', 'null', $repopath);
    echo $publicgitid;

    $gitbranchobj = new gitbranch();
    $pubgitbranchid = $gitbranchobj->setGitBranch($publicgitid, $clientbranch, '1', $activestatus);

    chdir($repopath);
    // clone the public git.
    exec ('git clone '.$clientuser.':'.$clienttoken.'@'.$clientgitrepo);

    // Get latest commit sha from private git.
    $client = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/phalcon-rest/']);
    $response = $client->request('GET', 'github/commits/'.$companyid.'/'.$siteid.'/'.$environmentname);
    $gitencode = json_encode($response);
    $gitdecode = json_decode($gitencode);
    $gitsha = $gitdecode->sha;

    // Create branch on private git as per the name of public git branch.
    $branch = $client->request('GET','github/createRepoBranch/'.$companyid.'/'.$siteid.'/'.$privategitid.'/'.$clientbranch.'/'.$gitsha);

    // Insert private git details into Git branch table.
    $privategitbranchid = $gitbranchobj->setGitBranch($privategitid, $clientbranch, $pubgitbranchid, $activestatus);

    // Update the contected to detail.
    $updategitconnection = $gitbranchobj->updateGitBranchReference($privategitid, $pubgitbranchid);

    // Push the code to private git branch.
    exec('git add *');
    exec('git commit -m "First commit" ');
    exec('git remote add origin '.$RemoteGitURL);
    exec('git remote -v');
    exec('git push origin'.$branch_name);
  
    // Create hook on public git.
    $CreateHook = $client->request('GET', 'github/evolverWebhook'); 
    $this->response1 = array(
           "Status" => "Success",
           "MsgCode" => "100",
           "MsgDescription" => "Operation done successfully",
           "Data" => $CreateHook,
          ); 
    return $this->response1;    
}

}
?>
