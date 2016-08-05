<?php
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
use Aws\S3\S3Client;

class SiteController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }

	protected function postgresDBConnection(){
		
		$host        = "host=54.174.204.196";
		$port        = "port=5432";
		$dbname      = "dbname=evolpaasmgr";
		$credentials = "user=postgres password=lbit123";
		return pg_connect( "$host $port $dbname $credentials"  );
	}
	
	public function ListSitesAndSubSites() {

        try {
			// Receiving get parameters 
			$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$companyid 		= (isset($_GET["companyid"])) 		? $_GET["companyid"] 		: "";
			$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";

			if($apikey != "api-dev-123456" || $companyid == ""){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key and Company id are mandatory fields'));
			}

			// Making DB connection
			// Return false if db connection failed
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			// Fetching backend company id
			$companySQL =<<<EOF
			SELECT * from epm_company WHERE frontendcompanyid = '$companyid';
EOF;
			$companyResult 	= pg_query($db, $companySQL);
			$companyData 	= pg_fetch_assoc($companyResult);
			$backend_companyid = $companyData["backendcompanyid"];

			if($backend_companyid == ""){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => "This company id doesn't exist in the database."));
			}
			
			//Fetching server ip based on given input parameters
			if($siteid != "" && $environmentid != ""){
				$serverSQL =<<<EOF
				SELECT s.externalip,s.serverid,x.environmentid from epm_server AS s INNER JOIN epm_xref_site_environment AS x ON s.serverid = x.serverid WHERE s.backendcompanyid = $backend_companyid AND s.isprimary=TRUE AND x.siteid=$siteid AND x.environmentid=$environmentid AND lower(s.serverstatus) = 'active';
EOF;
			}
			else if($environmentid != "") {
				$serverSQL =<<<EOF
				SELECT externalip,serverid,environmentid from epm_server WHERE backendcompanyid = $backend_companyid AND environmentid = $environmentid AND serverfunction = 'ApacheWeb' AND lower(serverstatus) = 'active';
EOF;
			}
			else {
				$serverSQL =<<<EOF
				SELECT externalip,serverid,environmentid from epm_server WHERE backendcompanyid = $backend_companyid AND serverfunction = 'ApacheWeb' AND lower(serverstatus) = 'active';
EOF;
			}
			
			$serverResult = pg_query($db, $serverSQL);
			$serverData = pg_fetch_all($serverResult);
			
			if(!$serverData || pg_num_rows($serverResult) == 0){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => "No server exist for the given inputs."));
			}
			
			// Fetching site details using server_ip
			$sitesArray = array();			
			
			foreach($serverData as $val){
				
				$serverPath = "http://".trim($val["externalip"])."/API/SiteListing.php?serverid=".$val["serverid"]."&companyid=".$backend_companyid."&environmentid=".$val["environmentid"]."&siteid=".$siteid; // Main server primary path
				
				$ch = curl_init($serverPath);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
				$sites = curl_exec($ch);
				
				if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 404 && $sites) {
					$sitesArray = array_merge($sitesArray,(array) json_decode($sites));
				}
				curl_close($ch);
			}
			
			if(sizeof($sitesArray)>0){
				
				$siteIds = array();				
				$object = true;
				foreach($sitesArray as $key=>$sites){
					$siteid = $sites->siteID;
					if(!isset($siteIds[$siteid])){
						$siteIds[$siteid] = $key;
					}
					else {
						$subsiteIds = array();

						$sitesArray[$siteIds[$siteid]]->subSite = array_merge($sitesArray[$siteIds[$siteid]]->subSite,$sitesArray[$key]->subSite);
						foreach($sitesArray[$siteIds[$siteid]]->subSite as $key2=>$subsites){
							$subsiteid = $subsites->subSiteID;
							
							if(!isset($subsiteIds[$subsiteid])){
								$subsiteIds[$subsiteid] = $key2;
							}
							else {
								$sitesArray[$siteIds[$siteid]]->subSite[$subsiteIds[$subsiteid]] = (object) array_merge((array) $sitesArray[$siteIds[$siteid]]->subSite[$key2],(array) $sitesArray[$siteIds[$siteid]]->subSite[$subsiteIds[$subsiteid]]);
								unset($sitesArray[$siteIds[$siteid]]->subSite[$key2]);
							}
						}

						/*
						if($object){
							$sitesArray[$siteIds[$siteid]]->subSite = array_merge($sitesArray[$siteIds[$siteid]]->subSite,$sitesArray[$key]->subSite);
							foreach($sitesArray[$siteIds[$siteid]]->subSite as $key2=>$subsites){
								$subsiteid = $subsites->subSiteID;
								
								if(!isset($subsiteIds[$subsiteid])){
									$subsiteIds[$subsiteid] = $key2;
								}
								else {
									$sitesArray[$siteIds[$siteid]]->subSite[$subsiteIds[$subsiteid]] = (object) array_merge((array) $sitesArray[$siteIds[$siteid]]->subSite[$key2],(array) $sitesArray[$siteIds[$siteid]]->subSite[$subsiteIds[$subsiteid]]);
									unset($sitesArray[$siteIds[$siteid]]->subSite[$key2]);
								}
							}
						}
						else {							
							$sitesArray[$siteIds[$siteid]]["subSite"] = array_merge((array)$sitesArray[$siteIds[$siteid]]["subSite"],(array)$sitesArray[$key]->subSite);
							foreach($sitesArray[$siteIds[$siteid]]["subSite"] as $key2=>$subsites){
								$subsiteid = $subsites->subSiteID;
								
								if(!isset($subsiteIds[$subsiteid])){
									$subsiteIds[$subsiteid] = $key2;
								}
								else {
									$sitesArray[$siteIds[$siteid]]["subSite"][$subsiteIds[$subsiteid]] = (object) array_merge((array) $sitesArray[$siteIds[$siteid]]["subSite"][$key2],(array) $sitesArray[$siteIds[$siteid]]["subSite"][$subsiteIds[$subsiteid]]);
									unset($sitesArray[$siteIds[$siteid]]["subSite"][$key2]);
								}
							}
						}
						*/
						$sitesArray[$siteIds[$siteid]] = array_merge((array) $sitesArray[$key],(array) $sitesArray[$siteIds[$siteid]]);
						unset($sitesArray[$key]);
						$object = false;
					}
				}

				foreach($sitesArray as $key=>$sites){
					
					if(is_array($sitesArray[$key])){
						if(!isset($sites["Dev"])) @$sitesArray[$key]["Dev"] = 0;
						if(!isset($sites["Test"])) @$sitesArray[$key]["Test"] = 0;
						if(!isset($sites["Live"])) @$sitesArray[$key]["Live"] = 0;
						
						if(sizeof($sites["subSite"])>0){
							foreach($sites["subSite"] as $key2=>$subsites){
								if(!isset($subsites->Dev)) @$sitesArray[$key]["subSite"][$key2]->Dev = 0;
								if(!isset($subsites->Test)) @$sitesArray[$key]["subSite"][$key2]->Test = 0;
								if(!isset($subsites->Live)) @$sitesArray[$key]["subSite"][$key2]->Live = 0;
							}
						}
					}
					if(is_object($sitesArray[$key])){
						if(!isset($sites->Dev)) {
							@$sitesArray[$key]->Dev = 0;
							@$sitesArray[$key]->DevAdminSiteURL = "";
							@$sitesArray[$key]->DevSiteURL = "";
							@$sitesArray[$key]->DevSiteStatus = "Undeployed";
							
						}
						if(!isset($sites->Test)) {
							@$sitesArray[$key]->Test = 0;
							@$sitesArray[$key]->TestAdminSiteURL = "";
							@$sitesArray[$key]->TestSiteURL = "";
							@$sitesArray[$key]->TestSiteStatus = "Undeployed";
						}
						if(!isset($sites->Live)) {
							@$sitesArray[$key]->Live = 0;	
							@$sitesArray[$key]->LiveAdminSiteURL = "";
							@$sitesArray[$key]->LiveSiteURL = "";
							@$sitesArray[$key]->LiveSiteStatus = "Undeployed";
						}
						
						if(sizeof($sites->subSite)>0){
							foreach($sites->subSite as $key2=>$subsites){
								if(!isset($subsites->Dev)) {
									@$sitesArray[$key]->subSite[$key2]->Dev = 0;
									@$sitesArray[$key]->subSite[$key2]->DevAdminSubSiteURL = "";
									@$sitesArray[$key]->subSite[$key2]->DevSubSiteURL = "";
									@$sitesArray[$key]->subSite[$key2]->DevSubSiteStatus = "Undeployed";
								}
								if(!isset($subsites->Test)) {
									@$sitesArray[$key]->subSite[$key2]->Test = 0;
									@$sitesArray[$key]->subSite[$key2]->TestAdminSubSiteURL = "";
									@$sitesArray[$key]->subSite[$key2]->TestSubSiteURL = "";
									@$sitesArray[$key]->subSite[$key2]->TestSubSiteStatus = "Undeployed";
								}
								if(!isset($subsites->Live)) {
									@$sitesArray[$key]->subSite[$key2]->Live = 0;
									@$sitesArray[$key]->subSite[$key2]->LiveAdminSubSiteURL = "";
									@$sitesArray[$key]->subSite[$key2]->LiveSubSiteURL = "";
									@$sitesArray[$key]->subSite[$key2]->LiveSubSiteStatus = "Undeployed";
								}
							}
						}
					}
				}
			}
			$this->app->response
				->setStatusCode(200, "Success")
				->setJsonContent($sitesArray);
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}

	public function GetIPAddressOfSites(){

        try {
			// Receiving get parameters 
			$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid 		= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";

			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized") 
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environmentid and Site/Subsite Id is mandatory.'));
			}

			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			if($siteid == ""){
				$SiteIDSQL =<<<EOF
				SELECT * from epm_sub_site WHERE subsiteid = $subsiteid AND environmentid=$environmentid;
EOF;
				$SiteIDResult 	= pg_query($db, $SiteIDSQL);
				$SiteIDData 	= pg_fetch_assoc($SiteIDResult);
				$siteid 		= $SiteIDData["siteid"];
			}
			$IPSQL =<<<EOF
			SELECT s.externalip FROM epm_server AS s INNER JOIN epm_xref_site_environment AS x ON s.serverid = x.serverid WHERE x.siteid = $siteid AND x.environmentid = $environmentid;
EOF;
			$IPResult 	= pg_query($db, $IPSQL);
			$IPData 	= pg_fetch_assoc($IPResult);
			$this->app->response
				->setStatusCode(200, "Success")
				->setJsonContent(array("serverip"=>trim($IPData["externalip"])));
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}
	
	public function GetConnectionInfo(){

        try {
			// Receiving get parameters 
			$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid 		= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";
			$environmentid	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"] 	: "";

			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized") 
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environmentid and Site/Subsite Id is mandatory.'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			if($siteid != ""){
				$serverIPSQL =<<<EOF
					SELECT 
						s.externalip
					FROM 
						epm_server AS s
					INNER JOIN 
						epm_xref_site_environment AS x
					ON 
						s.serverid = x.serverid
					WHERE 
						x.siteid = $siteid AND
						x.environmentid = $environmentid
EOF;
			}
			else {
				$serverIPSQL =<<<EOF
					SELECT 
						s.externalip
					FROM 
						epm_server AS s
					INNER JOIN 
						epm_xref_subsite_environment AS x
					ON 
						s.serverid = x.serverid
					WHERE 
						x.subsiteid = $subsiteid AND
						x.environmentid = $environmentid AND
						d.isprimary = TRUE
EOF;
			}
			
			$serverIPResult 	= pg_query($db, $serverIPSQL);

			$return = array();

			if(pg_num_rows($serverIPResult)>0){
				
				$serverIPData 	= pg_fetch_assoc($serverIPResult);
				$serverPath 	= "http://".trim($serverIPData["externalip"])."/API/GetConnectionInfo.php?environmentid=".$environmentid."&siteid=".$siteid;
				
				$ch = curl_init($serverPath);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$return = curl_exec($ch);
				if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 404) {
					if($return == ""){
						$this->app->response
							->setStatusCode(404, "Not Found")
							->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Unexpected Error! Could not get the site connection info.'));
					}
					else if(!is_array($return)){
						$this->app->response
							->setStatusCode(404, "Not Found")
							->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => $return));
					}
					else {
						$this->app->response
							->setStatusCode(200, "Success")
							->setJsonContent($return);
					}
				}
				else {
					$this->app->response
						->setStatusCode(404, "Not Found")
						->setJsonContent(array('status' => 'ERROR', 'data' => 'Could not connect to server to get the site connection info.'));
				}
				curl_close($ch);
			}
			else {
				$this->app->response
					->setStatusCode(404, "Not Found")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Server not found.'));
			}
		}
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        }
        return $this->app->response;
	}

	public function ImportDBBySQLFileURL() {

        try {
			// Receiving get parameters 
			$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$database_url	= (isset($_GET["database_url"]))	? $_GET["database_url"] 	: "";

			if($apikey != "api-dev-123456" || $environmentid == "" || $siteid == "" || $database_url == ""){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key and Environmentid, Siteid, DatabaseURL are mandatory fields'));
			}

			// Making DB connection
			// Return false if db connection failed
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}

			// Fetching backend company id
			$serverSQL =<<<EOF
			SELECT s.externalip from epm_server AS s INNER JOIN epm_xref_site_environment AS x ON s.serverid = x.serverid WHERE s.isprimary=TRUE AND x.siteid=$siteid AND x.environmentid=$environmentid AND lower(s.serverstatus) = 'active';
EOF;
			$serverResult 		= pg_query($db, $serverSQL);
			$serverData 		= pg_fetch_assoc($serverResult);
			
			if(!$serverData){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'No server exist for the given site and environment.'));
			}			

			$serverPath = "http://".trim($serverData["externalip"])."/API/ImportDB.php?environmentid=".$environmentid."&siteid=".$siteid."&database_url=".$database_url; // Main server primary path
			
			$ch = curl_init($serverPath);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$dbImported = curl_exec($ch);
			
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Unexpected error on target environment server'));
			}
			curl_close($ch);
			if($dbImported == 1){
				$this->app->response
					->setStatusCode(200, "Success")
					->setJsonContent(array('status' => 'Success', 'MsgDesc' => 'Import Done Successfully.'));
			}
			else {
				$this->app->response
					->setStatusCode(200, "Failed")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => $dbImported));
			}
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}

	public function ImportFiles() {

        try {
			// Receiving get parameters 
			$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$archivefileurl	= (isset($_GET["archivefileurl"]))	? $_GET["archivefileurl"] 	: "";

			if($apikey != "api-dev-123456" || $environmentid == "" || $siteid == "" || $archivefileurl == ""){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environmentid, Siteid, DatabaseURL are mandatory fields'));
			}

			// Making DB connection
			// Return false if db connection failed
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}

			// Fetching backend company id
			$serverSQL =<<<EOF
			SELECT s.externalip, s.serverid, x.drupalfolderpath  from epm_server AS s INNER JOIN epm_xref_site_environment AS x ON s.serverid = x.serverid WHERE s.isprimary=TRUE AND x.siteid=$siteid AND x.environmentid=$environmentid AND lower(s.serverstatus) = 'active';
EOF;
			$serverResult 		= pg_query($db, $serverSQL);
			$serverData 		= pg_fetch_assoc($serverResult);
			
			if(!$serverData){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'No server exist for the given site and environment.'));
			}			

			$sites_directory = trim($serverData["drupalfolderpath"]);
			$sites_directory = (substr($sites_directory,-1) == "1")	? $sites_directory : $sites_directory."/";
			
			$serverPath = "http://".trim($serverData["externalip"])."/API/ImportFiles.php?target_environmentid=".$environmentid."&siteid=".$siteid."&targetServerID=".trim($serverData["serverid"])."&targetServerIP=".trim($serverData["externalip"])."&sites_directory=".$sites_directory."&siteid=".$siteid."&SourceFileZipPath=".$archivefileurl; // Main server primary path
			
			$ch = curl_init($serverPath);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$dbImported = curl_exec($ch);
			
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Unexpected error on target environment server'));
			}
			curl_close($ch);
			if($dbImported == 1){
				$this->app->response
					->setStatusCode(200, "Success")
					->setJsonContent(array('status' => 'Success', 'MsgDesc' => 'Import Done Successfully.'));
			}
			else {
				$this->app->response
					->setStatusCode(200, "Failed")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => $dbImported));
			}
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}
	
	public function CloneDB(){
		
        try {
			// Receiving get parameters 
			$apikey 				= (isset($_GET["apikey"])) 					? $_GET["apikey"] 				: "";
			$source_environmentid 	= (isset($_GET["source_environmentid"])) 	? $_GET["source_environmentid"]	: "";
			$target_environmentid 	= (isset($_GET["target_environmentid"])) 	? $_GET["target_environmentid"]	: "";
			$siteid 				= (isset($_GET["siteid"])) 					? $_GET["siteid"] 				: "";
			$subsiteid 				= (isset($_GET["subsiteid"])) 				? $_GET["subsiteid"] 			: "";
			
			if($apikey != "api-dev-123456" || $source_environmentid == "" || $target_environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Source Environmentid, Target Environmentid and Site/Subsite id are mandatory fields'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			if($siteid == ""){
				$SiteIDSQL =<<<EOF
				SELECT * from epm_sub_site WHERE subsiteid = $subsiteid;
EOF;
				$SiteIDResult 	= pg_query($db, $SiteIDSQL);
				if(pg_num_rows($SiteIDResult) == 0){
					return $this->app->response
						->setStatusCode(401, "Unathorized")
						->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Invalid subsite id.'));
				}
				$SiteIDData 	= pg_fetch_assoc($SiteIDResult);
				$siteid 		= $SiteIDData["siteid"];
			}
			
			$sourceServerSQL =<<<EOF
			SELECT epm_server.externalip, epm_server.serverid, epm_xref_site_environment.drupalfolderpath FROM epm_server JOIN epm_xref_site_environment ON epm_xref_site_environment.serverid = epm_server.serverid WHERE epm_xref_site_environment.siteid=$siteid AND epm_xref_site_environment.environmentid=$source_environmentid;
EOF;

			$sourceServerResult = pg_query($db, $sourceServerSQL);
			if(pg_num_rows($sourceServerResult) == 0){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Server does not exist for the given site in source environment.'));
			}

			$sourceServerData 	= pg_fetch_assoc($sourceServerResult);			
			$sourceServerIP 	= trim($sourceServerData["externalip"]);
			$sourceServerID 	= trim($sourceServerData["serverid"]);
			$drupalfolderpath 	= trim($sourceServerData["drupalfolderpath"]);
			$sites_directory 	= (substr($drupalfolderpath, -1) == "/") ? $drupalfolderpath : $drupalfolderpath."/";

			$serverPath = "http://".$sourceServerIP."/API/ExportDB.php?target_environmentid=".$target_environmentid."&source_environmentid=".$source_environmentid."&siteid=".$siteid."&subsiteid=".$subsiteid."&sites_directory=".$sites_directory."&sourceServerID=".$sourceServerID."&sourceServerIP=".$sourceServerIP;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $serverPath); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$result = curl_exec($ch);
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 404) {
				if($result == 1){
					return $this->app->response
						->setStatusCode(200, "Success")
						->setJsonContent(array('status' => 'Success', 'MsgDesc' => 'DB Cloned successfully.'));
				}
				else {
					return $this->app->response
						->setStatusCode(401, "Failed")
						->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => $result));
				}
			}
			else {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Unexpected error on source/target environment server'));
			}
			curl_close($ch);
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}
	
	public function CloneFiles() {

        try {
			// Receiving get parameters 
			$apikey 				= (isset($_GET["apikey"])) 					? $_GET["apikey"] 				: "";
			$source_environmentid 	= (isset($_GET["source_environmentid"])) 	? $_GET["source_environmentid"]	: "";
			$target_environmentid 	= (isset($_GET["target_environmentid"])) 	? $_GET["target_environmentid"]	: "";
			$siteid 				= (isset($_GET["siteid"])) 					? $_GET["siteid"] 				: "";
			$subsiteid 				= (isset($_GET["subsiteid"])) 				? $_GET["subsiteid"] 			: "";
			
			if($apikey != "api-dev-123456" || $source_environmentid == "" || $target_environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Source Environmentid, Target Environmentid and Site/Subsite id are mandatory fields'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			if($siteid == ""){
				$SiteIDSQL =<<<EOF
				SELECT * from epm_sub_site WHERE subsiteid = $subsiteid;
EOF;
				$SiteIDResult 	= pg_query($db, $SiteIDSQL);
				if(pg_num_rows($SiteIDResult) == 0){
					return $this->app->response
						->setStatusCode(401, "Unathorized")
						->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Invalid subsite id.'));
				}
				$SiteIDData 	= pg_fetch_assoc($SiteIDResult);
				$siteid 		= $SiteIDData["siteid"];
			}
			
			$sourceServerSQL =<<<EOF
			SELECT epm_server.externalip, epm_server.serverid, epm_xref_site_environment.drupalfolderpath FROM epm_server JOIN epm_xref_site_environment ON epm_xref_site_environment.serverid = epm_server.serverid WHERE epm_xref_site_environment.siteid=$siteid AND epm_xref_site_environment.environmentid=$source_environmentid;
EOF;

			$sourceServerResult = pg_query($db, $sourceServerSQL);
			if(pg_num_rows($sourceServerResult) == 0){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Server does not exist for the given site in source environment.'));
			}

			$sourceServerData 	= pg_fetch_assoc($sourceServerResult);			
			$sourceServerIP 	= trim($sourceServerData["externalip"]);
			$sourceServerID 	= trim($sourceServerData["serverid"]);
			$drupalfolderpath 	= trim($sourceServerData["drupalfolderpath"]);
			$sites_directory 	= (substr($drupalfolderpath, -1) == "/") ? $drupalfolderpath : $drupalfolderpath."/";

			$serverPath = "http://".$sourceServerIP."/API/ExportFiles.php?target_environmentid=".$target_environmentid."&source_environmentid=".$source_environmentid."&siteid=".$siteid."&sites_directory=".$sites_directory."&sourceServerID=".$sourceServerID."&sourceServerIP=".$sourceServerIP;
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $serverPath); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$result = curl_exec($ch);
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 404) {
				
				if($result == 1){
					$this->app->response
						->setStatusCode(200, "Success")
						->setJsonContent(array('status' => 'Success', 'MsgDesc' => 'Files has been cloned successfully'));
				}
				else {
					$this->app->response
						->setStatusCode(401, "Failure")
						->setJsonContent(array('status' => 'Error', 'MsgDesc' => $result));
				}
			}
			else {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Could not connect to source server'));
			}
			curl_close($ch);
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}

	public function AddSecondaryUserToDB(){
		
        try {
			$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid		= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";
			
			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key and Company id are mandatory fields'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}

			if($siteid != ""){
				$serverSQL =<<<EOF
				SELECT 
					s.externalip,s.serverid 
				FROM 
					epm_server AS s 
				INNER JOIN 
					epm_xref_site_environment AS x 
				ON 
					s.serverid = x.serverid 
				WHERE 
					s.isprimary=TRUE AND 
					x.siteid=$siteid AND 
					x.environmentid=$environmentid AND 
					lower(s.serverstatus) = 'active';
EOF;
			} 
			else {
				$serverSQL =<<<EOF
				SELECT 
					s.externalip,s.serverid 
				FROM 
					epm_server AS s 
				INNER JOIN 
					epm_xref_subsite_environment AS x 
				ON 
					s.serverid = x.serverid 
				WHERE 
					s.isprimary=TRUE AND 
					x.subsiteid=$subsiteid AND 
					x.environmentid=$environmentid AND 
					lower(s.serverstatus) = 'active';
EOF;
			}

			$serverResult = pg_query($db, $serverSQL);
			
			$serverData = pg_fetch_assoc($serverResult);

			if(pg_num_rows($serverResult) == 0 || $serverData["externalip"] == ""){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Server does not exist for the given site and environment'));
			}
			
			$serverPath = "http://".trim($serverData["externalip"])."/API/AddSecondaryUserToDB.php?serverid=".$serverData["serverid"]."&environmentid=".$environmentid."&siteid=".$siteid."&subsiteid=".$subsiteid;
			
			$ch = curl_init($serverPath);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$result = curl_exec($ch);
			
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Unexpected error on target environment server'));
			}			
			$resultArr = json_decode($result);

			if(is_object($resultArr)){
				$this->app->response
					->setStatusCode(200, "Success")
					->setJsonContent(array('status' => 'Success', 'MsgDesc' => $resultArr));
			}
			else {
				$this->app->response
					->setStatusCode(401, "Failed")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => $result));
			}
			
			curl_close($ch);
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}

	public function isJson($string) {
		 json_decode($string);
		 return (json_last_error() == JSON_ERROR_NONE);
	}
	
	public function GetSingleSite(){
		
		$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
		$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
		$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";

		if($apikey != "api-dev-123456" || $siteid == "" || $environmentid == ""){
			http_response_code(401);
			echo json_encode(array());
		}
	
		$db = $this->postgresDBConnection();
		if (!$db) {
			$returnmsg = http_response_code(401);
			echo json_encode(array("Status"=>"failed","MsgCode"=>$returnmsg ,"msgdescription"=>"Could not connect with Database." ));
		}
		
		$siteSQL =<<<EOF
			SELECT 
				* 
			FROM 
				epm_sites AS s 
			INNER JOIN 
				epm_xref_site_environment AS x 
			ON 
				s.siteid = x.siteid 
			WHERE 
				s.siteid=$siteid AND
				x.environmentid=$environmentid
EOF;

		$siteResult = pg_query($db, $siteSQL);
		$siteData	= pg_fetch_assoc($siteResult);

		if(pg_num_rows($siteResult)>0){
			$siteData["subsites"] = array();
			$subsites = $this->GetSubSitesBySiteID($siteid,$environmentid);
			if(isset($subsites) && is_array($subsites)){
				$siteData["subsites"] = $subsites;
			}
			echo json_encode($siteData);
		}
		else {
			http_response_code(401);
			$MsgCode = http_response_code(401);
			echo json_encode(array("Status"=>"failed","MsgCode"=>$MsgCode ,"msgdescription"=>"Site does not exist for the given environment" ));
		}
	}

	protected function GetSubSitesBySiteID($siteid,$environmentid){

		$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
		$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";

		if($siteid == "" || $environmentid == ""){
			http_response_code(401);
			echo json_encode(array());
		}
	
		$db = $this->postgresDBConnection();
		if (!$db) {
			$returnmsg = http_response_code(401);
			echo json_encode(array("Status"=>"failed","MsgCode"=>$returnmsg ,"msgdescription"=>"Could not connect with Database." ));
		}
		
		$subSiteSQL =<<<EOF
			SELECT 
				* 
			FROM 
				epm_sub_site AS s 
			INNER JOIN 
				epm_xref_subsite_environment AS x 
			ON 
				s.subsiteid = x.subsite_id 
			WHERE 
				s.siteid=$siteid AND
				s.environmentid=$environmentid
EOF;

		$subSiteResult = pg_query($db, $subSiteSQL);
		$subSiteData	= pg_fetch_all($subSiteResult);

		if(pg_num_rows($subSiteResult)>0){
			return $subSiteData;
		}
		else {
			return array();
		}
	}
	
	public function RunUpdateDotPHPFile() {
		
        try {
			// Receiving get parameters 
			$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid 		= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";

			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized") 
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environment Id and Site/Subsite Id are mandatory.'));
			}

			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Could not connect to database.'));
			}
			
			if($siteid == ""){
				$SiteIDSQL =<<<EOF
				SELECT * from epm_sub_site WHERE subsiteid = $subsiteid AND environmentid=$environmentid;
EOF;
				$SiteIDResult 	= pg_query($db, $SiteIDSQL);
				if(pg_num_rows($SiteIDResult) == 0){
					return $this->app->response
						->setStatusCode(401, "Unathorized")
						->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => "Failed! Invalid subsite id."));
				}
				$SiteIDData 	= pg_fetch_assoc($SiteIDResult);
				$siteid 		= $SiteIDData["siteid"];
			}
			
			$serverSQL =<<<EOF
			SELECT s.externalip FROM epm_server AS s INNER JOIN epm_xref_site_environment AS x ON s.serverid = x.serverid WHERE x.siteid = $siteid AND x.environmentid = $environmentid;
