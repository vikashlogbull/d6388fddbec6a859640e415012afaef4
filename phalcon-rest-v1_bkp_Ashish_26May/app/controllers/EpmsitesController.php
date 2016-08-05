<?php
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

class EpmsitesController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }

    public function validateGitUrl($giturl, $gituser, $clienttoken) {
        $parsedURL = parse_url($giturl);
        $urldecode = json_decode(json_encode($parsedURL));
        $path = $urldecode->path;
        $branchflag = 'False';
        $addedgit = 'False';
        $chunks = explode('/', $path);
        foreach ($chunks as $i => $chunk) {
            if ($chunks[$i] == 'tree') {
                $branchflag = 'True';
            }
        }
        $arrayIndexOne = 1;
        $arrayIndexTwo = 2;
        $repoowner = $chunks[$arrayIndexOne];
        $reponame = $chunks[$arrayIndexTwo];
        $repocheck = explode('.', $chunks[$arrayIndexTwo]);
        if ($repocheck[$arrayIndexOne] == 'git') {
            $reponame = $repocheck[0];
            $addedgit = 'True';
        }
        if ($branchflag == 'False' && $i == 2) {
            if ($addedgit == 'False') {
                $NewGitUrl = $giturl . ".git";
            } else {
                $NewGitUrl = $giturl;
            }
        } 
        if ($branchflag == 'True') {
            $Newpath = "";
            if ($addedgit == 'False') {
                $withgit = $chunks[$arrayIndexTwo];
                $chunks[$arrayIndexTwo] = $withgit . ".git";
            }
            foreach ($chunks as $i => $chunk) {
                if ($i != 0) {
                    if ($i <= 2) {
                        $Newpath = $Newpath . "/" . $chunks[$i];
                    }
                }
            }
            $NewGitUrl = $urldecode->scheme . "://" . $urldecode->host . "" . $Newpath;
        }
        // Validate Git URL
        $client = new \Github\Client();
        $client->authenticate($gituser, $clienttoken);
        $repo = new Github\Api\Repo($client);
        $repoinfo = $repo->show($repoowner, $reponame);
        $myrepo_json = json_decode(json_encode($repoinfo), true);
        $clone_url = $myrepo_json["clone_url"];
        if ($NewGitUrl == $clone_url) {
            $GitUrl = $NewGitUrl;
            return $GitUrl;
        } else {
            return null;
        }
    }

    public function checkGitRepoEmpty($repo_owner, $repo_name, $repo_token, $env) {
        $client = new \Github\Client(
            new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache')));
        $client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);
        try {
            $commits = $client->api('repo')->commits()->all(
                     trim($repo_owner), trim($repo_name),
                     array('sha' => ($env == 'dev' ? 'master' : $env)));
            $sha = [];
            $arrayIndexSHA = 0;
            foreach ($commits as $keys=>$objects) {
                foreach ($objects as $key => $object) {
                    if ($key == 'sha') {
                        $sha[$arrayIndexSHA] = $object;
                        $arrayIndexSHA += 1;
                    }
                }
            }
            return $sha;
        } catch (ErrorException $e) {
            return False;
        }
    }

    public function createSiteResponse($siteid, $sitename, $siteurl, $subsiteResp) {
        $adminsiteurl = $siteurl.'/user';
        $devadminsiteurl = trim($adminsiteurl);
        $devsitestatus = "Deployed";
        $dev = '1';
        if ($subsiteResp) {
            $data = array("siteid" => $siteid,
                          "sitename" => $sitename,
                          "DevSiteURL" => $siteurl,
                          "DevAdminSiteURL" => $devadminsiteurl,
                          "DevSiteStatus" => $devsitestatus,
                          "Dev" => $dev,
                          "Subsite Data" => $subsiteResp
            );
        } else {
        $data = array("siteid" => $siteid,
                      "sitename" => $sitename,
                      "DevSiteURL" => $siteurl,
                      "DevAdminSiteURL" => $devadminsiteurl,
                      "DevSiteStatus" => $devsitestatus,
                      "Dev" => $dev
            );
       }
        return $data;
    }

    public function createSubSiteResponse($subsiteid, $subsitename,$subsiteurl,$using_own_db) {
       $devadminsubsiteurl = $subsiteurl.'/user';
       $devsubsitestatus = "Deployed";
       if ($using_own_db == TRUE) {
           $devsubsiteusingowndb = 'Yes';
       } else {
           $devsubsiteusingowndb = 'No';
       }
       $dev = '1';
       $data = array("subsiteid" => $subsiteid,
                     "subsitename" => $subsitename,
                     "DevSubSiteURL" => $subsiteurl,
                     "DevSubSiteUsingOwnDB" => $devsubsiteusingowndb,
                     "DevAdminSubSiteURL" => $devadminsubsiteurl,
                     "DevSubSiteStatus" => $devsubsitestatus,
                     "Dev" => $dev
                    );
       return $data;
    }

    public function getGitRepoName($giturl)
    {
        $parsedURL = parse_url($giturl);
        $urldecode = json_decode(json_encode($parsedURL));
        $path = $urldecode->path;
        $chunks = explode('/', $path);
        $repocheck = explode('.', $chunks[2]);
        if ($repocheck[1] == 'git') {
            $reponame = $repocheck[0];
        } else {
            $reponame = $chunks[2];
        }
        return $reponame;
    }

    public function deleteWebHook($gituser, $gitrepo, $gittoken, $hookid) {
        $clientgit = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/']);
        $deleterepohook = $clientgit->request('DELETE',
           'phalcon-rest-v1/github/deletehook',
           ['query' => ['repo_name' => trim($gitrepo), 'repo_owner' => trim($gituser), 'token' => trim($gittoken), 'id' => $hookid, 'string' => '    active']])->getBody()->getContents();

        $decode = json_decode($deleterepohook);
        $encode = json_encode($decode);
        $decodedata = json_decode($encode);
        if ($decodedata->Status == 'Success') {
            return True;
        } else {
            return False;
        }
    }

    public function SetRepoSubsiteTableEntry($object, $siteid, $environmentid, $serverid, $database_server_id) {
        $subsitedbdeploymentschema = 'Completed';
        $dbname = $object->subsitedomain;
        $subsitename = $object->subsitedomain;
        $gitbranchsubsitestatus = 'No';
        $file_systempath = $object->subsitedirpath;
        $drup_dbname = $object->drup_dbname;
        $dbconnection_string = $object->db_ip;
        $dbusername = $object->dbuser;
        $dbpassword = $object->password;
        $eipaddressserver = "";
        $subsite_status = "Completed";
        $subsite_path = $object->subsitedirpath;
        $subsite_domain_name = $object->subsitedomain;
        $database_id = "1";
        $database_name = $object->drup_dbname;
        $git_branch_id = "1";
        $database_uses_global_password = "False";
        $using_separate_branch = "False";
        $gitid = "1";
        $using_own_db = "TRUE";
        $dbport = '3306';
        $dbtype = 'Mysql';
        $dbversion = '5.5.47';
        $dbhostname = '';
        $isprimary = 'TRUE';
        $status = 'active';
        $external_hostname = '';
        $primary_db_id = '1';

        $objsubsite = new subsite();
        $subsiteid = $objsubsite->setSubsite(trim($subsitedbdeploymentschema), trim($dbname), trim($environmentid), trim($siteid), trim($subsitename), trim($gitbranchsubsitestatus), trim($file_systempath), trim($drup_dbname), trim($dbconnection_string), trim($dbusername), trim($dbpassword), trim($eipaddressserver), trim($subsite_status));
        if ($subsiteid) {
            $subsitetableflag = 'True';
        } else {
            $subsitetableflag = 'False';
        }

        $objsubsiteenv = new subsiteenv();
        $id = $objsubsiteenv->setSubSiteEnv(trim($subsiteid), trim($environmentid), trim($subsite_path), trim($serverid), trim($subsite_domain_name), trim($database_id), trim($database_name), trim($dbusername), trim($dbpassword), trim($git_branch_id), trim($database_uses_global_password), trim($using_separate_branch), trim($gitid), trim($using_own_db), trim($subsite_status));
        if ($id) {
            $subsiteenvtableflag = 'True';
        } else {
            $subsiteenvtableflag = 'False';
        }

        $database = new database();
        $databaseid = $database->setDatabase($database_name, $dbusername, $dbpassword, $dbconnection_string, $dbport, $dbtype, $dbversion, $dbhostname, $environmentid, $isprimary, $status, $external_hostname, $primary_db_id, $database_server_id);
        if ($databaseid) {
            $databasetableflag = 'True';
        } else {
            $databasetableflag = 'False';
        }

        if ($subsitetableflag == 'True' && $subsiteenvtableflag == 'True' && $databasetableflag == 'True') {
            return $subsiteid;
        } else {
            return False;
        }
    }

    public function create()
    {
        try {
            // Get input to the script 
            $apikey =  $this->app->request->get("apikey");
            $sitename = $this->app->request->get("sitename");
            $frontendcompanyid =  $this->app->request->get("companyid");
            $siteurl =  $this->app->request->get("siteurl");
            $git_url =  $this->app->request->get("giturl");
            $environmentname =  $this->app->request->get("environment");
            $InstallType = $this->app->request->get("InstallType");
            $drupal_distribution = $this->app->request->get("Distribution"); 
            $clienttoken = $this->app->request->get("clienttoken");
            $gituser = $this->app->request->get("gituser");
            $repo_name = $this->app->request->get("repo_name");
            $branch_name = $this->app->request->get("branch_name");
            
            if (!isset($apikey) || empty($apikey)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'API Key not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($InstallType) || empty($InstallType)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Install type not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($siteurl) || empty($siteurl)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site url not Posted or is Empty!'));
                return $this->app->response;
            }

            # Check Site Url
            $objsiteenv = new siteenvironment();
            $checksiteurl = $objsiteenv->checkSiteUrl($siteurl);
            if (!is_null($checksiteurl)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Url Already Exist!'));
                return $this->app->response;
            }

            // Process and Validate Git URL
            if ($InstallType == 'GIT' || $InstallType == 'GITHUB') {
                $git_url = $this->validateGitUrl($git_url, $gituser, $clienttoken);
                if (is_null($git_url)) {
                    $this->app->response
                            ->setStatusCode(404, "Not Found")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Git Repository does not exist.'));
                    return $this->app->response;
                }
            }

            if ($InstallType == 'GIT' || $InstallType == 'GITHUB') {
                $gitSHA = $this->checkGitRepoEmpty($gituser, $repo_name, $clienttoken, mb_strtolower($environmentname));
                if ($gitSHA == False || empty($gitSHA)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Git Repository is Empty.'));
                    return $this->app->response;
                }
            }
            //Get Input to the script for Git Repository create.
            $gitUserName = $gituser;
            $git_token = $clienttoken;
            $RepoGitUrl = $git_url;
            $Description = "Initial checkin while creating site!";

            if (!isset($sitename) || empty($sitename)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Sitename not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($frontendcompanyid) || empty($frontendcompanyid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company Id not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($environmentname) || empty($environmentname)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environement Name not Posted or is Empty!'));
                return $this->app->response;
            }

            # Match the company id within the DB backendcompanyid from company id
            $objcompany = new company();
            $companyname = $objcompany->getBackendCompanyName($frontendcompanyid);
            if (is_null($companyname)) {
                 $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
            }

            # Get Backend Company ID
            $companyid = $objcompany->getBackendCompanyIdbyFrontend($frontendcompanyid);
            if (is_null($companyid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
            }

            # Get environmentid
            $objenv = new environment();
            $environmentid = $objenv->getEnvironmentId($environmentname, 'active');
            if (is_null($environmentid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environment does not exist!'));
            }

            # Get serverips 
            $objserv = new server();
            $backendcompanyid = $companyid;
            $serverips = $objserv->getAllServerIP($backendcompanyid, $environmentid);
            if (is_null($serverips)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            # Get serverid
            $serverid = $objserv->getServerID($backendcompanyid, $environmentid);
            if (is_null($serverid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }
            $primaryserverid = $serverid;

            $phql = "SELECT A1.db_external_hostname AS dbconnection_string FROM databaseserver A1 JOIN server A2 on A1.serverid = A2.serverid JOIN xrefServerCompany A3 ON A2.serverid = A3.serverid WHERE A2.serverfunction = 'dbserver' AND A1.status = 'active' AND A2.serverstatus = 'active' and A3.backendcompanyid = $backendcompanyid AND A2.environmentid = $environmentid";
            $resdb = $this->modelsManager->executeQuery($phql);
            $dbserverips = [];
            $arrayIndexDBIP = 0;
            foreach($resdb as $res) {
                $dbserverips[$arrayIndexDBIP] = trim($res->dbconnection_string);
                $arrayIndexDBIP += 1;
            }

            if (is_null($dbserverips)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Database Server does not exist!'));
                return $this->app->response;
            }

            # Insert into epm_sites table
            $objsite = new sites();
            $siteid = $objsite->setSites($companyid, $sitename, 'Creating');
            if ($siteid) {
                $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'Success','message' => 'Site being created', 'siteid' => $siteid));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site already exists!'));
            }

            # Subsite database entry in epm_database.
            $database_name = substr(md5($sitename."_".$environmentname."_".$siteid), 0, 12); 
            $dbtype = 'Mysql';
            $dbversion = '5.5.47';
            $dbhostname = '';
            $status = 'active';
            $external_hostname = '';
            $primary_db_id = '1';
            $isprimary = 'TRUE';
            $serverfunction = 'dbserver';

            # Get DB connection information.
            if (($res = $this->modelsManager->executeQuery("

                SELECT A1.id AS database_server_id, A2.serverid AS dbid, A1.db_external_hostname AS dbconnection_string, A1.db_port AS dbport, 
                A1.db_server_username AS dbusername, A1.db_server_password AS dbpassword  
                FROM databaseserver A1
                JOIN server A2 on A1.serverid = A2.serverid
                JOIN xrefServerCompany A3 ON A2.serverid = A3.serverid 
                WHERE A1.is_primary_db_server = 1 AND A1.status = 'active' AND A2.serverstatus = 'active' and 
                A3.backendcompanyid = :companyid: AND A2.environmentid = :environmentid:
                LIMIT 1 ", array(
                'companyid'=> $companyid,
                'environmentid'=> $environmentid,))) && isset($res[0]) 
                && ($dbconnection_string = &trim($res[0]->dbconnection_string)) &&
                ($dbusername = &trim($res[0]->dbusername)) &&
                ($dbpassword = &trim($res[0]->dbpassword)) &&
                ($database_server_id = &trim($res[0]->database_server_id)) &&
                ($dbport = &trim($res[0]->dbport))) {
                   error_log($companyid.','.$environmentid.','.$dbconnection_string.','.$dbusername.','.$dbpassword.','.$dbport.','.$primaryserverid);
            }
                
            $db_ip = $dbconnection_string;
            $db_internal_hostname = '';
            $db_external_hostname = $dbconnection_string;
            $db_port = $dbport;
            $db_type = $dbtype;
            $db_version = $dbversion;
            $db_server_username = $dbusername;
            $db_server_password = $dbpassword;
            $status = 'active'; 

            $database = new database();
            $databaseid = $database->setDatabase($database_name, $dbusername, $dbpassword, $dbconnection_string, $dbport, $dbtype, $dbversion, $dbhostname, $environmentid, $isprimary, $status, $external_hostname, $primary_db_id, $database_server_id);

	    $NewSite = trim($sitename)."_".trim($companyid)."_".trim($environmentid)."_".trim($siteid);
            #INSERT INTO epm_xref_site_environment
            $drupalfolderpath = '/var/www/html/'.$NewSite;
            $drup_dbname = $database_name;
            $git_branch_id = '1';
            $database_uses_global_password = 'TRUE';
            $database_id = $databaseid;
            $gitid = '1';
            $sitedomainname = $siteurl;
            $username = ''; 
            $password = '';
            $site_status = '';
            $objsiteenv = new siteenvironment();
            $xrefid = $objsiteenv->setSite(trim($siteid), trim($environmentid), trim($drupalfolderpath), trim($serverid), trim($sitedomainname), trim($drup_dbname), trim($dbconnection_string), trim($username), trim($password), trim($git_branch_id), trim($database_uses_global_password), trim($database_id), trim($gitid), trim($site_status));
            if ($xrefid) {
                $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'Success', 'message' => 'Site environment reference created', 'xrefid' => $xrefid));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site environment reference already exists!'));
            }

            $repopath =  $_SERVER['DOCUMENT_ROOT'];
            $client = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech']);
            $gitresponse = $client->request('POST','/phalcon-rest-v1/integrategit/', ['query' => ['repopath' => $repopath, 'companyid' => $companyid, 'clientgit' => $git_url, 'siteid' => $siteid, 'clienttoken' => $clienttoken, 'clientuser' => $gituser, 'sitenameforgit' => $sitename, 'repo_name' => $repo_name, 'environmentname' => strtolower($environmentname), 'branchsuffix' => $branch_name]])->getBody()->getContents();
            $gitdecode = json_decode($gitresponse);
            $gitencode = json_encode($gitdecode);
            $gitdecodedata = json_decode($gitencode);
            if ($gitdecodedata->Status != 'Success') {
               $this->response = array(
                        "Status"                => "Failure",
                        "MsgCode"               => "-1",
                        "MsgDescription"=> "Failed, reason: Git operations has not successfully done",
                    );  
               return $this->response;
            } else {
                $jsongitdata = $gitdecodedata->Response;
                $ClientPublicRepo = $jsongitdata->ClientPublicRepo;
                $PrivateRepoDirectory = $jsongitdata->PrivateRepoDirectory;
                $PrivateRepoGitUrl = $jsongitdata->PrivateRepoGitUrl;
                $PrivateClientBranch = $jsongitdata->PrivateClientBranch;
            }

            if ($InstallType == 'GIT' || $InstallType == 'GITHUB') {
                // Loop for multiple server deployment.
                $ListenerRes = [];
                $arrayIndexLis = 0;
                foreach($serverips as $serverip)
                {
                    $server_id = $objserv->getServerIDByIP($serverip);
                    if($server_id == $primaryserverid) {
                        $primary_server = TRUE;
                    } else {
                        $primary_server = FALSE;
                    }

                    try {
                        $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                        $getListenerRes = $clientlistener->request('GET',
                            '/Listener.php',
                            ['query' => ['apikey' => $apikey, 'sitename' => $sitename, 'companyid' => $companyid, 'giturl' => $git_url, 'siteurl' => $siteurl, 'environment' => $environmentname, 'InstallType' => $InstallType, 'Distribution' => $drupal_distribution, 'distributionurl' => '', 'drup_dbname' => $drup_dbname, 'db_ip' => $dbconnection_string, 'dbuser' => $dbusername, 'password' => $dbpassword, 'NewSite' => $NewSite, 'listeneraction' => 'createsite', 'primary_server' => $primary_server, 'ClientPublicRepo' => $ClientPublicRepo, 'PrivateRepoDirectory' => $PrivateRepoDirectory, 'PrivateRepoGitUrl' => $PrivateRepoGitUrl, 'PrivateClientBranch' => $PrivateClientBranch, 'dbserverips' => $dbserverips]])->getBody()->getContents();

                        $ListenerRes[$arrayIndexLis] = $getListenerRes;
                        $arrayIndexLis += 1;
                    }
                    catch(Exception $e){
                        throw new Exception("Invalid URL");
                    }
                }
                $ListenerSuccessflag = False;
                foreach ($ListenerRes as $ListenerResp) {
                    $getdecode = json_decode($getListenerRes);
                    $getencode = json_encode($getdecode);
                    $getdecodedata = json_decode($getencode);
                    if ($getdecodedata->Status == 'Success') {
                        $ListenerSuccessflag = True;
                        break;
                    }
                }
                if ($ListenerSuccessflag == True) {
                    $getlistenerdata = $getdecodedata->{'Response'};
                    $drupuser = $getlistenerdata->dbuser;
                    $druppassword = $getlistenerdata->dbpassword;
                    $allsubsiteInfo = $getlistenerdata->subsiteinfo;
                    $subsiteIDs = [];
                    // Codes for Subsite entries in database.
                    if ($allsubsiteInfo) {
                        $subsiteIDs = [];
                        $arrayIndexSubIDs = 0;
                        foreach ($allsubsiteInfo as $key => $object) {
                            $subsiteID = $this->SetRepoSubsiteTableEntry($object, $siteid, $environmentid, $serverid, $database_server_id);
                            if ($subsiteID) {
                                $subsiteIDs[$arrayIndexSubIDs] = $subsiteID;
                                $arrayIndexSubIDs += 1;
                            }
                        }
                        if (!is_null($subsiteIDs) || !empty($subsiteIDs)) {
                            $this->app->response
                                    ->setStatusCode(201, "Created")
                                    ->setJsonContent(array('status' => 'OK','message' => 'All Sub Site deployed succesfully', 'subsiteids' => $subsiteIDs));
                        } else {
                            $this->app->response
                                    ->setStatusCode(409, "Conflict")
                                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site already exists!'));
                        }
                    }

                    // Update DB User and Password on site environment
                    $siteenvobj = new siteenvironment();
                    $update = $siteenvobj->updateDBUser($siteid, $drupuser, $druppassword);
                    if (is_null($update)) {
                        error_log("SiteEnvironment table is not updated with new db user credentials");
                    }

                    // Update DB User and Password on Database table
                    $dbobj = new database();
                    $updatedb = $dbobj->updateDBUser($drup_dbname, $drupuser, $druppassword);
                    if (is_null($updatedb)) {
                        error_log("Database table is not updated with new db user credentials");
                    }

                    // Add secondry user to db
                    $dbsecondryuser = $client->request('POST','/phalcon-rest-v1/site/addusertodb/',['query' => ['apikey'=> $apikey, 'environmentid' => $environmentid, 'siteid' => $siteid]])->getBody()->getContents();
                    $getdbdecode = json_decode($dbsecondryuser);
                    $getdbencode = json_encode($getdbdecode);
                    $getdbdecodedata = json_decode($getdbencode);
                    if (($getdbdecodedata->status == 'Failed')) {
                        error_log("Secondary user for drupal db is not created!");
                    }

                    $filesystempath = $_SERVER['DOCUMENT_ROOT']."/".$NewSite;
                    $update1 = $objsite->updateSites($companyid, $sitename, 'Completed', $siteid);

                    // Codes for Subsite information response.
                    $subsiteObj = new subsite();
                    $subsiteenvObj = new subsiteenv();
                    $subsiteids = $subsiteIDs;
                    if (!is_null($subsiteids)) {
                        $subsiteResp = [];
                        $arrayIndexRes = 0;
                        foreach($subsiteids as $subsiteid) {
                            $subsiteObj = new subsite();
                            $subsiteInfo = $subsiteObj->getSubSiteData($subsiteid);
                            $subsiteenvObj = new subsiteenv();
                            $subsiteenvInfo = $subsiteenvObj->getXrefSubSiteData($subsiteid);
                            $subsitename = trim($subsiteInfo['subsitename']);
                            $subsiteurl = trim($subsiteenvInfo['subsite_domain_name']);
                            $using_own_db = trim($subsiteenvInfo['using_own_db']);
                            $subsiteResp[$arrayIndexRes] = $this->createSubSiteResponse($subsiteid, $subsitename, $subsiteurl, $using_own_db);
                            $arrayIndexRes += 1;
                        }
                    } else {
                        $subsiteResp = False;
                    }
                    $compsitedata = $this->createSiteResponse($siteid, $sitename, $siteurl, $subsiteResp);
                    if ($update1) {
                       $this->app->response
                            ->setStatusCode(201, "Created")
                            ->setJsonContent(array('status' => 'Success', 'message' => 'Site created succesfully', 'Site Data' => $compsitedata ));
                    } else {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site.'));
                    }
                    $update2 = $objsiteenv->updateSiteStatus($xrefid, 'Completed');
                    if (is_null($update2)) {
                        $this->app->response
                             ->setStatusCode(403, "Error")
                             ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site environment.'));
                    } else { 
                        $curlcommand = "curl -X POST -H \"Cache-Control: no-cache\" -F \"apikey=Tgi/s0TcTqUXxJy/Jiu9xjyaqozf0TlE/PXOzf0r2SE=\" \"http://52.9.187.253:8080/d841c50/\"";
                        exec($curlcommand,$result);
                    }
                } else {
                    $getlistenerdata = $getdecodedata->{'Response'};

                    $objsite = new sites();
                    $update1 = $objsite->updateSites($companyid, $sitename, $getlistenerdata, $siteid);
                    $objsiteenv = new siteenvironment();
                    $update2 = $objsiteenv->updateSiteStatus($xrefid, $getlistenerdata);
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'message' => 'Error in Site Creation', 'site data' => $getlistenerdata));
                    return $this->response;
                }

            } elseif ($InstallType == 'FRESH' && $drupal_distribution != 'null'){
                $objdist = new distributions();
                $distributionurl = $objdist->getDistributionurl($drupal_distribution);
                if (is_null($distributionurl)) {
                $this->app->response
                   ->setStatusCode(404, "Not Found")
                   ->setJsonContent(array('status' => 'ERROR', 'data' => 'Drupl distribution not found!'));
                }

                // Loop for multiple server deployment.
                $ListenerRes = [];
                $arrayIndexLis = 0;
                foreach($serverips as $serverip)
                {
                    $server_id = $objserv->getServerIDByIP($serverip);
                    if($server_id == $primaryserverid) {
                        $primary_server = TRUE;
                    } else {
                        $primary_server = FALSE;
                    }
                    try {
                        $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                        $getListenerRes = $clientlistener->request('GET', '/Listener.php',
                                           ['query' => ['apikey' => $apikey, 'sitename' => $sitename, 'companyid' => $companyid,'giturl' => $git_url, 'siteurl' => $siteurl, 'environment' => $environmentname, 'InstallType' => $InstallType, 'Distribution' => $drupal_distribution, 'distributionurl' => $distributionurl, 'drup_dbname' => $drup_dbname, 'db_ip' => $dbconnection_string, 'dbuser' => $dbusername, 'password' => $dbpassword, 'username' => $gitUserName, 'token' => $git_token,'repogiturl' => $RepoGitUrl, 'NewSite' => $NewSite, 'listeneraction' => 'createsite', 'primary_server' => $primary_server, 'description' => $Description, 'ClientPublicRepo' => $ClientPublicRepo, 'PrivateRepoDirectory' => $PrivateRepoDirectory, 'PrivateRepoGitUrl'=> $PrivateRepoGitUrl, 'PrivateClientBranch' => $PrivateClientBranch, 'dbserverips' => $dbserverips]])->getBody()->getContents();

                        $ListenerRes[$arrayIndexLis] = $getListenerRes;
                        $arrayIndexLis += 1;
                    }
                    catch(Exception $e){
                        throw new Exception("Invalid URL");
                    }
                }
                $ListenerSuccessflag = False;
                foreach ($ListenerRes as $ListenerResp) {
                    $getdecode = json_decode($getListenerRes);
                    $getencode = json_encode($getdecode);
                    $getdecodedata = json_decode($getencode);
                    if ($getdecodedata->Status == 'Success') {
                        $ListenerSuccessflag = True;
                        break;
                    }
                }
                if ($ListenerSuccessflag == True) {
                    $getlistenerdata = $getdecodedata->{'Response'};
                    $drupuser = $getlistenerdata->dbuser;
                    $druppassword = $getlistenerdata->dbpassword;
                    // Update DB User and Password on site environment
                    $siteenvobj = new siteenvironment();
                    $update = $siteenvobj->updateDBUser($siteid, $drupuser, $druppassword);
                    if (is_null($update)) {
                        error_log("SiteEnvironment table is not updated with new db user credentials");
                    }
                    // Update DB User and Password on Database table
                    $dbobj = new database();
                    $updatedb = $dbobj->updateDBUser($drup_dbname, $drupuser, $druppassword);
                    if (is_null($updatedb)) {
                        error_log("Database table is not updated with new db user credentials");
                    }

                    // Add secondry user to db
                    $dbsecondryuser = $client->request('POST','/phalcon-rest-v1/site/addusertodb/',['query' => ['apikey'=> $apikey, 'environmentid' => $environmentid, 'siteid' => $siteid]])->getBody()->getContents();
                    $getdbdecode = json_decode($dbsecondryuser);
                    $getdbencode = json_encode($getdbdecode);
                    $getdbdecodedata = json_decode($getdbencode);
                    if (($getdbdecodedata->status == 'Failed')) {
                        error_log("Secondary user for drupal db is not created!");
                    }

                    $filesystempath = $_SERVER['DOCUMENT_ROOT']."/".$NewSite;
                    $update1 = $objsite->updateSites($companyid, $sitename, 'Completed', $siteid);
                    $subsiteResp = False;
                    $compsitedata = $this->createSiteResponse($siteid, $sitename, $siteurl, $subsiteResp);
                    if ($update1) {
                        $this->app->response
                            ->setStatusCode(201, "Created")
                            ->setJsonContent(array('status' => 'Success', 'message' => 'Site created succesfully', 'Site Data' => $compsitedata));
                    } else {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site.'));
                    }
                    $update2 = $objsiteenv->updateSiteStatus($xrefid, 'Completed');
                    if (is_null($update2)) {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site environment.'));
                    } else {
                        $curlcommand = "curl -X POST -H \"Cache-Control: no-cache\" -F \"apikey=Tgi/s0TcTqUXxJy/Jiu9xjyaqozf0TlE/PXOzf0r2SE=\" \"http://52.9.187.253:8080/d841c50/\"";
                        exec($curlcommand, $result);
                    }
                } else {
                    $getlistenerdata = $getdecodedata->{'Response'};
                    $objsite = new sites();
                    $update1 = $objsite->updateSites($companyid, $sitename, $getlistenerdata, $siteid);
                    $objsiteenv = new siteenvironment();
                    $update2 = $objsiteenv->updateSiteStatus($xrefid, $getlistenerdata);
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'message' => 'Error in Site Creation', 'site data' => $getlistenerdata));
                }
            }
        }
        catch(Exception $e) {
            echo $e->getMessage();
        } 
        return $this->app->response;
     }

    public function delete($siteid) {
        if (is_null($siteid)) {
            $flag = "failed";
            $returnmsg = http_response_code(404);
            $msgdesc = "Site ID does not exist!";
            return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
        }
        $objsiteid = new sites();
        $sitestatus = $objsiteid->getsitestatus($siteid);

        if (trim($sitestatus) == 'deleted') {
            $this->app->response
                ->setStatusCode(403, "Error")
                ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site has Already Deleted'));
        } else {
            try {
                # Get Sitename
                $sitename = $objsiteid->getSiteName($siteid);
                if (is_null($sitename)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Name does not exist!'));
                }

                # Get companyid 
                $companyid = $objsiteid->getCompanyId($siteid);
                if (is_null($companyid)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                # Get Site URL
                $objsiteenv = new siteenvironment();
                $siteurl = trim($objsiteenv->getSiteUrl($siteid));
                if (is_null($siteurl)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Url does not exist!'));
                }

                # Get environmentid 
                $environmentid = $objsiteenv->getEnvironmentId($siteid);
                if (is_null($environmentid)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                # Get xrefid 
                $xrefid = $objsiteenv->getXrefID($siteid);
                if (is_null($xrefid)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                # Get drup_dbname 
                $drup_dbname = $objsiteenv->getDrupDBName($siteid);
                if (is_null($drup_dbname)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                # Get database_server_id
                $objdatabase = new database();
                $database_server_id = $objdatabase->getDatabaseName($drup_dbname);
                if (is_null($database_server_id)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                # Get serverips
                $objserv = new server();
                $backendcompanyid = $companyid;
                $serverips = $objserv->getAllServerIP($backendcompanyid, $environmentid);
                if (is_null($serverips)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                #Get gitid
                $git_branch_id = $objsiteenv->getBranchId($siteid);
                if(is_null($git_branch_id)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Git Id does not exist!'));
                }

                # Get Site Environment values
                $siteenvalldata = $objsiteenv->getSiteEnvData($siteid);
                if(is_null($siteenvalldata)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Git Information does not exist!'));
                }
                $PrivGitID = $siteenvalldata["gitid"];
                $PrivGitBranchID = $siteenvalldata["git_branch_id"];

                # Get Private Git Branch information
                $objgitbranch = new gitbranch();
                $PrivGitBranchalldata = $objgitbranch->getGitBranchAllData($PrivGitBranchID);
                $PrivGitID = $PrivGitBranchalldata["git_id"];
                $PrivHookID = $PrivGitBranchalldata["hookid"];
                $pubGitBranchID = $PrivGitBranchalldata["connected_to"];

                # Get Private Git Information
                $gitObj = new git();
                $PrivGitInfo = $gitObj->getGitallData($PrivGitID);
                $PrivGitURL = $PrivGitInfo["git_url"];
                $PrivGitUName = $PrivGitInfo["git_username"];
                $PrivGitToken = $PrivGitInfo["github_token"];
                $PrivGitRepoName = $this->getGitRepoName($PrivGitURL);

                # Delete Private repository hook
                $privwebhookdelete = $this->deleteWebHook($PrivGitUName, $PrivGitRepoName, $PrivGitToken, $PrivHookID);
                if ($privwebhookdelete == 'True') {
                    $objgitbranch = new gitbranch();
                    $updatestatus = $objgitbranch->updateGitBranchStatus($PrivGitBranchID, '0');
                } else {
                    $this->app->response
                        ->setStatusCode(400, "Bad Request")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Private Webhook is not deleted'));   
                }
                # Get Public Git Branch information
                $objgitbranch = new gitbranch();
                $PubGitBranchalldata = $objgitbranch->getGitBranchAllData($pubGitBranchID);
                $PubGitID = $PubGitBranchalldata["git_id"];
                $PubHookID = $PubGitBranchalldata["hookid"];

                # Get Public Git Information
                $gitObj = new git();
                $PubGitInfo = $gitObj->getGitallData($PubGitID);
                $PubGitURL = $PubGitInfo["git_url"];
                $PubGitUName = $PubGitInfo["git_username"];
                $PubGitToken = $PubGitInfo["github_token"];
                $PubGitRepoName = $this->getGitRepoName($PubGitURL);

                # Delete Public repository hook
                $pubwebhookdelete = $this->deleteWebHook($PubGitUName, $PubGitRepoName, $PubGitToken, $PubHookID);
                if ($pubwebhookdelete == 'True') {
                    $objgitbranch = new gitbranch();
                    $updatestatus = $objgitbranch->updateGitBranchStatus($pubGitBranchID, '0');
                } else {
                    $this->app->response
                        ->setStatusCode(400, "Bad Request")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Public Webhook is not deleted'));
                }
                // Need to ensure that it calls the delete funciton in the Listener. *****
                $deletesitename = trim($sitename)."_".trim($companyid)."_".trim($environmentid)."_".trim($siteid);

                // Loop for multiple server.
                foreach($serverips as $serverip)
                {
                    try {
                        $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                        $getListenerRes = $clientlistener->request('GET', '/Listener.php',
                            ['query' => ['deletesitename' => $deletesitename, 'siteurl' => $siteurl, 'listeneraction' => 'deletesite']])->getBody()->getContents();
                    }
                    catch(Exception $e) {
                        throw new Exception("Invalid URL");
                    }
                }

                $getdecode = json_decode($getListenerRes);
                $getencode = json_encode($getdecode);
                $getdecodedata = json_decode($getencode);
                // Check result and then take action
                if (($getdecodedata->Status == 'Success')) {
                    $update1 = $objsiteid->updateSiteStatus('deleted', $siteid);
                    if ($update1) {
                        $this->app->response
                            ->setStatusCode(201, "deleted")
                            ->setJsonContent(array('status' => 'Success', 'message' => 'Site deleted succesfully', 'data' => $update1));
                    } else {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the status of the site.'));
                    }
                    $update2 = $objsiteenv->updateSiteStatus($xrefid, 'Deleted');
                    if (is_null($update2)) {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site environment.'));
                    }
                    $update3 = $objdatabase->updateDatabaseStatus($drup_dbname, 'Deleted');
                    if (is_null($update3)) {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating database status.'));
                    }
                } else {
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Deletion Failed '));
                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
        return $this->app->response;
    }

    // *******  Subsite Codes  *******
    public function createSiteResponseSub($mergesitedata, $compsubsitedata) {
        $siteid = $mergesitedata['siteid'];
        $sitename = $mergesitedata['sitename'];
        $DevSiteURL = $mergesitedata['sitedomainname'];
        $DevAdminSiteURL = $mergesitedata['sitedomainname'].'/user';
        $DevSiteStatus = 'Deployed';
        $Dev = '1';
        
        $data = array("siteid" => $siteid,
                      "sitename" => $sitename,
                      "DevSiteURL" => $DevSiteURL,
                      "DevAdminSiteURL" => $DevAdminSiteURL,
                      "DevSiteStatus" => $DevSiteStatus,
                      "Dev" => $Dev,
                      "Subsite Data" => $compsubsitedata
                     );
       return $data;
    }

    public function createSubsite()
    {
	try{
            // Get input to the create subsite controller. 
            $apikey =  $this->app->request->get("apikey");
            $subsitename = $this->app->request->get("subsitename");
            $siteid = $this->app->request->get("siteid");
            $frontendcompanyid = $this->app->request->get("companyid");
            $subsiteurl =  $this->app->request->get("subsiteurl");
            $git_url =  $this->app->request->get("giturl");
            $using_own_db =  $this->app->request->get("using_own_db");

            // Check if API Key is null.
            if (!isset($apikey) || empty($apikey)) {
                $this->app->response
                      ->setStatusCode(404, "Not Found")
                      ->setJsonContent(array('status' => 'ERROR', 'data' => 'API Key not Posted or is Empty!'));
                return $this->app->response;
            }

            // Check if subsite name is null.
            if (!isset($subsitename) || empty($subsitename)) {
                $this->app->response
                      ->setStatusCode(404, "Not Found")
                      ->setJsonContent(array('status' => 'ERROR', 'data' => 'Subsite name not Posted or is Empty!'));
                return $this->app->response;
            }

            // Check if subsite own db value is null.
            if (!isset($using_own_db) || empty($using_own_db)) {
                $this->app->response
                      ->setStatusCode(404, "Not Found")
                      ->setJsonContent(array('status' => 'ERROR', 'data' => 'Using own db not Posted or is Empty!'));
                return $this->app->response;
            }

            // Check for Subsite url
            $objsubsiteenv = new subsiteenv();
            $checksubsiteurl = $objsubsiteenv->checkSubSiteUrl($subsiteurl);
            if (!is_null($checksubsiteurl)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Subsite Url Already Exist!'));
                return $this->app->response;
            }

            // Get Environmentid
            $objsiteenv = new siteenvironment();
            $environmentid = $objsiteenv->getEnvironmentId($siteid);
            if (is_null($environmentid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environment does not exist!'));
            }

            // Match the company id within the DB backendcompanyid from company id
            $objcompany = new company();
            $companyname = $objcompany->getBackendCompanyName($frontendcompanyid);
            if (is_null($companyname)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
            }

            // Get the Backendcompany ID  
            $companyid = $objcompany->getBackendCompanyIdbyFrontend($frontendcompanyid);
            if (is_null($companyid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
            }
        
            // Get serverip 
            $objserv = new server();
	    $backendcompanyid = $companyid;
            $serverips = $objserv->getAllServerIP($backendcompanyid, $environmentid);
            if (is_null($serverips)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            $dbserverips = $objserv->getAllDBServerIP($backendcompanyid, $environmentid);
            if (is_null($dbserverips)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            // Get the Server ID
            $tempserverid = $objserv->getServerID($backendcompanyid, $environmentid);
            $serverid = trim($tempserverid);
            if (is_null($serverid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }
            $primaryserverid = $serverid;

            // Get Private Git ID by using siteid
            $objxrefGitSites = new xrefGitSites();
            $gitsitedata = $objxrefGitSites->getGitSitebySiteID($siteid);
            $sitegitid = $gitsitedata['gitid'];
            // Get All Data by Git ID from epm_git table
            $git = new git();
            $gitalldata = $git->getGitallData($sitegitid);
            $gitrepo_access_type = $gitalldata['repo_access_type'];
            if ($gitrepo_access_type == 'private') {
                $sitegitusername = $gitalldata['git_username'];
                $sitegittoken = $gitalldata['github_token'];
                $sitegiturl = $gitalldata['git_url'];
            } else {
                $this->app->response
                     ->setStatusCode(403, "Error")
                     ->setJsonContent(array('status' => 'ERROR', 'message' => 'Site does not have private repository'));
                return $this->response;
            }

            // Subsite database entry in epm_database.
            $database_name = '';
            $dbtype = 'Mysql';
            $dbversion = '5.5.47';
            $dbhostname = '';
            $isprimary = 'TRUE';
            $status = 'active';
            $external_hostname = '';
            $primary_db_id = '1';
            $serverfunction = 'dbserver';

            //Get DB connection information.
            if (($res = $this->modelsManager->executeQuery("

                SELECT A1.id AS database_server_id, A2.serverid AS dbid, A1.db_external_hostname AS dbconnection_string, A1.db_port AS dbport, 
                A1.db_server_username AS dbusername, A1.db_server_password AS dbpassword  
                FROM databaseserver A1
                JOIN server A2 on A1.serverid = A2.serverid
                JOIN xrefServerCompany A3 ON A2.serverid = A3.serverid 
                WHERE A1.is_primary_db_server = 1 AND A1.status = 'active' AND A2.serverstatus = 'active' and 
                A3.backendcompanyid = :companyid: AND A2.environmentid = :environmentid:
                LIMIT 1 ", array(
                'companyid'=> $companyid,
                'environmentid'=> $environmentid,))) && isset($res[0]) 
                && ($dbconnection_string = &trim($res[0]->dbconnection_string)) &&
                ($dbusername = &trim($res[0]->dbusername)) &&
                ($dbpassword = &trim($res[0]->dbpassword)) &&
                ($database_server_id = &trim($res[0]->database_server_id)) &&
                ($dbport = &trim($res[0]->dbport))) {
                   error_log($companyid.','.$environmentid.','.$dbconnection_string.','.$dbusername.','.$dbpassword.','.$dbport.','.$primaryserverid);
            }

            $db_ip = $dbconnection_string;
            $db_internal_hostname = '';
            $db_external_hostname = $dbconnection_string;
            $db_port = $dbport;
            $db_type = $dbtype;
            $db_version = $dbversion;
            $db_server_username = $dbusername;
            $db_server_password = $dbpassword;
            $status = 'active';
        
            if ($using_own_db == 'TRUE') {
                $database = new database();
                $databaseid = $database->setDatabase($database_name, $dbusername, $dbpassword, $dbconnection_string, $dbport, $dbtype, $dbversion, $dbhostname, $environmentid, $isprimary, $status, $external_hostname, $primary_db_id, $database_server_id);
            } else {
                $xrefsitedata = $objsiteenv->getXrefSiteData($siteid,$environmentid);
                $databaseid = $xrefsitedata['database_id'];
            }

            // Get the Site Name
            $objsite = new sites();
            $sitename = $objsite->getSiteName($siteid);
            if (is_null($sitename)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site does not exist!'));
            }

            // Get site drupal installation path
            $objsiteenv = new siteenvironment();
            $drupalfolderpath = trim($objsiteenv->getSiteDrupalPath($siteid));
            if (is_null($drupalfolderpath)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Drupal site folderpath does not exist!'));
            }

            $subsitedbdeploymentschema = 'Creating';
            $dbname = '';
            $gitbranchsubsitestatus = 'No';
            $file_systempath = trim($drupalfolderpath)."/sites/".$subsiteurl;
            $drup_dbname = '';
            $eipaddressserver = '';
            $sitedomainname = $subsiteurl;
            $subsite_status = 'Creating';
        
            // Insert the Subsite information on epm_sub_site table.
            $objsubsite = new subsite();
            $subsiteid = $objsubsite->setSubsite(trim($subsitedbdeploymentschema), trim($dbname), trim($environmentid), trim($siteid), trim($subsitename), trim($gitbranchsubsitestatus), trim($file_systempath), trim($drup_dbname), trim($dbconnection_string), trim($dbusername), trim($dbpassword), trim($eipaddressserver), trim($subsite_status));
            if ($subsiteid) {
                $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'OK','message' => 'Sub Site created succesfully', 'subsiteid' => $subsiteid));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site already exists!'));
            }

            // Check Subsite own db and assign Database Name.
            if ($using_own_db == 'TRUE') {
                $subsite_db = substr(md5($subsitename."_".$companyid."_".$environmentid."_".$siteid."_".$subsiteid), 0, 12);
            } else {
                $subsite_db = substr(md5($subsitename),0, 12);
            }
            $database_name = $subsite_db;
            $dbname = $subsite_db;
            $drup_dbname = $subsite_db;

            if ($using_own_db == 'TRUE') {
               $databaseid = $database->updateDatabaseName($databaseid, $database_name);
            }
            $subsiteid = $objsubsite->updateSubsiteDB($subsiteid, $dbname, $drup_dbname);

            // INSERT subsite environment information into epm_xref_subsite_environment
            $subsite_path = $_SERVER['DOCUMENT_ROOT']."/".$sitename."/sites/".$subsiteurl; 
            $subsite_domain_name = $subsiteurl; 
            $database_id = $databaseid; 
            $git_branch_id ='1'; 
            $database_uses_global_password = 'TRUE'; 
            $using_separate_branch = 'TRUE'; 

            $id = $objsubsiteenv->setSubSiteEnv(trim($subsiteid), trim($environmentid), trim($subsite_path), trim($serverid), trim($subsite_domain_name), trim($database_id), trim($database_name), trim($dbusername), trim($dbpassword), trim($git_branch_id), trim($database_uses_global_password), trim($using_separate_branch), $sitegitid, trim($using_own_db), trim($subsite_status));
            if ($id) {
                $this->app->response
                         ->setStatusCode(201, "Created")
                         ->setJsonContent(array('status' => 'OK', 'message' => 'Sub Site environment reference created', 'id' => $id));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Sub Site environment reference already exists!'));
            }

            $ListenerRes = [];
            $arrayIndexLis = 0;
            foreach($serverips as $serverip)
            {
                $server_id = $objserv->getServerIDByIP($serverip);
                if($server_id == $primaryserverid) {
                    $primary_server = TRUE;
                } else {
                    $primary_server = FALSE;
                }
                try {
                    $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                    $getListenerRes = $clientlistener->request('GET', '/Listener.php',
                                        ['query' => ['sitename' => trim($sitename), 'subsitename' => $subsitename, 'drupfolderpath' => $drupalfolderpath, 'subsiteurl' => $subsiteurl, 'drup_dbname' => $drup_dbname, 'db_ip' => $dbconnection_string, 'dbuser' => $dbusername, 'password' => $dbpassword, 'listeneraction' => 'createsubsite', 'primary_server' => $primary_server, 'dbserverips' => $dbserverips, 'sitegitusername' => $sitegitusername, 'sitegittoken' => $sitegittoken, 'sitegiturl' => $sitegiturl]])->getBody()->getContents();
                    $ListenerRes[$arrayIndexLis] = $getListenerRes;
                    $arrayIndexLis += 1;
                }
	        catch(Exception $e){
                    throw new Exception("Invalid URL");
	        }
            }
            $ListenerSuccessflag = False;
            foreach ($ListenerRes as $ListenerResp) {
                $getdecode = json_decode($getListenerRes);
                $getencode = json_encode($getdecode);
                $getdecodedata = json_decode($getencode);
                if ($getdecodedata->Status == 'Success') {
                    $ListenerSuccessflag = True;
                    break;
                }
            }
            if ($ListenerSuccessflag == True) {
                $getlistenerdata = $getdecodedata->{'Response'};
                $drupuser = $getlistenerdata->dbuser;
                $druppassword = $getlistenerdata->dbpassword;

                //update DB User and pass on subsite
                $updatesubsitedb = $objsubsite->updateDB($subsiteid, $drupuser, $druppassword);
                if (is_null($updatesubsitedb)) {
                    error_log("subsite table is not updated with new db user credentials");
                }

                // Update DB User and Password on site environment
                $subsiteenvobj = new subsiteenv();
                $update = $subsiteenvobj->updateDBUser($subsiteid, $drupuser, $druppassword);
                if (is_null($update)) {
                    error_log("SiteEnvironment table is not updated with new db user credentials");
                }

                // Update DB User and Password on Database table
                $dbobj = new database();
                $updatedb = $dbobj->updateDBUser($drup_dbname, $drupuser, $druppassword);
                if (is_null($updatedb)) {
                    error_log("Database table is not updated with new db user credentials");
                }
                $filesystempath = $_SERVER['DOCUMENT_ROOT'] . "/" . $sitename . "_" . $companyid . "_" . $environmentid . "_" . $siteid . "/sites/" . $subsiteurl;
                $update1 = $objsubsite->updateSubSite(trim($subsiteid), trim($subsitename), trim($filesystempath), 'Completed');
                $sitedata = $objsite->getSiteData($siteid);
                $xrefsitedata = $objsiteenv->getXrefSiteData($siteid,$environmentid);
                $mergesitedata = array_merge($sitedata,$xrefsitedata);
                $subsiteenvObj = new subsiteenv();
                $subsiteenvInfo = $subsiteenvObj->getXrefSubSiteData($subsiteid);
                $using_own_db = trim($subsiteenvInfo['using_own_db']);
                $compsubsitedata = $this->createSubSiteResponse($subsiteid, $subsitename,$subsiteurl,$using_own_db);
                $compsitedata = $this->createSiteResponseSub($mergesitedata,$compsubsitedata);

                if ($update1) {
                    $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'OK', 'message' => 'Sub Site created succesfully', 'Site Data' => $compsitedata));
                } else {
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the Sub-site.'));
                }
                $update2 = $objsubsiteenv->updateSubSiteStatus($id, 'Completed');
                if (is_null($update2)) {
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the Sub-site environment.'));
                } else {
                    $curlcommand = "curl -X POST -H \"Cache-Control: no-cache\" -F \"apikey=Tgi/s0TcTqUXxJy/Jiu9xjyaqozf0TlE/PXOzf0r2SE=\" \"http://52.9.187.253:8080/d841c50/\"";
                    exec($curlcommand,$result);
                }
            } else {
                $getlistenerdata = $getdecodedata->{'Response'};
                $objsubsite = new subsite();
                $update1 = $objsubsite->updateSubSite(trim($subsiteid), trim($subsitename), trim($filesystempath), $getlistenerdata);
                $objsubsiteenv = new subsiteenv();
                $update2 = $objsubsiteenv->updateSubSiteStatus($id, $getlistenerdata);
                $this->app->response
                    ->setStatusCode(403, "Error")
                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'SubSite creation Failed ', 'SubSite data' => $getlistenerdata));
            }
	}
	catch(Exception $e){
	    echo $e->getMessage();
	}   
        return $this->app->response;
    }

    public function deleteSubsite($subsiteid) {
         
        if (is_null($subsiteid)) {
             $flag = "failed";
             $returnmsg = http_response_code(404);
             $msgdesc = "SubSite ID does not exist!";
             return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
        }
        $objsubsite = new subsite();
        $sitestatus = $objsubsite->getSubsitestatus($subsiteid);

        if (trim($sitestatus) == 'deleted') {
                $this->app->response
                    ->setStatusCode(403, "Error")
                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site is already Deleted'));
        } else {
            try {
                #Get subsite id
                $siteid = $objsubsite->getSiteid($subsiteid);
                if(is_null($siteid)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Siteid does not exist!'));
                }

                # Get drup_dbname
                $drup_dbname = $objsubsite->getDrupDBName($subsiteid);
                if(is_null($drup_dbname)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'DB Name does not exist!'));
                }

                # Get database_server_id
                $objdatabase = new database();
                $database_server_id = $objdatabase->getDatabaseName($drup_dbname);
                if (is_null($database_server_id)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'DB Server ID does not exist!'));
                }

                # Get companyid
                $objsite = new sites();
                $companyid = $objsite->getCompanyId($siteid);
                if (is_null($companyid)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                #Get subsiteurl
                $objsubsiteenv = new subsiteenv();
                $subsiteurl = $objsubsiteenv->getSubSiteUrl($subsiteid);
                if (is_null($subsiteurl)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Sub Site Url does not exist!'));
                }

                # Get environmentid
                $environmentid = $objsubsiteenv->getEnvironmentId($subsiteid);
                if (is_null($environmentid)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                # Get serverips
                $objserv = new server();
                $backendcompanyid = $companyid;
                $serverips = $objserv->getAllServerIP($backendcompanyid, $environmentid);
                if (is_null($serverips)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                }

                #Get File Systempath
                $filesystempath = $objsubsite->getfilesystempath($subsiteid);
                if (is_null($filesystempath)){
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Filesystempath does not exist!'));
                }

                foreach($serverips as $serverip)
                {
                    try {
                        $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                        $getListenerRes = $clientlistener->request('GET', '/Listener.php',
                                           ['query' => ['filesystempath' => trim($filesystempath), 'subsiteurl' => trim($subsiteurl), 'listeneraction' => 'deletesubsite']])->getBody()->getContents();
                    }
                    catch(Exception $e)
                    {
                        echo $e->getMessage();
                    }
                }
                $getdecode = json_decode($getListenerRes);
                $getencode = json_encode($getdecode);
                $getdecodedata = json_decode($getencode);
                // Check result and then take action
                if (($getdecodedata->Status == 'Success')) {
                    $update1 = $objsubsite->updatSubSiteStatus('deleted', $subsiteid);
                    if ($update1) {
                        $this->app->response
                            ->setStatusCode(201, "deleted")
                            ->setJsonContent(array('status' => 'OK', 'message' => 'Site deleted succesfully', 'data' => $update1));
                    } else {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the status of the site.'));
                    }
                    $update2 = $objsubsiteenv->updateSubSiteEnvStatus($subsiteid, 'deleted');
                    if (is_null($update2)) {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the Sub-site environment.'));
                    }
                    $update3 = $objdatabase->updateDatabaseStatus($drup_dbname, 'Deleted');
                    if (is_null($update3)) {
                        $this->app->response
                            ->setStatusCode(403, "Error")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating database status.'));
                    }
                } else {
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Deletion Failed '));
                }
            } catch(Exception $e){
                echo $e->getMessage();
            }
        }
        return $this->app->response;
    }

    public function getSitesIPAndDeploymentPath($siteid, $environmentname)
    {
        try {
            if (!isset($siteid) || empty($siteid)) {
                $this->app->response
                     ->setStatusCode(404, "Not Found")
                     ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site ID not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($environmentname) || empty($environmentname)) {
                $this->app->response
                     ->setStatusCode(404, "Not Found")
                     ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environement Name not Posted or is Empty!'));
                return $this->app->response;
            }

            # Get environmentid
            $objenv = new environment();
            $environmentid = $objenv->getEnvironmentId($environmentname, 'active');
            if (is_null($environmentid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environment does not exist!'));
                return $this->app->response;
            }

            # Get Site data
            $objsite = new sites();
            $sitedata = $objsite->getSiteData($siteid);
            if (is_null($sitedata) || empty($sitedata)) {
                $this->app->response
                     ->setStatusCode(404, "Not Found")
                     ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site does not exist!'));
                return $this->app->response;
            }
            $sitename = trim($sitedata['sitename']);
            $companyid = trim($sitedata['companyid']);

            # Get Site Environment data
            $objsiteenv = new siteenvironment();
            $siteenvalldata = $objsiteenv->getSiteEnvData($siteid);
            $siteDeployPath = trim($siteenvalldata['drupalfolderpath']);
            $siteenvironmentid = trim($siteenvalldata['environmentid']);

            if ($environmentid == $siteenvironmentid) {
                # Get serverips 
                $objserv = new server();
                $serverips = $objserv->getAllServerIP($companyid, $environmentid);
                if (is_null($serverips)){
                    $this->app->response
                         ->setStatusCode(404, "Not Found")
                         ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                    return $this->app->response;
                }
            } else {
                $this->app->response
                     ->setStatusCode(404, "Not Found")
                     ->setJsonContent(array('status' => 'ERROR', 'data' => 'Wrong environment input'));
                    return $this->app->response;
            }

            $serveripres = [];
            $arrayIndex = 0;
            foreach($serverips as $serverip) {
                $serveripres = array_merge($serveripres, array('Server-'.($arrayIndex+1) => $serverip));
                $arrayIndex += 1;
            }
            $siteDeploydata = array(
                            "Sitename" => $sitename,
                            "ServerIP" => $serveripres,
                            "SiteDeploymentPath" => $siteDeployPath
                        );
            if ($serveripres) {
                $this->app->response
                    ->setStatusCode(201, "Success")
                    ->setJsonContent(array('status' => 'OK', 'message' => 'Site deploy information', 'Site Data' => $siteDeploydata));
            } else {
                $this->app->response
                    ->setStatusCode(403, "Error")
                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for Site Deploy information'));
            }
        } catch(Exception $e) {
            echo $e->getMessage();
        }
        return $this->app->response;
    }
}
