<?php
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
class EpmsubsitesController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
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
            if ($apikey == 'null') {
                $flag = "failed";
                $returnmsg = http_response_code(401);
                $msgdesc = "API Key does not exist!";
                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            }

            // Check if subsite name is null.
            if ($subsitename == 'null') {
                 $flag = "failed";
                 $returnmsg = http_response_code(404);
                 $msgdesc = "Subsite name does not exist!";
                 return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            }

            // Check if subsite own db value is null.
            if ($using_own_db == 'null') {
                 $flag = "failed";
                 $returnmsg = http_response_code(404);
                 $msgdesc = "Subsite own DB value does not exist!";
                 return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            }

            // Get Environmentid
            $objsiteenv = new siteenvironment();
            $environmentid = $objsiteenv->getEnvironmentId($siteid);
            if ($environmentid == null) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environment does not exist!'));
            }

            // Validate the API Key.
            $objapikey = new apikey();
            $apikeyid = $objapikey->getApikeyId($apikey);
            if ($apikeyid == null) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'API Key does not exist!'));
            }

            // Match the company id within the DB backendcompanyid from company id
            $objcompany = new company();
            $companyname = $objcompany->getBackendCompanyName($frontendcompanyid);
            if ($companyname == null) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
            }

            // Get the Backendcompany ID  
            $companyid = $objcompany->getBackendCompanyIdbyFrontend($frontendcompanyid);
            if ($companyid == null) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
            }
        
            // Get serverip 
            $objserv = new server();
	    $backendcompanyid = $companyid;
            $tempserverip = $objserv->getServerIP($backendcompanyid, $environmentid);
            $serverip = trim($tempserverip);
            if ($serverip == null){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            // Get the Server ID
            $tempserverid = $objserv->getServerID($backendcompanyid, $environmentid);
            $serverid = trim($tempserverid);
            if ($serverid == null){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

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

            $objxrefdbserv = new xrefServerDatabase();
            $databaseid = trim($objxrefdbserv->getXrefDBServId($serverid));

            //Get Input to the script for Git Repository create.
            $database = new database();
            $dbid = $databaseid; // From xref_site_environment by siteid/database_id
            $dbres = $database->getDatabase($dbid);
            $dbencode = json_encode($dbres);
            $dbdecode = json_decode($dbencode);
            $dbconnection_string = $dbdecode->databaseip;
            $dbusername = $dbdecode->database_username;
            $dbpassword = $dbdecode->database_password;
            $dbport = $dbdecode->dbport;
        
            $database = new database();
            if ($using_own_db == 'TRUE') {
                $databaseid = $database->setDatabase($database_name, $dbusername, $dbpassword, $dbconnection_string, $dbport, $dbtype, $dbversion, $dbhostname, $environmentid, $isprimary, $status, $external_hostname, $primary_db_id);
            } else {
                $databaseid = '1'; 
            }

            //Get Input to the script for Git Repository create.
            // Get the Site Name
            $objsite = new sites();
            $sitename = $objsite->getSiteName($siteid);
            if ($sitename == null){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site does not exist!'));
            }

            // Get site drupal installation path
            $objsiteenv = new siteenvironment();
            $drupalfolderpath = trim($objsiteenv->getSiteDrupalPath($siteid));
            if ($drupalfolderpath == null){
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
                $subsite_db = $subsitename."_".$companyid."_".$environmentid."_".$siteid."_".$subsiteid;
            } else {
                $subsite_db = $subsitename;
            }

            $database_name = $subsite_db;
            $dbname = $subsite_db;
            $drup_dbname = $subsite_db;

            if ($using_own_db == 'TRUE') {
                $databaseid = $database->updateDatabaseName($databaseid, $database_name);
            }
            $subsiteid = $objsubsite->updateSubsiteDB($subsiteid, $dbname, $drup_dbname);

            // INSERT subsite environment information into epm_xref_subsite_environment
            $objsubsiteenv = new subsiteenv();

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

            $ListenerAPI = "http://".$serverip."/Listener.php?sitename=".trim($sitename)."&subsitename=".$subsitename."&drupfolderpath=".trim($drupalfolderpath)."&repogiturl=".$RepoGitUrl."&subsiteurl=".$subsiteurl."&InstallType=CreateSubsite&drup_dbname=".$drup_dbname."&db_ip=".$dbconnection_string."&dbuser=".$dbusername."&password=".$dbpassword."&username=".$gitUserName."&token=".$git_token."&repogiturl=".$RepoGitUrl."&listeneraction=createsubsite&description=".$Description;
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $ListenerAPI);
                curl_setopt($ch, CURLOPT_HEADER, true); 
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "type=cncl&reason=ticket.type.cancel.7");
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/xml", "Authorization: removed_dev_key:removed_api_key"));
                $result = curl_exec($ch);
                curl_close($ch);
	    }
	    catch(Exception $e){
		$e->getMessage();
	    }
            $filesystempath = $_SERVER['DOCUMENT_ROOT']."/".$sitename."_".$companyid."_".$environmentid."_".$siteid."/sites/".$subsiteurl;

            $update1 = $objsubsite->updateSubSite(trim($subsiteid), trim($subsitename), trim($filesystempath), 'Completed');
            if ($update1) {
            $this->app->response
                    ->setStatusCode(201, "Created")
                    ->setJsonContent(array('status' => 'OK', 'message' => 'Sub Site created succesfully', 'Subsiteid' => $update1));
            } else {
            $this->app->response
                    ->setStatusCode(403, "Error")
                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the Sub-site.'));
            }

            $update2 = $objsubsiteenv->updateSubSiteStatus($id, 'Completed');
            if ($update2 == null) {
                $this->app->response
                    ->setStatusCode(403, "Error")
                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the Sub-site environment.'));
            }
	}
	catch(Exception $e){
	    echo $e->getMessage();
	}   
        return $this->app->response;
    }

    public function delete($subsiteid) {

        if ($subsiteid == 'null') {
             $flag = "failed";
             $returnmsg = http_response_code(404);
             $msgdesc = "SubSite ID does not exist!";
             return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
        }

	try {
	    #Get subsite id
	    $objsubsite = new subsite();
	    $siteid = $objsubsite->getSiteid($subsiteid);
	    if($siteid == null) {
	        $this->app->response
			->setStatusCode(404, "Not Found")
			->setJsonContent(array('status' => 'ERROR', 'data' => 'Siteid does not exist!'));
	    }
            
	    # Get companyid 
	    $objsite = new sites();
            $companyid = $objsite->getCompanyId($siteid);
            if ($companyid == null){
                 $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            #Get subsiteurl
            $objsubsiteenv = new subsiteenv();
            $subsiteurl = $objsubsiteenv->getSubSiteUrl($subsiteid);
            if ($subsiteurl == null) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Sub Site Url does not exist!'));
            }

            # Get environmentid 
            $environmentid = $objsubsiteenv->getEnvironmentId($subsiteid);
            if ($environmentid == null){
                $this->app->response
                      ->setStatusCode(404, "Not Found")
                      ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            # Get serverip 
            $objserv = new server();
	    $backendcompanyid = $companyid;
            $tempserverip = $objserv->getServerIP($backendcompanyid, $environmentid);
            $serverip = trim($tempserverip);
            if ($serverip == null){
                $this->app->response
                      ->setStatusCode(404, "Not Found")
                      ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            #Get File Systempath
            $filesystempath = $objsubsite->getfilesystempath($subsiteid);
            if ($filesystempath == null){
                $this->app->response
                      ->setStatusCode(404, "Not Found")
                      ->setJsonContent(array('status' => 'ERROR', 'data' => 'Filesystempath does not exist!'));
            }

	    try {
                // Need to ensure that it calls the delete funciton in the Listener. *****
                $ListenerAPI = "http://".$serverip."/Listener.php?filesystempath=".trim($filesystempath)."&subsiteurl=".trim($subsiteurl)."&listeneraction=deletesubsite";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $ListenerAPI);
                curl_setopt($ch, CURLOPT_HEADER, true); 
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "type=cncl&reason=ticket.type.cancel.7");
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/xml", "Authorization: removed_dev_key:removed_api_key"));
                $result = curl_exec($ch);
                curl_close($ch);
	    }
	    catch(Exception $e)
	    {
		echo $e->getMessage();
	    }

            // Check result and then take action
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
            if ($update2 == null) {
                $this->app->response
                    ->setStatusCode(403, "Error")
                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the Sub-site environment.'));
            }
        }
	catch(Exception $e){
	    echo $e->getMessage();
	}
        return $this->app->response;
    }
}
