<?php
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
class EpmsubsitesController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }

    public function createSiteResponse($mergesitedata, $compsubsitedata) {
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

    public function createSubSiteResponse($subsiteid, $subsitename,$subsiteurl,$using_own_db) {
       $devadminsubsiteurl = $subsiteurl.'/user';
       $devsubsitestatus = "Deployed";
       if ($using_own_db == 'TRUE') {
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

    public function create()
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

            // Validate the API Key.
            $objapikey = new apikey();
            $apikeyid = $objapikey->getApikeyId($apikey);
            if (is_null($apikeyid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'API Key does not exist!'));
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

            // Get the Server ID
            $tempserverid = $objserv->getServerID($backendcompanyid, $environmentid);
            $serverid = trim($tempserverid);
            if (is_null($serverid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }
            $primaryserverid = $serverid;

            //Get Input to the script for Git Repository create.
            $git = new git();
            $gitres = $git->getGitDatabyCompanyId($companyid);
            $gitencode = json_encode($gitres);
            $gitdecode = json_decode($gitencode);
            $gitid = $gitdecode->git_id;
            $gitUserName = $gitdecode->git_username;
            $git_token = $gitdecode->github_token;
            $RepoGitUrl = $gitdecode->git_url;
            $Description = "Initial checkin while creating subsite!";


            // Subsite database entry in epm_database.
            $database_name = '';
            $dbtype = 'Mysql';
            $dbversion = '5.5.47';
            $dbhostname = '';
            $isprimary = 'TRUE';
            $status = 'active';
            $external_hostname = '';
            $primary_db_id = '1';

            $serverobj = new server();
            // Get primary db server id
            $isprimary = 'TRUE';
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
            $is_primary_db_server = '1';
            $primary_db_server_id = '0';
            $status = 'active';
        
            if ($using_own_db == 'TRUE') {
                $database = new database();
                $databaseid = $database->setDatabase($database_name, $dbusername, $dbpassword, $dbconnection_string, $dbport, $dbtype, $dbversion, $dbhostname, $environmentid, $isprimary, $status, $external_hostname, $primary_db_id, $database_server_id);
            } else {
                $xrefsitedata = $objsiteenv->getXrefSiteData($siteid,$environmentid);
                $databaseid = $xrefsitedata['database_id'];
            }

            //Get Input to the script for Git Repository create.
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

            $id = $objsubsiteenv->setSubSiteEnv(trim($subsiteid), trim($environmentid), trim($subsite_path), trim($serverid), trim($subsite_domain_name), trim($database_id), trim($database_name), trim($dbusername), trim($dbpassword), trim($git_branch_id), trim($database_uses_global_password), trim($using_separate_branch), trim($gitid), trim($using_own_db), trim($subsite_status));
            if ($id) {
                $this->app->response
                         ->setStatusCode(201, "Created")
                         ->setJsonContent(array('status' => 'OK', 'message' => 'Sub Site environment reference created', 'id' => $id));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Sub Site environment reference already exists!'));
            }

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
                                        ['query' => ['sitename' => trim($sitename), 'subsitename' => $subsitename, 'drupfolderpath' => $drupalfolderpath, 'repogiturl' => $RepoGitUrl, 'subsiteurl' => $subsiteurl, 'drup_dbname' => $drup_dbname, 'db_ip' => $dbconnection_string, 'dbuser' => $dbusername, 'password' => $dbpassword, 'username' => $gitUserName, 'token' => $git_token, 'repogiturl' => $RepoGitUrl, 'listeneraction' => 'createsubsite', 'primary_server' => $primary_server, 'description' => $Description]])->getBody()->getContents();
                }
	        catch(Exception $e){
                    throw new Exception("Invalid URL");
	        }
            }
            $getdecode = json_decode($getListenerRes);
            $getencode = json_encode($getdecode);
            $getdecodedata = json_decode($getencode);

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
            if (($getdecodedata->Status == 'Success')) {
                $filesystempath = $_SERVER['DOCUMENT_ROOT'] . "/" . $sitename . "_" . $companyid . "_" . $environmentid . "_" . $siteid . "/sites/" . $subsiteurl;
                $update1 = $objsubsite->updateSubSite(trim($subsiteid), trim($subsitename), trim($filesystempath), 'Completed');

                $sitedata = $objsite->getSiteData($siteid);
                $xrefsitedata = $objsiteenv->getXrefSiteData($siteid,$environmentid);
                $mergesitedata = array_merge($sitedata,$xrefsitedata);

                $compsubsitedata = $this->createSubSiteResponse($subsiteid, $subsitename,$subsiteurl,$using_own_db);
                $compsitedata = $this->createSiteResponse($mergesitedata,$compsubsitedata);

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
                }
            }else {
                $this->app->response
                    ->setStatusCode(403, "Error")
                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site creation Failed '));
            }
	}
	catch(Exception $e){
	    echo $e->getMessage();
	}   
        return $this->app->response;
    }

    public function delete($subsiteid) {
         
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

                }else {
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Deletion Failed '));
                }
            }catch(Exception $e){
                echo $e->getMessage();
            }
        }
        return $this->app->response;
    }
}