EOF;
			$serverResult 	= pg_query($db, $serverSQL);

			if(pg_num_rows($serverResult) == 0){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => "Failed! No server exist for the given inputs."));
			}
			
			$serverData 	= pg_fetch_assoc($serverResult);
			$serverPath 	= "http://".trim($serverData["externalip"])."/API/RunUpdateDotPHPFile.php?environmentid=".$environmentid."&siteid=".$siteid."&subsiteid=".$subsiteid;
			
			$ch = curl_init($serverPath);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$return = curl_exec($ch);
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Could not connect with the site server.'));
			}
			curl_close($ch);
			
			if($return == 1){
				$this->app->response
					->setStatusCode(200, "Success")
					->setJsonContent(array('status' => 'Success', 'MsgDesc' => 'Success! update.php run successfully.'));
			}
			else {
				$this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => $return));
			}
		}
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}	
	
	public function ClearCache() {
        try {
			// Receiving get parameters 
			$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid 		= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";

			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized") 
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environment Id and Site/Subsite Id are mandatory.'));
			}

			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Could not connect to database.'));
			}
			
			if($siteid == ""){
				$SiteIDSQL =<<<EOF
				SELECT * from epm_sub_site WHERE subsiteid = $subsiteid AND environmentid=$environmentid;
EOF;
				$SiteIDResult 	= pg_query($db, $SiteIDSQL);
				if(pg_num_rows($SiteIDResult) == 0){
					return $this->app->response
						->setStatusCode(401, "Unathorized")
						->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => "Failed! Invalid subsite id."));
				}
				$SiteIDData 	= pg_fetch_assoc($SiteIDResult);
				$siteid 		= $SiteIDData["siteid"];
			}
			
			$serverSQL =<<<EOF
			SELECT s.externalip FROM epm_server AS s INNER JOIN epm_xref_site_environment AS x ON s.serverid = x.serverid WHERE x.siteid = $siteid AND x.environmentid = $environmentid;
