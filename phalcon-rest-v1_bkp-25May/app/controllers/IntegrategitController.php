<?php
require_once 'php-github-api-master/vendor/autoload.php';
require_once 'vendor/autoload.php';
use GuzzleHttp\Client;
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

class IntegrategitController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }
   
    public function hookExists($gituser, $gitrepo, $gittoken) {

        // Get hook from public repository.
        $clientgit = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/']);
        $getrepohook = $clientgit->request('GET',
            'phalcon-rest-v1/github/gethook',
            ['query' => ['repo_name' => trim($gitrepo), 'repo_owner' => trim($gituser), 'token' => trim($gittoken)]])->getBody()->getContents();

	$getdecode = json_decode($getrepohook);
       
	if (($getdecode->Status == 'Success') && (!empty($getdecode->{'Data'}))) {
	    $getDatavar = $getdecode->{'Data'};
	    foreach($getdecode->{'Data'} as $config) {
                $urldata = $config->{'config'};
                if(($urldata->{'url'} == 'http://api.evolverinc.tech/phalcon-rest-v1/github/evolverWebhook') OR ($urldata->{'url'} == 'http:\/\/api.evolverinc.tech\/phalcon-rest-v1\/github\/evolverWebhook')) {
                    $hookid = $config->{'id'};
                    $hookurl = $urldata->{'url'};
                    $this->response = array( 
                        "hookexist" => true,
                        "hookid" => $hookid,
                        "hookurl" => $hookurl
                        );
                    return $this->response;
                }
           }
        $this->response = array( 
            "hookexist" => false,
            "hookid" => null,
            "hookurl" => null
            );
        return $this->response;
        }
    }

    public function findanddeleteHook($gituser, $gitrepo, $gittoken) {

        // Get hook from public repository.
        $clientgit = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/']);

        try {
        $getrepohook = $clientgit->request('GET',
            'phalcon-rest-v1/github/gethook',
            ['query' => ['repo_name' => trim($gitrepo), 'repo_owner' => trim($gituser), 'token' => trim($gittoken)]])->getBody()->getContents();
        } catch (RequestException $e) {
            error_log($e->getRequest());
            if ($e->hasResponse()) {
                 error_log($e->getResponse());
            }
        } catch (ClientException $e) {
            error_log($e->getRequest());
            error_log($e->getResponse());
        }

        $getdecode = json_decode($getrepohook);
        $getencode = json_encode($getdecode);
        $getdecodedata = json_decode($getencode);
       
        if (($getdecodedata->Status == 'Success') && (!empty($getdecodedata->{'Data'}))) {
            $getDatavar = $getdecodedata->{'Data'};
            $hookid = $getDatavar[0]->id; 
            $deleterepohook = $clientgit->request('DELETE',
                'phalcon-rest-v1/github/deletehook',
                ['query' => ['repo_name' => trim($gitrepo), 'repo_owner' => trim($gituser), 'token' => trim($gittoken), 'id' => $hookid, 'string' => 'active']])->getBody()->getContents();

            $decode = json_decode($deleterepohook);
            $encode = json_encode($decode);
            $decodedata = json_decode($encode);

            if ($decodedata->Status == 'Success') {
                //$this->response = array(
                //    "Status" => "Success",
                //    "MsgCode" => "200",
                //    "MsgDescription" => "Webhooks deleted!",
                //    );
                //echo json_encode($this->response);
                //return $this->response;
                return True;
            }
        }

        //$this->response = array(
        //    "Status" => "Failed",
        //    "MsgCode" => "404",
        //    "MsgDescription" => "Webhooks not found!",
        //    );
        //echo json_encode($this->response);
        //return $this->response;
        return False;
    }
    
    public function CreateRepoForSite($RepoAccountID, $companyid, $companyname, $siteid, $repopath) {

        $repoobj = new privaterepository();
        $pvtrepodata = $repoobj->getRepoAccount($RepoAccountID, 'active');
        $repoencode = json_encode($pvtrepodata);
        $repodecode = json_decode($repoencode);
        $gitPUserName = $repodecode->username;
        $git_Ptoken = $repodecode->token;
        $Description = $repodecode->description;
        $RemoteGitPURL = $repodecode->repo_url;
        $prepo_name = md5($companyname.'_'.$siteid);
        
        // Create private repo git for the given company..
        if (! empty($RemoteGitPURL)) {
            $client = new \Github\Client();
            $client->authenticate($gitPUserName, $git_Ptoken);
            $repo = new Github\Api\Repo($client);
            $myrepo = $repo->create($prepo_name, $Description, $RemoteGitPURL, true);
            $myrepo_json = json_decode(json_encode($myrepo), true);
            $html_url = $myrepo_json["clone_url"];
        }
        else {
            $this->response = Array(
                "status" => "Error",
                "message" => "Admin account is not exists!"
                );
            error_log(json_encode($this->response));
            return $this->response;
        }
        $repoauth1 = $gitPUserName.":".$git_Ptoken."@github.com";
        $repoauth2 = str_replace("github.com", $repoauth1, $html_url);
        $RepoGitPUrl = $repoauth2;
        $clientbranch = trim($companyname.'_'.$siteid);
        $gitObj = new git();
        $privategitid = $gitObj->setGit($companyid, 'null', $html_url, 'null', $gitPUserName, 'null', 'No', $git_Ptoken, 'private', $prepo_name,'True', 'null', $repopath);
        $this->response = Array(
            "Status" => "Success",
            "message" => "Private repo is created successfully and updated on table!",
            "git_username" => $gitPUserName,
            "github_token" => $git_Ptoken,
            "git_url" => $html_url,
            "repo_name" => $prepo_name,
            "repo_complete_url" => $RepoGitPUrl,
            "privategitid" => $privategitid,
            );
        error_log(json_encode($this->response));
        return $this->response;
        
    }
        // check if private git repo is exist.
    

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
        $HASH = md5($sitenameforgit.'_'.$siteid.'_'.$companyid);
        $repoauth1 = $clientuser.":".$clienttoken."@github.com";
        $repoauth2 = str_replace("github.com", $repoauth1, $clientgit);
        $clientgitrepo = $repoauth2;
    
        // Get company name by companyid 
        $compobj = new company();
        $companyname = $compobj->getBackendCompanyName($companyid);

        // Get private git data of the company
        $gitObj = new git();
        $privategitid = $gitObj->getPrivateGitDatabyCompanyId($companyid);

        if (empty($clientgit) && empty($clientuser) && empty($clienttoken)) {
            exec('mkdir test');
            chdir('test'); 
            $RepoAccountID ='2';
            $gitdata = $this->CreateRepoForSite($RepoAccountID, $companyid, $companyname, $siteid, $repopath);
            error_log(json_encode($gitdata));
            $gitencode = json_encode($gitdata);
            $gitdecode = json_decode($gitencode);
            if ($gitdecode->{'Status'} == "Success") {
                $gitPUserName = $gitdecode->{'git_username'};
                error_log("CreatePrivateGitCode_UserName: ".$gitPUserName);
                $git_Ptoken = $gitdecode->{'github_token'};
                $RepoGitPUrl = $gitdecode->{'git_url'};
                $prepo_name = $gitdecode->{'repo_name'};
                $repo_complete_url = $gitdecode->{'repo_complete_url'};
                $privategitid = $gitdecode->{'privategitid'};
                error_log("Private_Git_Info".$RepoGitPUrl);
            }else {
                $this->response = Array(
                      "status" => "Error",
                      "Code" => "400",
                      "message" => "Private git repo is not created."
                      );
                error_log(json_encode($this->response));
                return $this->response;
            }    

            // Update git and site reference on xref_git_sites table.
            $gitsiteobj = new xrefGitSites();
            $xrefgitsiteid = $gitsiteobj->setGitSite($siteid, $privategitid, 'active');

            $data = array(
                "ClientPublicRepo" => "null",
                "PrivateRepoDirectory" => $prepo_name,
                "PrivateRepoGitUrl" => $repo_complete_url,
                "PrivateClientBranch" => "master",
            ); 
            error_log(json_encode($data));
            $this->response = array(
                "Status" => "Success",
                "MsgCode" => "100",
                "MsgDescription" => "Operation done successfully",
                "Response" => $data,
            ); 
	    header('Content-Type: application/json');
            echo json_encode($this->response);
            return $this->response;    
        } else {
            exec('mkdir test');
            chdir('test'); 
            $RepoAccountID ='2';
            $gitdata = $this->CreateRepoForSite($RepoAccountID, $companyid, $companyname, $siteid, $repopath);
            error_log(json_encode($gitdata));
            $gitencode = json_encode($gitdata);
            $gitdecode = json_decode($gitencode);
            if ($gitdecode->{'Status'} == "Success") {
                $gitPUserName = $gitdecode->{'git_username'};
                error_log("CreatePrivateGitCode_UserName: ".$gitPUserName);
                $git_Ptoken = $gitdecode->{'github_token'};
                $RepoGitPUrl = $gitdecode->{'git_url'};
                $prepo_name = $gitdecode->{'repo_name'};
                $repo_complete_url = $gitdecode->{'repo_complete_url'};
                //$privategitid = $gitdecode->{'privategitid'};
                error_log("Private_Git_Info".$RepoGitPUrl);
            }else {
                $this->response = Array(
                      "status" => "Error",
                      "Code" => "400",
                      "message" => "Private git repo is not created."
                      );
                error_log(json_encode($this->response));
                return $this->response;
            }    
            // Insert data on epm_git_branch table for the public git.
            $gitObj = new git();
            $publicgitid = $gitObj->setGit($companyid, 'null', $clientgitrepo, 'null', $clientuser, 'null', 'No', $clienttoken, 'public', $repo_name,'True', 'null', $repopath);
            if ($branchsuffix == 'master') {
                $is_private_master = 'true';
            } else { 
                $is_private_master = 'false';
            }   

            // New Git branch will connected to branch 1 y default.
            $connected_to = 1;
            $gitbranchobj = new gitbranch();
            $pubgitbranchid = $gitbranchobj->setGitBranch($publicgitid, $branchsuffix, $connected_to, $activestatus, $is_private_master);

            // Update git and site reference on xref_git_sites table.
            $gitsiteobj = new xrefGitSites();
            $xrefgitsiteid = $gitsiteobj->setGitSite($siteid, $privategitid, 'active');

            chdir($repopath);
            // clone the public git.
            $s2 = explode("/",$clientgitrepo);
            $s3 = end($s2);
            $s4 = explode(".",$s3);
            chdir($s4[0]);
            $PrivateRepo = $s4[0];
            // Get latest commit sha from private git.
            $compobj = new company();
            $fcompanyid = $compobj-> getFrontendCompanyId($companyid, 'active');

            $client = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/']);

            // Get commit details with sha.
            error_log("'http://api.evolverinc.tech/phalcon-rest-v1/github/getcommits/".$fcompanyid.'/'.$privategitid.'/'.$environmentname);
            $commits = $client->request('GET', 'phalcon-rest-v1/github/getcommits/'.$fcompanyid.'/'.$privategitid.'/'.$environmentname)->getBody()->getContents();
            error_log($commits);
            $getcommitdata = json_decode($commits);
            $commitsencode = json_encode($getcommitdata);
            $getdata = json_decode($commitsencode);
            $shadata = $getdata->{'Data'};
            $gitsha= $shadata[0]->sha;
            if ($gitsha == null) {
                $this->response1 = array(
                   "Status" => "Failed",
                   "MsgCode" => "404",
                   "MsgDescription" => "Commits has not found!",
                   "Data" => null,
                  ); 
            }

		/* Edited by Mayank Started 14 May */			
		// Create the branch on private git as per the name of public git branch.
		try {
			error_log("DataForCreateRepo: ".$companyid."_".$siteid."_".$privategitid."_".$clientbranch."_".$gitsha);
			// Create the branch on private git as per the name of public git branch.
			$branch = $client->request('GET','phalcon-rest-v1/github/createRepoBranch/'.trim($companyid).'/'.trim($siteid).'/'.trim($privategitid).'/'.trim($clientbranch).'/'.trim($gitsha))->getBody()->getContents();
			error_log("BranchData: ".$branch);
			// Insert private git details into Git branch table.
		}
		catch(Exception $e){
			$this->response = array(
				"Status" => "Failed",
				"MsgCode" => "404",
				"MsgDescription" => "Unable to create the new branch.",
				"Data" => null,
			); 
			header('Content-Type: application/json');
			echo json_encode($this->response);
			return $this->response;
		}

		$branchdecode = json_decode($branch);
		if($branchdecode->Status != "Success") {				
			$this->response = array(
				"Status" => "Failed",
				"MsgCode" => "404",
				"MsgDescription" => "Unable to create git branch.".$branchdecode->MsgDescription,
				"Data" => null,
			); 
			header('Content-Type: application/json');
			echo json_encode($this->response);
			return $this->response;
		}
		/* Edited by Mayank Ended 14 May */
		
            // Insert private git details into Git branch table.
		$gitbranchobj = new gitbranch();
            $privategitbranchid = $gitbranchobj->setGitBranch($privategitid, $clientbranch, $pubgitbranchid, $activestatus, $is_private_master);

            // Update the contected to detail.
            $gitbranchobj = new gitbranch();
            $updategitconnection = $gitbranchobj->updateGitBranchReference($privategitbranchid, $pubgitbranchid);

		# Get environmentid
		$objenv = new environment();
		$environmentid = $objenv->getEnvironmentId($environmentname, 'active');
		if (is_null($environmentid)){
			return $this->app->response
					->setStatusCode(404, "Not Found")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Environment does not exist!'));
		} 

            // Update git id and git branch id on epm_xref_site_environment.
            $siteenv = new siteenvironment();
            $updategitdata = $siteenv->updateGitData($siteid, $environmentid, $privategitid, $privategitbranchid);
        
            // Webhook payload URL with HASH generated code. 
            $hook_url_hash = "http://api.evolverinc.tech/phalcon-rest-v1/github/evolverWebhook";
            // Create hook on public git.
            $client = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/']);

            // Add Hook on Public git repository.
            $publichookdata = $this->hookExists($clientuser, trim($s4[0]), $clienttoken);
            $hookdecode = json_decode(json_encode($publichookdata));
            $hookexist = $hookdecode->{'hookexist'};
            if ( $hookexist == true) {
                $hookid = $hookdecode->{'hookid'};
                $hookurl = $hookdecode->{'hookurl'}; 
                $gitbranchobj = new gitbranch(); 
                $gitupdate = $gitbranchobj->updateGitBranchHookDetails($pubgitbranchid, 'True', $hookurl, $hookid);
            } else {
                $response = $client->post(
            	        'phalcon-rest-v1/github/addHook',
        	        array('body' => json_encode(array(
				    "repo_owner"	=> trim($clientuser),
				    "repo_name"		=> trim($s4[0]),
				    "repo_token"	=> trim($clienttoken),
				    "events"		=> array("push"),
				    "config"		=> array( array( "url"	=> $hook_url_hash),),
				    "active"		=> true,))))->getBody()->getContents();
                $decode = json_decode($response);
                $encode = json_encode($decode);
                $decodedata = json_decode($encode);
                $getDatavar = $decodedata->{'response'};
                $publichookid = $getDatavar->id; 
                $gitbranchobj = new gitbranch(); 
                $gitupdate = $gitbranchobj->updateGitBranchHookDetails($pubgitbranchid, 'True', $hook_url_hash, $publichookid);

                $this->response = array(
                    "Status" => "Success",
                    "MsgCode" => "100",
                    "MsgDescription" => "Operation done successfully",
                    "Data" => $response,
                ); 
            }

            $pvtrepo = explode("/",$RepoGitPUrl);
            $ends2 = end($pvtrepo);
            $PrivateRepo = explode(".",$ends2);

            // Add Hook on Private git repository.
            try {

                $privatehookdata = $this->hookExists($gitPUserName, trim($prepo_name), $git_Ptoken);
                $checkpvtdata = json_encode($privatehookdata);
                error_log("checkpvtdata: ".$checkpvtdata);
                if ($checkpvtdata != 'null') { 
                    $hookdecode = json_decode(json_encode($privatehookdata));
                    $hookexist = $hookdecode->{'hookexist'};
                }else {
                    $hookexist = false;
                }
                if ( $hookexist == true) {
                    $hookid = $hookdecode->{'hookid'};
                    $hookurl = $hookdecode->{'hookurl'}; 
                    $gitbranchobj = new gitbranch(); 
                    $gitupdate = $gitbranchobj->updateGitBranchHookDetails($privategitbranchid, 'True', $hookurl, $hookid);
                } else {
                    $responsehook = $client->post(
        	            'phalcon-rest-v1/github/addHook',
        	            array('body' => json_encode(array(
			    	        "repo_owner"	=> trim($gitPUserName),
				        "repo_name"		=> trim($prepo_name),
				        "repo_token"	=> trim($git_Ptoken),
				        "events"		=> array("push"),
				        "config"		=> array( array( "url"	=> $hook_url_hash),),
				        "active"		=> true,))))->getBody()->getContents();

                    $decode = json_decode($responsehook);
                    $encode = json_encode($decode);
                    $decodedata = json_decode($encode);
                    $getDatavar = $decodedata->{'response'};
                    $privatehookid = $getDatavar->id; 

                    $gitbranchobj = new gitbranch();
                    $gitupdate = $gitbranchobj->updateGitBranchHookDetails($privategitbranchid, 'True', $hook_url_hash, $privatehookid);
                }

                $data = array(
                    "ClientPublicRepo" => $clientgitrepo,
                    "PrivateRepoDirectory" => $PrivateRepo[0],
                    "PrivateRepoGitUrl" => $repo_complete_url,
                    "PrivateClientBranch" => "master",
                ); 
                error_log(json_encode($data));
                $this->response = array(
                    "Status" => "Success",
                    "MsgCode" => "100",
                    "MsgDescription" => "Operation done successfully",
                    "Response" => $data,
                ); 
	        header('Content-Type: application/json');
	        echo json_encode($this->response);
                return $this->response;    
            } catch (ErrorException $e) {
                                $this->response = array(
                                "Status"                => "Failure",
                                "MsgCode"               => "-1",
                                "MsgDescription"=> "Failed, reason: ". $e->getMessage(),
                    );
              return $this->response;
            }
    }
}

	public function deploy() {

        $companyid = $this->app->request->get("companyid");
        $siteid = $this->app->request->get("siteid");
        $sitenameforgit = $this->app->request->get("sitenameforgit");
        $source_environmentid = $this->app->request->get("source_environmentid");
        $target_environmentid = $this->app->request->get("target_environmentid");
        $site_private_git_id = $this->app->request->get("site_private_git_id");
        $source_git_branch_id = $this->app->request->get("source_git_branch_id");
		$site_private_git_sha_key = $this->app->request->get("site_private_git_sha_key");
		
		# Get source environment name
		$objenv = new environment();
		$source_environmentname = $objenv->getEnvironmentName(trim($source_environmentid));
		if (is_null($source_environmentname)){
			$this->app->response
					->setStatusCode(404, "Not Found")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Invalid Source Environment ID!'));
			header('Content-Type: application/json');
			echo json_encode($this->response);
			return $this->response;
		}
		$source_environmentname = trim(strtolower($source_environmentname));

		# Get target environment name
		$objenv = new environment();
		$target_environmentname = $objenv->getEnvironmentName(trim($target_environmentid));
		if (is_null($target_environmentname)){
			$this->app->response
					->setStatusCode(404, "Not Found")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Invalid Target Environment ID!'));
			header('Content-Type: application/json');
			echo json_encode($this->response);
			return $this->response;
		}
		$target_environmentname = trim(strtolower($target_environmentname));
		
		$gitObj = new git();
		$gitdata = $gitObj->getGitData($site_private_git_id);
		$gitencode = json_encode($gitdata);
		$gitdecode = json_decode($gitencode);
		$gitPUserName = $gitdecode->git_username;
		$git_Ptoken = $gitdecode->github_token;
		$RepoGitPUrl = $gitdecode->git_url;

        // Get company name by companyid 
        $compobj 		= new company();
        $companyname 	= $compobj->getBackendCompanyName($companyid);
		
        $compobj 		= new company();
        $fcompanyid 	= $compobj-> getFrontendCompanyId($companyid, 'active');
		

		$target_branch_name 	= $sitenameforgit."_".$target_environmentname;
        $client 				= new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/']);
		$createBranchResponse 	= $client->request('GET','phalcon-rest-v1/github/createRepoBranch/'.trim($companyid).'/'.trim($siteid).'/'.trim($site_private_git_id).'/'.trim($target_branch_name).'/'.trim($site_private_git_sha_key))->getBody()->getContents();;
		
		
		// Create the branch on private git as per the name of public git branch.
		/*try {
			$createBranchResponse = $client->request('GET','phalcon-rest-v1/github/createRepoBranch/'.trim($companyid).'/'.trim($siteid).'/'.trim($site_private_git_id).'/'.trim($target_branch_name).'/'.trim($site_private_git_sha_key))->getBody()->getContents();;
		}
		catch(Exception $e){
			try {
				$createBranchResponse = $client->request('GET','phalcon-rest-v1/github/createRepoBranch/'.trim($companyid).'/'.trim($siteid).'/'.trim($site_private_git_id).'/'.trim($target_branch_name).'/'.trim($site_private_git_sha_key))->getBody()->getContents();;
			}
			catch(Exception $e){
				$this->response = array(
					"Status" => "Failed",
					"MsgCode" => "404",
					"MsgDescription" => "Unable to create the new branch.",
					"Data" => null,
				); 
				header('Content-Type: application/json');
				echo json_encode($this->response);
				return $this->response;
			}
		}*/
		
		$branchdecode = json_decode($createBranchResponse);
		/*if($branchdecode->Status != "Success") {				
			$this->response = array(
				"Status" => "Failed",
				"MsgCode" => "404",
				"MsgDescription" => "Unable to create git branch.".$branchdecode->MsgDescription,
				"Data" => null,
			); 
			header('Content-Type: application/json');
			echo json_encode($this->response);
			return $this->response;
		}*/
		
		// Insert private git details into Git branch table.
		
		$target_git_branch_connected_to = $source_git_branch_id;
        $target_git_branch_status 		= 1;				
		$is_private_master 				= 'false';			/*******/			
		
		$gitbranchobj = new gitbranch();
		$privategitbranchid = $gitbranchobj->setGitBranch($site_private_git_id, $target_branch_name, $target_git_branch_connected_to, $target_git_branch_status, $is_private_master);
		
		// Update git id and git branch id on epm_xref_site_environment.
		$siteenv = new siteenvironment();
		$updategitdata = $siteenv->updateGitData($siteid, $target_environmentid, $site_private_git_id, $privategitbranchid);
		
		$data = array(
			"giturl" => $RepoGitPUrl,
			"gitbranch" => $target_branch_name
		);
		
		$this->response = array(
			"Status" => "Success",
			"MsgCode" => "200",
			"MsgDescription" => "Git branch created successfully!",
			"Data" => $data,
		); 

		header('Content-Type: application/json');
		echo json_encode($this->response);
		return $this->response;
	}
}
?>
