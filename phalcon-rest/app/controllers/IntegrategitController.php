<?php

use GuzzleHttp\Client;
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;

class IntegrategitController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }
   
    #public function gitaction($repopath, $companyid, $clientgitrepo, $siteid,  $clientbranch, $clienttoken,$clientuser,$sitenameforgit){
    public function create() {

    $repopath = $this->app->request->get("repopath");
    $companyid = $this->app->request->get("companyid");
    $clientgit = $this->app->request->get("clientgit");
    $siteid = $this->app->request->get("siteid");
    $clienttoken = $this->app->request->get("clienttoken");
    $clientuser = $this->app->request->get("clientuser");
    $sitenameforgit = $this->app->request->get("sitenameforgit");
    $repo_name = $this->app->request->get("reponame");
    $environmentname = $this->app->request->get("environmentname");
    $branchsuffix = $this->app->request->get("branchsuffix");
    $clientbranch = $sitenameforgit.'_'.$branchsuffix;
    $activestatus = 1;

    if ($branchsuffix == 'master') {
        $is_private_master = 'true';
    }
    else { 
        $is_private_master = 'false';
    }   
    $repoauth1 = $clientuser.":".$clienttoken."@github.com";
    $repoauth2 = str_replace("github.com", $repoauth1, $clientgit);
    //$clientgitrepo = $repoauth2.".git";
    $clientgitrepo = $repoauth2;
    
    // Insert data on epm_git table for public git.
    $compobj = new company();
    $comapanyname = $compobj->getBackendCompanyName($companyid);

    $gitObj = new git();
    $privategitid = $gitObj->getPrivateGitDatabyCompanyId($companyid);

    if ($privategitid != null) {    
        $gitdata = $gitObj->getGitData($privategitid);
        $gitencode = json_encode($gitdata);
        $gitdecode = json_decode($gitencode);
        $gitPUserName = $gitdecode->git_username;
        $git_Ptoken = $gitdecode->github_token;
        $RepoGitPUrl = $gitdecode->git_url;
    }
    // check if private git repo is exist.
    elseif ($privategitid == null) {
        $gitPUserName = 'evolver-paas';
        $git_Ptoken = 'c9e35244a65b53f05c4a18d5a9305fb3a4f429ff';
        $Description = 'First public clonning of code is updated.';
        $RepoGitPUrl = 'https://github.com/evolver-paas';
        
        // Create private repo git.
        $client = new \Github\Client();
        $client->authenticate($gitPUserName, $git_Ptoken);
        $repo = new Github\Api\Repo($client);
        $myrepo = $repo->create($companyname, $Description, $RepoGitPUrl, true);
        $myrepo_json = json_decode(json_encode($myrepo), true);
        $html_url = $myrepo_json["html_url"];
        $repoauth1 = $gitPUserName.":".$git_Ptoken."@github.com";
        $repoauth2 = str_replace("github.com", $repoauth1, $html_url);
        //$RemoteGitPURL = $repoauth2.".git";
        $RemoteGitPURL = $repoauth2;
        $privategitid = $gitObj->setGit($companyid, 'null', $RepoGitPUrl, 'null', $gitPUserName, 'null', 'No', $git_Ptoken, 'private', $repo_name,'True', 'null', $repopath);
    }
        
    // Insert data on epm_git_branch for public git.
    $publicgitid = $gitObj->setGit($companyid, 'null', $clientgitrepo, 'null', $clientuser, 'null', 'No', $clienttoken, 'public', $repo_name,'True', 'null', $repopath);

    $gitbranchobj = new gitbranch();
    $pubgitbranchid = $gitbranchobj->setGitBranch($publicgitid, $clientbranch, '1', $activestatus, $is_private_master);

    chdir($repopath);
    // clone the public git.
    exec ('git clone '.$clientgitrepo);
    $s2 = explode("/",$clientgitrepo);
    $s3 = end($s2);
    $s4 = explode(".",$s3);
    chdir($s4[0]);
    // Get latest commit sha from private git.
    $client = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/phalcon-rest/']);
    $commits = &$client->request('GET', 'github/commits/'.$companyid.'/'.$siteid.'/'.$environmentname)->getBody();
    $getdata = json_decode($commits);
    $shadata = $getdata->{'Data'};
    $gitsha= $shadata[0]->sha;
    if ($gitsha == null) {
    $this->response1 = array(
           "Status" => "Failed",
           "MsgCode" => "404",
           "MsgDescription" => "Commits has not found!",
           "Data" => null,
          ); 
    exit();
    }

    // Create branch on private git as per the name of public git branch.
    $branch = $client->request('GET','github/createRepoBranch/'.trim($companyid).'/'.trim($siteid).'/'.trim($privategitid).'/'.trim($clientbranch).'/'.trim($gitsha));

    // Insert private git details into Git branch table.
    $privategitbranchid = $gitbranchobj->setGitBranch($privategitid, $clientbranch, $pubgitbranchid, $activestatus, $is_private_master);

    // Update the contected to detail.
    $updategitconnection = $gitbranchobj->updateGitBranchReference($privategitid, $pubgitbranchid);

    // Push the code to private git branch.
    exec('git add *');
    exec('git commit -m "First commit" ');
    exec('git remote set-url origin '.$RepoGitPUrl);
    exec('git remote -v');
    exec('git push origin '.$clientbranch);
  
    // Create hook on public git.
    //$CreateHook = $client->request('POST', 'github/addHook', ['json' => ['repo_owner' => $clientuser,'repo_name' => $clientgit,'repo_token' => $clienttoken,'events' => ["push"],'config'=> ['url' => 'http://api.evolverinc.tech/phalcon-rest/github/evolverWebhook'],'active'=> true]] );
    $CreateHook = $client->post('http://api.evolverinc.tech/github/addHook',array(), '{"repo_owner":"vikashlogbull","repo_name":"https://github.com/vikashlogbull/LBIT_skt25.git","repo_token":"c9e35244a65b53f05c4a18d5a9305fb3a4f429ff","events":["push"],"config":["url":"http://api.evolverinc.tech/phalcon-rest/github/evolverWebhoo"],"active": true]}');
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