EOF;
			$serverResult 	= pg_query($db, $serverSQL);

			if(pg_num_rows($serverResult) == 0){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => "Failed! No server exist for the given inputs."));
			}
			
			$serverData	= pg_fetch_assoc($serverResult);
			$serverPath	= "http://".trim($serverData["externalip"])."/API/ClearCache.php?environmentid=".$environmentid."&siteid=".$siteid."&subsiteid=".$subsiteid;

			$ch = curl_init($serverPath);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$return = curl_exec($ch);
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Could not connect with the site server.'));
			}
			curl_close($ch);
			
			if($return == 1){
				$this->app->response
					->setStatusCode(200, "Success")
					->setJsonContent(array('status' => 'Success', 'MsgDesc' => 'Success! Cache cleared successfully.'));
			}
			else {
				$this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => $return));
			}
		}
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        }
		return $this->app->response;
	}
	
	public function ExportDBToS3() {

        try {
			// Receiving get parameters 
			$apikey 			= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 		= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 			= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid 			= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";
			
			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environment ID and Site/Subsite ID are mandatory fields'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			if($siteid == ""){
				$SiteIDSQL =<<<EOF
				SELECT * from epm_sub_site WHERE subsiteid = $subsiteid;
EOF;
				$SiteIDResult 	= pg_query($db, $SiteIDSQL);
				if(pg_num_rows($SiteIDResult) == 0){
					return $this->app->response
						->setStatusCode(401, "Unathorized")
						->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Invalid subsite id.'));
				}
				$SiteIDData 	= pg_fetch_assoc($SiteIDResult);
				$siteid 		= $SiteIDData["siteid"];
			}
			
			$serverSQL =<<<EOF
			SELECT epm_server.externalip FROM epm_server JOIN epm_xref_site_environment ON epm_xref_site_environment.serverid = epm_server.serverid WHERE epm_xref_site_environment.siteid=$siteid AND epm_xref_site_environment.environmentid=$environmentid;
EOF;
			$serverResult = pg_query($db, $serverSQL);
			if(pg_num_rows($serverResult) == 0){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Server does not exist for the given site in source environment.'));
			}

			$serverData 	= pg_fetch_assoc($serverResult);
			$serverIP 		= trim($serverData["externalip"]);

			$serverPath = "http://".$serverIP."/API/ExportDBToS3.php?environmentid=".$environmentid."&siteid=".$siteid."&subsiteid=".$subsiteid;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $serverPath); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$result = curl_exec($ch);
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 404) {
				
				if($result == 1){
					$this->app->response
						->setStatusCode(200, "Success")
						->setJsonContent(array('status' => 'Success', 'MsgDesc' => 'Database has been exported to AWS'));
				}
				else {
					$this->app->response
						->setStatusCode(401, "Failure")
						->setJsonContent(array('status' => 'Error', 'MsgDesc' => $result));
				}
			}
			else {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Could not connect to source server'));
			}
			curl_close($ch);
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}

	public function ExportFilesToS3() {

        try {
			// Receiving get parameters 
			$apikey 			= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 		= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 			= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid 			= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";
			
			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environment ID and Site/Subsite ID are mandatory fields'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			if($siteid == ""){
				$SiteIDSQL =<<<EOF
				SELECT * from epm_sub_site WHERE subsiteid = $subsiteid;
EOF;
				$SiteIDResult 	= pg_query($db, $SiteIDSQL);
				if(pg_num_rows($SiteIDResult) == 0){
					return $this->app->response
						->setStatusCode(401, "Unathorized")
						->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Invalid subsite id.'));
				}
				$SiteIDData 	= pg_fetch_assoc($SiteIDResult);
				$siteid 		= $SiteIDData["siteid"];
			}
			
			$serverSQL =<<<EOF
			SELECT epm_server.externalip FROM epm_server JOIN epm_xref_site_environment ON epm_xref_site_environment.serverid = epm_server.serverid WHERE epm_xref_site_environment.siteid=$siteid AND epm_xref_site_environment.environmentid=$environmentid;
EOF;
			$serverResult = pg_query($db, $serverSQL);
			if(pg_num_rows($serverResult) == 0){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Server does not exist for the given site in source environment.'));
			}

			$serverData 	= pg_fetch_assoc($serverResult);
			$serverIP 		= trim($serverData["externalip"]);

			$serverPath = "http://".$serverIP."/API/ExportFilesToS3.php?environmentid=".$environmentid."&siteid=".$siteid."&subsiteid=".$subsiteid;
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $serverPath); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$result = curl_exec($ch);
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 404) {
				
				if($result == 1){
					$this->app->response
						->setStatusCode(200, "Success")
						->setJsonContent(array('status' => 'Success', 'MsgDesc' => 'Files has been exported to AWS'));
				}
				else {
					$this->app->response
						->setStatusCode(401, "Failure")
						->setJsonContent(array('status' => 'Error', 'MsgDesc' => $result));
				}
			}
			else {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed! Could not connect to source server'));
			}
			curl_close($ch);
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}
	
	public function GetListOfHistoricalDBExports() {

        try {
			// Receiving get parameters 
			$apikey 			= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 		= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 			= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid 			= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";
			
			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environment ID and Site/Subsite ID are mandatory fields'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			if($siteid != ""){
				$SQL =<<<EOF
				SELECT id,uploaded_timestamp,filesize from epm_s3_bucket_info WHERE siteid = $siteid AND environmentid=$environmentid AND file_type='1' ORDER BY id DESC;
EOF;
			}
			else {
				$SQL =<<<EOF
				SELECT id,uploaded_timestamp,filesize from epm_s3_bucket_info WHERE subsiteid = $subsiteid AND environmentid=$environmentid AND file_type='1' ORDER BY id DESC;
EOF;
			}

			$Result 	= pg_query($db, $SQL);
			if(pg_num_rows($Result) == 0){
				return $this->app->response
					->setStatusCode(200, "Success")
					->setJsonContent(array());
			}
			$list 	= pg_fetch_all($Result);
			return $this->app->response
				->setStatusCode(200, "Success")
				->setJsonContent($list);
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}

	public function GetListOfHistoricalFileExports() {

        try {
			// Receiving get parameters 
			$apikey 			= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$environmentid 		= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
			$siteid 			= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
			$subsiteid 			= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";
			
			if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key, Environment ID and Site/Subsite ID are mandatory fields'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}
			
			if($siteid != ""){
				$SQL =<<<EOF
				SELECT id,uploaded_timestamp,filesize from epm_s3_bucket_info WHERE siteid = $siteid AND environmentid=$environmentid AND file_type='0' ORDER BY id DESC;
EOF;
			}
			else {
				$SQL =<<<EOF
				SELECT id,uploaded_timestamp,filesize from epm_s3_bucket_info WHERE subsiteid = $subsiteid AND environmentid=$environmentid AND file_type='0' ORDER BY id DESC;
EOF;
			}

			$Result 	= pg_query($db, $SQL);
			if(pg_num_rows($Result) == 0){
				return $this->app->response
					->setStatusCode(200, "Success")
					->setJsonContent(array());
			}
			$list 	= pg_fetch_all($Result);
			return $this->app->response
				->setStatusCode(200, "Success")
				->setJsonContent($list);
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}
	
	public function GetPresignedURL(){

		try {
			// Receiving get parameters 
			$apikey 			= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
			$unique_id		 	= (isset($_GET["id"]))	 			? $_GET["id"]	 			: "";
			
			if($apikey != "api-dev-123456" || $unique_id == ""){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'API Key and Record Unique ID is Mandatory'));
			}
			
			// Making DB connection
			$db = $this->postgresDBConnection();
			if (!$db) {
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Could not connect to database.'));
			}

			$SQL =<<<EOF
			SELECT * FROM epm_s3_bucket_info WHERE id = $unique_id;
EOF;
			$Result 	= pg_query($db, $SQL);
			if(pg_num_rows($Result) == 0){
				return $this->app->response
					->setStatusCode(401, "Unathorized")
					->setJsonContent(array('status' => 'ERROR', 'MsgDesc' => 'Failed: Invalid unique id.'));
			}
			
			$Data = pg_fetch_assoc($Result);			

			// Instantiate the S3 client with your AWS credentials
			$s3Client = S3Client::factory(array(
				'credentials' => array(
					'key'    => 'AKIAIXAW4ILVD7W24IGQ',
					'secret' => 'qS2Ou0DCZ5UHfjJzYqzbNX60N6C9CSwk+ChzRhYm'
				),
				'region'  => 'us-east-1',
				'version' => 'latest'
			));

			$cmd = $s3Client->getCommand('GetObject', [
				'Bucket' => trim($Data["bucket_name"]),
				'Key'    => trim($Data["file_key"])
			]);

			$request = $s3Client->createPresignedRequest($cmd, '+180 minutes');

			$PreSignedURL = (string) $request->getUri();
		
			$this->app->response
				->setStatusCode(200, "Success")
				->setJsonContent(array('status' => 'Success', 'temporalLink'=>$PreSignedURL));
        }
        catch(Exception $e) {
			$this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => $e->getMessage()));
        } 
        return $this->app->response;
	}
}