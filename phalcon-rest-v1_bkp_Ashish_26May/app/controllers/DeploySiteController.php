<?php
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

class DeploySiteController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }
	
    public function Deploy()
    {
		
        try {
            // Get input to the script 
            $apikey 				=  $this->app->request->get("apikey");
            $siteid 				=  $this->app->request->get("siteid");
            $source_environmentid 	=  $this->app->request->get("source_environmentid");
            $target_environmentid 	=  $this->app->request->get("target_environmentid");

            if (!isset($apikey) || empty($apikey)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'API Key not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($siteid) || empty($siteid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site ID is Empty!'));
                return $this->app->response;
            }

            if (!isset($source_environmentid) || empty($source_environmentid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Source Environement is Empty!'));
                return $this->app->response;
            }

            if (!isset($target_environmentid) || empty($target_environmentid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Target Environement is Empty!'));
                return $this->app->response;
            }

            #Select APIKEY
            $objapikey = new apikey();
            $apikeyid = $objapikey->getApikeyId($apikey);
            if (is_null($apikeyid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'API Key does not exist!'));
                return $this->app->response;
            }

            # Get source environment name
            $objenv = new environment();
            $source_environmentname = $objenv->getEnvironmentName($source_environmentid);
            if (is_null($source_environmentname)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Invalid Source Environment ID!'));
                return $this->app->response;
            }
			$source_environmentname = trim($source_environmentname);

            # Get target environment name
            $objenv = new environment();
            $target_environmentname = $objenv->getEnvironmentName($target_environmentid);
            if (is_null($target_environmentname)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Invalid Target Environment ID!'));
                return $this->app->response;
            }
			$target_environmentname = trim($target_environmentname);
			
            # Get site name
            $objsites = new sites();
            $sitename = $objsites->getSiteName($siteid);
            if (is_null($sitename)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Invalid Site ID!'));
                return $this->app->response;
            }
			$sitename = trim($sitename);

            # Get site details in source environment
            $objsiteenv = new siteenvironment();
            $sitexrefdata = $objsiteenv->getXrefSiteData($siteid,$source_environmentid);			
            if (is_null($sitexrefdata)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site does not exist into the epm_xref_site_environment table for source environment.'));
                return $this->app->response;
            }
			$source_sitedomainname 	= trim($sitexrefdata["sitedomainname"]);
			$site_private_git_id			= trim($sitexrefdata["gitid"]);
			$source_git_branch_id	= trim($sitexrefdata["git_branch_id"]);
			$source_serverid		= trim($sitexrefdata["serverid"]);

			$serverObj = new server();
			$source_serverip = $serverObj->getServerIPByID($source_serverid);
            if (is_null($source_serverip)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Source server does not exist.'));
                return $this->app->response;
            }
			$source_serverip 		= trim($source_serverip);
			
			$serverObj = new server();
			$source_server_baseurl = $serverObj->getServerBaseURLByServerID($source_serverid);
            if (is_null($source_server_baseurl)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Source server baseurl is blank in DB.'));
                return $this->app->response;
            }
			$source_server_baseurl = trim($source_server_baseurl);

            # Get site backend company id
            $objgitbranch = new gitbranch();
            $gitbranchdata = $objgitbranch->getGitBranchData($source_git_branch_id);
            if (is_null($gitbranchdata)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Invalid git branch!'));
                return $this->app->response;
			}
			$source_git_branch = $gitbranchdata["branch_name"];

            # Get site backend company id
            $objsites = new sites();
            $companyid = $objsites->getCompanyId($siteid);
            if (is_null($companyid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site is not associated with a company!'));
                return $this->app->response;
			}
			

            # Get site frontend company id
			$compobj = new company();
			$fcompanyid = $compobj->getFrontendCompanyId($companyid, 'active');
            if (is_null($fcompanyid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Frontend company id could not be found.'));
                return $this->app->response;
			}
			
			// Get private git data of the company
			/*$gitObj = new git();
			$privategitid = $gitObj->getPrivateGitDatabyCompanyId($companyid);
            if (is_null($privategitid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Unable to get the private git id of the company.'));
                return $this->app->response;
			}*/
			
			try {
				$client = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech/phalcon-rest-v1/']);
				if (!(($commits = $client->request('GET', 'github/getallcommits/'.$fcompanyid.'/'.$site_private_git_id.'/'.$source_git_branch)->getBody()) &&
				($getdata = json_decode($commits)) &&
				($shadata = $getdata->{'Data'}) &&
				($site_private_git_sha_key = $shadata[0]->sha))
				) {
					$this->response = array(
					   "Status" => "Failed",
					   "MsgCode" => "404",
					   "MsgDescription" => "Source environment branch does not exist or is inactive.",
					   "Data" => null,
					  ); 
					return $this->app->response;
				}
			}
			catch(Exception $e){
				$this->app->response
						->setStatusCode(404, "Not Found")
						->setJsonContent(array('status' => 'ERROR', 'data' => 'Source environment branch does not exist or is inactive.'));
				return $this->app->response;
			}
			
			# Get serverips
			$objserv = new server();
			$backendcompanyid = $companyid;
			$serverips = $objserv->getAllServerIP($backendcompanyid, $target_environmentid);
			if (is_null($serverips)) {
				$this->app->response
					->setStatusCode(404, "Not Found")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                return $this->app->response;
			}

            # Get serverid
            $serverid = $objserv->getServerID($backendcompanyid, $target_environmentid);
            if (is_null($serverid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist for target environment!'));
                return $this->app->response;
            }
			
            # Get server base url
            $serverbaseurl = $objserv->getServerBaseURL($backendcompanyid, $target_environmentid);
            if (is_null($serverbaseurl)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server base url is null!'));
                return $this->app->response;
            }
			$serverbaseurl = trim($serverbaseurl);	
			$serverbaseurl = (substr($serverbaseurl,-1) == "/") ? $serverbaseurl : $serverbaseurl."/";
			
			// Checking, If site already exists into the target environment
			$siteAlreadyDeployed = $this->IsSiteDeployed($siteid,$target_environmentid);
            if ($siteAlreadyDeployed){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site already deployed into the target environment.'));
                return $this->app->response;
			}
			
			// Delete record from epm_xref_site_environment table if exists
			$objsiteenv = new siteenvironment();
			$targetsitexrefdata = $objsiteenv->getXrefSiteData($siteid,$target_environmentid);
			if($targetsitexrefdata){
				$objsiteenv->deleteRecordBySiteIdAndEnvironmentId($siteid,$target_environmentid);
			}
			
			// Checking, site properly exists into the source environment
			$siteProperlyDeployed = $this->IsSiteDeployed($siteid,$source_environmentid);
            if (!$siteProperlyDeployed){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site is not properly deployed in source environment.'));
                return $this->app->response;
			}

			$sourceDB = $this->exportDatabase($siteid,$source_environmentid);
			$sourceDBDecode = json_decode($sourceDB);
			if($sourceDBDecode->Status != "Success"){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => $sourceDBDecode->MsgDesc));
                return $this->app->response;
			}
			$SourceDatabaseLink = $sourceDBDecode->Data;
			
			$target_sitedomainname = (strtolower(substr($source_sitedomainname,0,3)) == strtolower($source_environmentname)) ? strtolower($target_environmentname).substr($source_sitedomainname,3) : strtolower($target_environmentname)."_".$source_sitedomainname;

            # Check Site Url
            $objsiteenv = new siteenvironment();
            $checksiteurl = $objsiteenv->checkSiteUrl($target_sitedomainname);
            if (!is_null($checksiteurl)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Target Site Url Already Exist!'));
                return $this->app->response;
            }

			// Database entry in epm_database.
			$database_name = substr(md5($sitename."_".$target_environmentname."_".$siteid), 0, 12); 
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
			$res = $this->modelsManager->executeQuery("
				SELECT A1.id AS database_server_id, A2.serverid AS dbid, A1.db_external_hostname AS dbconnection_string, A1.db_port AS dbport, 
				A1.db_server_username AS dbusername, A1.db_server_password AS dbpassword  
				FROM databaseserver A1
				JOIN server A2 on A1.serverid = A2.serverid
				JOIN xrefServerCompany A3 ON A2.serverid = A3.serverid 
				WHERE A1.is_primary_db_server = 1 AND A1.status = 'active' AND A2.serverstatus = 'active' and 
				A3.backendcompanyid = :companyid: AND A2.environmentid = :environmentid:
				LIMIT 1 ", array(
				'companyid'=> $companyid,
				'environmentid'=> $source_environmentid)
			);
			
			if (	isset($res[0]) &&
					($dbconnection_string = &trim($res[0]->dbconnection_string)) &&
					($dbusername = &trim($res[0]->dbusername)) &&
					($dbpassword = &trim($res[0]->dbpassword)) &&
					($database_server_id = &trim($res[0]->database_server_id)) &&
					($dbport = &trim($res[0]->dbport))
				) {
				   error_log($companyid.','.$source_environmentid.','.$dbconnection_string.','.$dbusername.','.$dbpassword.','.$dbport);
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
			$database_id = $database->setDatabase($database_name, $dbusername, $dbpassword, $dbconnection_string, $dbport, $dbtype, $dbversion, $dbhostname, $target_environmentid, $isprimary, $status, $external_hostname, $primary_db_id, $database_server_id);
			
			$NewSite = trim($sitename)."_".trim($companyid)."_".trim($target_environmentid)."_".trim($siteid);

			#INSERT INTO epm_xref_site_environment
			$drupalfolderpath = $serverbaseurl.$NewSite;
			$drup_dbname = $database_name;
			$database_uses_global_password = 'TRUE';
			$username = ''; 
			$password = '';
			$site_status = '';
			$objsiteenv = new siteenvironment();

			$xrefid = $objsiteenv->getSiteEnvironmentId(trim($siteid), trim($target_environmentid));
			if ($xrefid) {				
				$updateXREFData = $objsiteenv->updateSite(trim($xrefid), trim($siteid), trim($target_environmentid), trim($drupalfolderpath), trim($serverid), trim($target_sitedomainname), trim($dbconnection_string), trim($drup_dbname), trim($username), trim($password), trim($source_git_branch_id), trim($database_uses_global_password), trim($database_id), trim($site_private_git_id), trim($site_status));
			}
			else {
				$xrefid = $objsiteenv->setSite(trim($siteid), trim($target_environmentid), trim($drupalfolderpath), trim($serverid), trim($target_sitedomainname), trim($drup_dbname), trim($dbconnection_string), trim($username), trim($password), trim($source_git_branch_id), trim($database_uses_global_password), trim($database_id), trim($site_private_git_id), trim($site_status));
			}

			//print_r(array('companyid' => $companyid, 'siteid' => $siteid, 'sitenameforgit' => $sitename, 'source_environmentid' => $source_environmentid, 'target_environmentid' => $target_environmentid, 'git_id' => $site_private_git_id, 'source_git_branch_id' => $source_git_branch_id));
			//die;
			
			try {
				$client = new GuzzleHttp\Client(['base_uri' => 'http://api.evolverinc.tech']);
				$gitresponse = $client->request('GET','/phalcon-rest-v1/integrategit/deploy', ['query' => ['companyid' => $companyid, 'siteid' => $siteid, 'sitenameforgit' => $sitename, 'source_environmentid' => $source_environmentid, 'target_environmentid' => $target_environmentid, 'site_private_git_id' => $site_private_git_id, 'source_git_branch_id' => $source_git_branch_id, 'site_private_git_sha_key' => $site_private_git_sha_key]])->getBody()->getContents();
			}
			catch(Exception $e){
				$this->app->response
						->setStatusCode(404, "Not Found")
						->setJsonContent(array('status' => 'ERROR', 'data' => 'Unable to create the git branch. Error: '.$e->getMessage()));
				return $this->app->response;
			}

			$gitdecode = json_decode($gitresponse);			
			if ($gitdecode->Status != 'Success') {					
				$this->app->response
						->setStatusCode(404, "Not Found")
						->setJsonContent(array('status' => 'ERROR', 'data' => $gitdecode->MsgDescription));
				return $this->app->response;				
			}
			
			$data = $gitdecode->Data;			
			$giturl = $data->giturl;
			$gitbranch = $data->gitbranch;
			
			 
			//print_r($serverips); 			 
			//echo  'http://IP/API/DeploySite.php?apikey='.$apikey.'&sitename='.$sitename.'&companyid='.$companyid.'&giturl='.$giturl.'&gitbranch='.$gitbranch.'&siteurl='.$target_sitedomainname.'&environment='.$target_environmentname.'&database_url='.$SourceDatabaseLink.'&drup_dbname='.$drup_dbname.'&db_ip='.$dbconnection_string.'&dbuser='.$dbusername.'&password='.$dbpassword.'&NewSite='.$NewSite.'&source_serverip='.$source_serverip.'&source_server_baseurl='.$source_server_baseurl;
			//die;
			 

			foreach($serverips as $serverip)
			{
				try {
					$clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
					$getListenerRes = $clientlistener->request('GET',
						'/API/DeploySite.php',
						['query' => ['apikey' => $apikey, 'sitename' => $sitename, 'companyid' => $companyid, 'giturl' => $giturl, 'gitbranch' => $gitbranch, 'siteurl' => $target_sitedomainname, 'environment' => $target_environmentname, 'database_url' => $SourceDatabaseLink, 'drup_dbname' => $drup_dbname, 'db_ip' => $dbconnection_string, 'dbuser' => $dbusername, 'password' => $dbpassword, 'NewSite' => $NewSite, 'source_serverip' => $source_serverip, 'source_server_baseurl' => $source_server_baseurl]])->getBody()->getContents();
				}
				catch(Exception $e){
					$this->app->response
							->setStatusCode(404, "Not Found")
							->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
					return $this->app->response;
				}
			}
			
			$getdecode = json_decode($getListenerRes);
			$getencode = json_encode($getdecode);
			$getdecodedata = json_decode($getencode);

			if (($getdecodedata->Status != 'Success')) {				  
				$this->app->response
					->setStatusCode(403, "Error")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Deployment Failed. Error: '.$getdecodedata->msgdescription));
				return $this->app->response;
			}
			
			$getlistenerdata = $getdecodedata->{'Response'};
			$drupuser = $getlistenerdata->dbuser;
			$druppassword = $getlistenerdata->dbpassword;
			//$allsubsiteInfo = $getlistenerdata->subsiteinfo;

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
			$dbsecondryuser = $client->request('POST','/phalcon-rest-v1/site/addusertodb/',['query' => ['apikey'=> $apikey, 'environmentid' => $target_environmentid, 'siteid' => $siteid]])->getBody()->getContents();
			$getdbdecode = json_decode($dbsecondryuser);
			$getdbencode = json_encode($getdbdecode);
			$getdbdecodedata = json_decode($getdbencode);
			if (($getdbdecodedata->status == 'Failed')) {
				error_log("Secondary user for drupal db is not created!");
			}

			
			
			$objsite = new sites();
			$update1 = $objsite->updateSites($companyid, $sitename, 'Completed', $siteid);
			$sitedata = $objsite->getSiteData($siteid);
			$xrefsitedata = $objsiteenv->getXrefSiteData($siteid,$target_environmentid);
			$compsitedata = array_merge($sitedata,$xrefsitedata);
			if (!$update1) {				   
				$this->app->response
					->setStatusCode(403, "Error")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site.'));
				return $this->app->response;
			}

			$update2 = $objsiteenv->updateSiteStatus($xrefid, 'Completed');
			if (is_null($update2)) {
				$this->app->response
					 ->setStatusCode(403, "Error")
					 ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site environment.'));
				return $this->app->response;
			}
			
			$curlcommand = "curl -X POST -H \"Cache-Control: no-cache\" -F \"apikey=Tgi/s0TcTqUXxJy/Jiu9xjyaqozf0TlE/PXOzf0r2SE=\" \"http://52.9.187.253:8080/d841c50/\"";
			exec($curlcommand,$result);

			$this->app->response
				->setStatusCode(201, "Created")
				->setJsonContent(array('status' => 'Success', 'message' => 'Site Deployed Successfully.', 'siteid' => $compsitedata, 'synchronization' => $result ));			
        }
        catch(Exception $e) {
            echo $e->getMessage();
        } 
        return $this->app->response;
    }
	
	public function IsSiteDeployed($siteid,$environmentid){
		
		$objsiteenv = new siteenvironment();
		$sitexrefdata = $objsiteenv->getXrefSiteData($siteid,$environmentid);
		if(!$sitexrefdata){
			return false;
		}
		
		if($sitexrefdata["database_id"] == "" || $sitexrefdata["database_id"] == 0 || $sitexrefdata["serverid"] == "" || $sitexrefdata["serverid"] == 0 || $sitexrefdata["drupalfolderpath"] == ""){
			return false;
		}
		
        $databaseObj = new database();
		$sitedatabase = $databaseObj->getDatabase($sitexrefdata["database_id"]);
		if(!$sitedatabase){
			return false;
		}
		
        $serverObj = new server();
		$siteserverip = $serverObj->getServerIPByID($sitexrefdata["serverid"]);

		if(!$siteserverip){
			return false;
		}
		

		try {
			$clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$siteserverip]);
			$getListenerRes = $clientlistener->request('GET','/API/IsSiteFolderExist.php',['query' => ['drupalfolderpath' => $sitexrefdata["drupalfolderpath"]]])->getBody()->getContents();
			if(!$getListenerRes){
				return false;
			}
		}
		catch(Exception $e){
			return false;
		}
		return 1;
	}
	
	function exportDatabase($siteid,$source_environmentid){

		// Fetching Source Server Details
		$objsiteenv 		= new siteenvironment();
		$sitexrefdata 		= $objsiteenv->getXrefSiteData($siteid,$source_environmentid);
		$drupalfolderpath	= trim($sitexrefdata["drupalfolderpath"]);		
		$serverid			= trim($sitexrefdata["serverid"]);		
		
		$objserver 			= new server();
		$serverip			= $objserver->getServerIPByID($serverid);
		$serverip			= trim($serverip);

		$serverPath = $serverip."/API/ExportDBToSQL.php?drupalfolderpath=".$drupalfolderpath;
			
		try {
			$ch = curl_init($serverPath);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$result = curl_exec($ch);			
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
				return json_encode(array('Status' => 'ERROR', 'MsgDesc' => 'Could not connect with source server'));
			}			
			curl_close($ch);			
		}
		catch(Exception $e){
			return json_encode(array('Status' => 'ERROR', 'MsgDesc' => 'Could not connect with source server. Reason: '.$e->getMessage()));
		}
		
		if($result != 1){
			return json_encode(array('Status' => 'ERROR', 'MsgDesc' => 'Could not export database from source server. Reason: '.$result));
		}		
		return json_encode(array('Status' => 'Success', 'Data' => $serverip."/API/".md5($drupalfolderpath).".sql"));
	}
}