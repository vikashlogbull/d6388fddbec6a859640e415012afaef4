<?php

use Phalcon\Mvc\Controller;

class IndexController extends Controller
{
	/**
	 * Connecting with postgres database
	 * RETURNS: Database Object
	*/	
	protected function postgresDBConnection(){
		
		$host        = "host=54.174.204.196";
		$port        = "port=5432";
		$dbname      = "dbname=evolpaasmgr";
		$credentials = "user=postgres password=lbit123";
		return pg_connect( "$host $port $dbname $credentials"  );
	}

	public function GetIPAddressOfSitesAction(){

		// Receiving get parameters 
		$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
		$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
		$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
		$subsiteid 		= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";

		if($apikey != "api-dev-123456" || $environmentid == "" || ($siteid == "" && $subsiteid == "")){
			return json_encode(array());
		}
		else {
			// Making DB connection
			// Return false if db connection failed
			$db = $this->postgresDBConnection();
			if (!$db) {
				$flag = "failed";
				$returnmsg = http_response_code(401);
				$msgdesc="Connection issue with Master DB";
				return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
			}
			else {
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
				$data = array("serverip"=>$IPData["externalip"]);
				echo json_encode($data);
			}
		}
	}
	
	public function ListSitesAndSubsitesAction() {

		// Receiving get parameters 
		$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
		$companyid 		= (isset($_GET["companyid"])) 		? $_GET["companyid"] 		: "";
		$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: "";
		$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";

		if($apikey != "api-dev-123456" || $companyid == ""){
			http_response_code(401);
			echo json_encode(array());
			return false;
		}

		// Making DB connection
		// Return false if db connection failed
		$db = $this->postgresDBConnection();
		if (!$db) {
			$flag = "failed";
			$returnmsg = http_response_code(401);
			$msgdesc="Connection issue with Master DB";
			return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
		}
		else {
			// Fetching backend company id
			$companySQL =<<<EOF
			SELECT * from epm_company WHERE frontendcompanyid = '$companyid';
EOF;
			$companyResult 	= pg_query($db, $companySQL);
			$companyData 	= pg_fetch_assoc($companyResult);
			$backend_companyid = $companyData["backendcompanyid"];

			if($backend_companyid == ""){
				$flag = "failed";
				$returnmsg = http_response_code(401);
				$msgdesc="This company id doesn't exist in the database.";
				//return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
				echo json_encode(array());
				return false;
			}			
			
			//Fetching server ip based on given input parameters
			if($siteid != "" && $environmentid != ""){
				$serverSQL =<<<EOF
				SELECT s.externalip,s.serverid,x.environmentid from epm_server AS s INNER JOIN epm_xref_site_environment AS x ON s.serverid = x.serverid WHERE s.backendcompanyid = $backend_companyid AND s.isprimary=TRUE AND x.siteid=$siteid AND x.environmentid=$environmentid AND lower(s.serverstatus) = 'active';
EOF;
			} 
			else if($environmentid != "") {
				$serverSQL =<<<EOF
				SELECT externalip,serverid,environmentid from epm_server WHERE backendcompanyid = $backend_companyid AND environmentid = $environmentid AND isprimary=TRUE AND lower(serverstatus) = 'active';
EOF;
			}
			else {
				$serverSQL =<<<EOF
				SELECT externalip,serverid,environmentid from epm_server WHERE backendcompanyid = $backend_companyid AND isprimary=TRUE AND lower(serverstatus) = 'active';
EOF;
			}

			$serverResult = pg_query($db, $serverSQL);
			$serverData = pg_fetch_all($serverResult);
			
			if(!$serverData){
				$flag = "failed";
				$returnmsg = http_response_code(401);
				$msgdesc="No server exist for the given inputs."; 
				//return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
				echo json_encode(array());
				return false;
			}
			
			// Fetching site details using server_ip
			$sitesArray = array();			
			foreach($serverData as $val){
				
				$serverPath = "http://".trim($val["externalip"])."/API/SiteListing.php?serverid=".$val["serverid"]."&companyid=".$backend_companyid."&environmentid=".$val["environmentid"]."&siteid=".$siteid; // Main server primary path
				
				$ch = curl_init($serverPath);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
				$sites = curl_exec($ch);
				
				if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 404) {
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

			//Finaly, returning the site/subsite json
			header('Content-Type: application/json;charset=utf-8');
			echo json_encode($sitesArray);
		}
	}
	
	public function CloneSiteBetweenEnvironmentsAction(){
		// Receiving get parameters 
		$apikey 				= (isset($_GET["apikey"])) 					? $_GET["apikey"] 				: "";
		$source_environmentid 	= (isset($_GET["source_environmentid"])) 	? $_GET["source_environmentid"]	: "";
		$target_environmentid 	= (isset($_GET["target_environmentid"])) 	? $_GET["target_environmentid"]	: "";
		$siteid 				= (isset($_GET["siteid"])) 					? $_GET["siteid"] 				: "";
		$subsiteid 				= (isset($_GET["subsiteid"])) 				? $_GET["subsiteid"] 			: "";
		
		if($source_environmentid == "" || $target_environmentid == "" || ($siteid == "" && $subsiteid == "")){
			return json_encode(array());
		}
		if($siteid == ""){
			$SiteIDSQL =<<<EOF
			SELECT * from epm_sub_site WHERE subsiteid = $subsiteid;
EOF;
			$SiteIDResult 	= pg_query($db, $SiteIDSQL);
			if(pg_num_rows($SiteIDResult) == 0){
				$flag = "failed";
				$returnmsg = http_response_code(401);
				$msgdesc="Failed! Invalid subsite id.";
				return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
			}
			$SiteIDData 	= pg_fetch_assoc($SiteIDResult);
			$siteid 		= $SiteIDData["siteid"];
		}
		
		// Making DB connection
		// Return false if db connection failed
		$db = $this->postgresDBConnection();
		if (!$db) {
			$flag = "failed";
			$returnmsg = http_response_code(401);
			$msgdesc="Failed! Connection issue with Master DB.";
			return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
		}
		else {
			$sourceServerSQL =<<<EOF
			SELECT epm_server.externalip, epm_server.serverid from epm_server JOIN epm_xref_site_environment ON epm_xref_site_environment.serverid = epm_server.serverid WHERE epm_xref_site_environment.siteid=$siteid AND epm_xref_site_environment.environmentid=$source_environmentid;
EOF;
			
			$sourceServerResult = pg_query($db, $sourceServerSQL);
			if(pg_num_rows($sourceServerResult) == 0){
				$flag = "failed";
				$returnmsg = http_response_code(401);
				$msgdesc="Failed! No Server exist for the selected site and source environment.";
				return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
			}
			$sourceServerData 	= pg_fetch_assoc($sourceServerResult);			
			$sourceServerIP = $sourceServerData["externalip"];
			$sourceServerID = $sourceServerData["serverid"];
			
			$targetServerSQL =<<<EOF
			SELECT epm_server.externalip, epm_server.serverid from epm_server JOIN epm_xref_site_environment ON epm_xref_site_environment.serverid = epm_server.serverid WHERE epm_xref_site_environment.siteid=$siteid AND epm_xref_site_environment.environmentid=$target_environmentid;
EOF;

			$targetServerResult = pg_query($db, $targetServerSQL);
			if(pg_num_rows($targetServerResult) == 0){
				$flag = "failed";
				$returnmsg = http_response_code(401);
				$msgdesc="Failed! No Server exist for the selected site and target environment.";
				return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
			}
			
			$targetServerData 	= pg_fetch_assoc($targetServerResult);
			$targetServerIP = $targetServerData["externalip"];
			$targetServerID = $targetServerData["serverid"];
			
			echo $serverPath = "http://".trim($sourceServerIP)."/CloneDatabase.php?serverid=".$sourceServerID."&environmentid=".$source_environmentid."&siteid=".$siteid;			
			die;
			
			$ch = curl_init(); 
			curl_setopt($ch, CURLOPT_URL, $serverPath); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$sites = curl_exec($ch);
			curl_close($ch);
			$sitesArray = array_merge($sitesArray,(array) json_decode($sites));
		}
	}
	
	public function GetConnectionInfoAction(){
		
		header('Content-Type: application/json;charset=utf-8');
		
		// Receiving get parameters 
		$apikey 		= (isset($_GET["apikey"])) 			? $_GET["apikey"] 			: "";
		$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
		$subsiteid 		= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";
		$environmentid	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"] 	: "";

		if($apikey != "api-dev-123456" || ($siteid == "" && $subsiteid == "") || $environmentid == ""){
			http_response_code(401);
			echo json_encode(array());
			return false;
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

		$db = $this->postgresDBConnection();

		$serverIPResult 	= pg_query($db, $serverIPSQL);

		$return = array();

		if(pg_num_rows($serverIPResult)>0){
			
			$serverIPData 	= pg_fetch_assoc($serverIPResult);			
			$serverPath 	= "http://".trim($serverIPData["externalip"])."/API/GetConnectionInfo.php?environmentid=".$environmentid."&siteid=".$siteid;
			
			$ch = curl_init($serverPath);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$return = curl_exec($ch);
			if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 404) {
				echo $return;
				die();
			}
			curl_close($ch);
		}
		echo json_encode(array());
	}
	
        public function createSite()
        {         
            // Get input to the script 
            $apikey =  $_GET["apikey"];
            $sitename = $_GET["sitename"];
            $companyid =  $_GET["companyid"];
            $siteurl =  $_GET["siteurl"];
            $giturl =  $_GET["giturl"];
            $environment =  $_GET["environment"];
            $InstallType = $_GET["InstallType"];
            $Distribution = $_GET["Distribution"]; //drupal-7

	    //Get Input to the script for Git Repository create.
	    $UserName = $_GET["username"];
	    $Token = $_GET["token"];
	    $RepoGitUrl = $_GET["repogiturl"];
	    $Description = $_GET["description"];

            // Master DB connection creation
            $host        = "host=54.174.204.196";
            $port        = "port=5432";
            $dbname      = "dbname=evolpaasmgr";
            $credentials = "user=postgres password=lbit123";

            $db = pg_connect( "$host $port $dbname $credentials"  );
            if (!$db) {
                $flag = "failed";
                $returnmsg = http_response_code(500);
                $msgdesc="Connection issue with Master DB";
                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            } else {
                if ($apikey == 'null') {
                     $flag = "failed";
                     $returnmsg = http_response_code(401);
                     $msgdesc = "API Key does not exist!";
                     return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                }

                if ($sitename == 'null') {
                     $flag = "failed";
                     $returnmsg = http_response_code(401);
                     $msgdesc = "Site name does not exist!";
                     return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                }

                if ($environment == 'null') {
                     $flag = "failed";
                     $returnmsg = http_response_code(401);
                     $msgdesc = "Environment value does not exist!";
                     return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                } else {
                    // Check if the $environment value is found in the epm_environment table. If not found, then report unsupported environment unsupported.
                }

                if ($InstallType == 'null') {
                     $flag = "failed";
                     $returnmsg = http_response_code(401);
                     $msgdesc = "Installation type not specified!";
                     return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                } else {
                    // Check if the Installtype is not other then the supported types.
                }

                    $sql =<<<EOF
              			SELECT id from epm_apikeyid where apikey = '$apikey';
EOF;
               	}
            // Query the DB to check if API key exists
            $return = pg_query($db, $sql);
            while($row = pg_fetch_row($return)) {
                $validID = $row[0];

                if($validID == 'null') {
                    $flag = "failed";
                    $returnmsg = http_response_code(401);
                    $msgdesc="Failure! Invalid APIKEY";
                    return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                    //echo json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
                } elseif ($validID != null) {
                    //Check company id if null or not exists then return the error message 
                    if ($companyid == null) {
                       $flag = "failed";
                       $returnmsg = http_response_code(500);
                       $msgdesc="Company ID does not exist";
                       return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                       //exit;
                    } else {
                    // Match the company id within the DB backendcompanyid from company id
                        $sql11 =<<<EOF
                         SELECT backendcompanyid from epm_company where backendcompanyid = '$companyid';
EOF;
                        $returncompanyid = pg_query($db, $sql11);
                        if ($returncompanyid == null) {
                           $flag = "failed";
                           $returnmsg = http_response_code(500);
                           $msgdesc="Company ID does not exist in database";
                           return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                        }

                        while($row = pg_fetch_row($returncompanyid)) {
                            $validcustomerID = $row[0];
                            if($validcustomerID == 'null') {
                                $flag = "failed";
                                $returnmsg = http_response_code(401);
                                $msgdesc="Invalid Company ID";
                                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                                //exit;
                            }
                        }
                    }
                    $sql6 =<<<EOF
                    SELECT environmentid from epm_environment where environmentname = '$environment';
EOF;
                    $returnurl = pg_query($db, $sql6);
                    while($row = pg_fetch_row($returnurl)) {
                    $environmentid = $row[0];
                    }
                    $sql1 =<<<EOF
        	INSERT INTO epm_sites (sitename,sitestatus,sitedomainname,git_url,createsite_date,drup_dbname,dbconnection_string,username,password) VALUES ('$sitename', 'creating','$siteurl','$giturl',now(),'$sitename','54.165.156.253','root','logbull2016' ) RETURNING siteid;
	
EOF;
                    $returnurl = pg_query($db, $sql1);
                         while($row = pg_fetch_row($returnurl)) {
                               $siteid = $row[0];
                         }
                    $sql4 =<<<EOF
                    INSERT INTO epm_xref_site_environment (siteid, environmentid, create_date) VALUES ('$siteid', '$environmentid', now()) RETURNING xrefid;
EOF;
                    $returnurl = pg_query($db, $sql4);
                        while($row = pg_fetch_row($returnurl)) {
                              $xrefid = $row[0];
                        }
                    
                    $drup_dbname = $sitename."_".$environment."_".$siteid;
                    $sitename = $sitename."_".$companyid."_".$environment."_".$siteid;
                    if ($InstallType == 'GIT' || $InstallType == 'GITHUB') {
                        $ListenerAPI = "http://52.70.235.72/Listener.php?apikey=".$apikey."&sitename=".$sitename."&companyid=".$companyid."&giturl=".$giturl."&siteurl=".$siteurl."&environment=".$environment."&InstallType=".$InstallType."&Distribution=".$Distribution."&distributionurl=&drup_dbname=".$drup_dbname."&db_ip=54.165.156.253&dbuser=root&password=logbull2016";
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $ListenerAPI);
                        curl_setopt($ch, CURLOPT_HEADER, true); 
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, "type=cncl&reason=ticket.type.cancel.7");
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/xml", "Authorization: removed_dev_key:removed_api_key"));
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $filesystempath = $_SERVER['DOCUMENT_ROOT']."/".$sitename;
                        $sql2 =<<<EOF
                        UPDATE epm_sites SET sitestatus = 'Completed' , updatesite_date = now(), filesystempath = '$filesystempath',drup_dbname = '$drup_dbname' WHERE siteid= '$siteid';
EOF;
                        $return = pg_query($db, $sql2);
                        if ($return == null) {
                            $flag = "Failed";
                            $returnmsg = "202";
                            $msgdesc = error_get_last();
                            return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                        }
                        $flag = "Success";
                        $returnmsg = "100";
                        $msgdesc = "Operation done successfully";
                        pg_close($db);

                        if (!error_get_last()){
                            return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                        }
                    } elseif ($InstallType == 'FRESH' && $Distribution != 'null') {
                        $sql3 =<<<EOF
                        SELECT distributionurl from epm_drupal_distributions where distributionname = '$Distribution';
EOF;
                        $returnurl = pg_query($db, $sql3);
                        while($row = pg_fetch_row($returnurl)) {
                            $distributionurl = trim($row[0]);
			    $ListenerAPI = "http://52.70.235.72/Listener.php?apikey=".$apikey."&sitename=".$sitename."&companyid=".$companyid."&giturl=".$giturl."&siteurl=".$siteurl."&environment=".$environment."&InstallType=".$InstallType."&Distribution=".$Distribution."&distributionurl=".$distributionurl."&drup_dbname=".$drup_dbname."&db_ip=54.165.156.253&dbuser=root&password=logbull2016&username=".$UserName."&token=".$Token."&repogiturl=".$RepoGitUrl."&description=".$Description;
        	            $ch = curl_init();
        	            curl_setopt($ch, CURLOPT_URL, $ListenerAPI);
        	            curl_setopt($ch, CURLOPT_HEADER, true); 
        	            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, "type=cncl&reason=ticket.type.cancel.7");
	                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        	            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/xml", "Authorization: removed_dev_key:removed_api_key"));
        	            $result = curl_exec($ch);
        	            curl_close($ch);
                            $filesystempath = $_SERVER['DOCUMENT_ROOT']."/".$sitename;
                            $sql2 =<<<EOF
                            UPDATE epm_sites SET sitestatus = 'Completed' , updatesite_date = now(), filesystempath = '$filesystempath', drup_dbname = '$drup_dbname' WHERE siteid= '$siteid';
    
EOF;
                            $return = pg_query($db, $sql2);
                            if ($return == null) {
                                $flag = "Failed";
                                $returnmsg = "202";
                                $msgdesc = error_get_last();
                                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                            }
                            $flag = "Success";
                            $returnmsg = "100";
                            $msgdesc = "Operation done successfully";
                            pg_close($db);
                    
                            return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
                            }
                        }
                    }
                }
            } //Function closed
        
			public function getSites(){
				$companyid 		= (isset($_GET["companyid"])) 		? $_GET["companyid"] 		: NULL;
				$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: NULL;
				$environmentid 	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"]	: NULL;

				$ch = curl_init("52.70.235.72/API/SiteListing.php?companyid=$companyid&environmentid=$environmentid&siteid=$siteid");
				curl_setopt($ch, CURLOPT_HEADER, 0);
				$output = curl_exec($ch);
				curl_close($ch);
			}
			
		public function getSiteAction(){
			
			header('Content-Type: application/json;charset=utf-8');

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
			else {
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
					$subsites = $this->getSubSitesBySiteID($siteid,$environmentid);
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
		}

		protected function getSubSitesBySiteID($siteid,$environmentid){
			
			$db = $this->postgresDBConnection();
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
	} //Class Closed
        
?>
