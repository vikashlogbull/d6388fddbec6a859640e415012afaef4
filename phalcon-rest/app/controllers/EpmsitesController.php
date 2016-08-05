<?php
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
class EpmsitesController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
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

            //Get Input to the script for Git Repository create.
            $git = new git();
            $gitid = 1;
            $gitres = $git->getGitData($gitid);
            $gitencode = json_encode($gitres);
            $gitdecode = json_decode($gitencode);
            $gitUserName = $gitdecode->git_username;
            $git_token = $gitdecode->github_token;
            $RepoGitUrl = $gitdecode->git_url;
            $Description = "Initial checkin while creating site!";

            if ($apikey == 'null') {
                $flag = "failed";
                $returnmsg = http_response_code(401);
                $msgdesc = "API Key does not exist!";
                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            }

            if ($sitename == 'null') {
                 $flag = "failed";
                $returnmsg = http_response_code(404);
                $msgdesc = "Site name does not exist!";
                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            }

            if ($environmentname == 'null') {
                 $flag = "failed";
                $returnmsg = http_response_code(404);
                $msgdesc = "Environment value does not exist!";
                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            }

            if ($InstallType == 'null') {
                 $flag = "failed";
                $returnmsg = http_response_code(404);
                $msgdesc = "Installation type not specified!";
                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            }

            #Select APIKEY
            $objapikey = new apikey();
            $apikeyid = $objapikey->getApikeyId($apikey);
            if ($apikeyid == null) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'API Key does not exist!'));
            }

            #Match the company id within the DB backendcompanyid from company id
            $objcompany = new company();
            $companyname = $objcompany->getBackendCompanyName($frontendcompanyid);
            if ($companyname == null) {
                 $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
            }

            $companyid = $objcompany->getBackendCompanyIdbyFrontend($frontendcompanyid);
            if ($companyid == null) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
            }

            # Get environmentid
            $objenv = new environment();
            $environmentid = $objenv->getEnvironmentId($environmentname, 'active');
            if ($environmentid == null){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environment does not exist!'));
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

            # Get serverid
            $serverid = $objserv->getServerID($backendcompanyid, $environmentid);
            if ($serverid == null){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            $objsite = new sites();
            $siteid = $objsite->setSites($companyid, $sitename, 'Creating');
            if ($siteid) {
                $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'OK','message' => 'Site created succesfully', 'siteid' => $siteid));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site already exists!'));
            }

            // Subsite database entry in epm_database.
            $database_name = $sitename."_".$environmentname."_".$siteid;
            $dbtype = 'Mysql';
            $dbversion = '5.5.47';
            $dbhostname = '';
            $isprimary = 'TRUE';
            $status = 'active';
            $external_hostname = '';
            $primary_db_id = '1';

            $objxrefdbserv = new xrefServerDatabase();
            $databaseid = $objxrefdbserv->getXrefDBServId($serverid);

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
            $databaseid = $database->setDatabase($database_name, $dbusername, $dbpassword, $dbconnection_string, $dbport, $dbtype, $dbversion, $dbhostname, $environmentid, $isprimary, $status, $external_hostname, $primary_db_id);

	    $NewSite = trim($sitename)."_".trim($companyid)."_".trim($environmentid)."_".trim($siteid);

            #INSERT INTO epm_xref_site_environment
            $drupalfolderpath = '/var/www/html/'.$NewSite;
            $drup_dbname = $sitename."_".$environmentname."_".$siteid;
            $git_branch_id = '1';
            $database_uses_global_password = 'TRUE';
            $database_id = '1';
            $gitid = '1';
            $sitedomainname = $siteurl;
            $serverid = '1';
            $username = ''; 
            $password = '';
            $site_status = '';
            $objsiteenv = new siteenvironment();
            $xrefid = $objsiteenv->setSite(trim($siteid), trim($environmentid), trim($drupalfolderpath), trim($serverid), trim($sitedomainname), trim($drup_dbname), trim($dbconnection_string), trim($username), trim($password), trim($git_branch_id), trim($database_uses_global_password), trim($database_id), trim($gitid), trim($site_status));
            if ($xrefid) {
                $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'OK', 'message' => 'Site environment reference created', 'xrefid' => $xrefid));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site environment reference already exists!'));
            }

            if ($InstallType == 'GIT' || $InstallType == 'GITHUB') {
                $ListenerAPI = "http://".$serverip."/Listener.php?apikey=".$apikey."&sitename=".$sitename."&companyid=".$companyid."&giturl=".$git_url."&siteurl=".$siteurl."&environment=".$environmentname."&InstallType=".$InstallType."&Distribution=".$drupal_distribution."&distributionurl=&drup_dbname=".$drup_dbname."&db_ip=".$dbconnection_string."&dbuser=".$dbusername."&password=".$dbpassword."&NewSite=".$NewSite."&listeneraction=createsite";
                
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
                    throw new Exception("Invalid URL",$e);
                }
                $filesystempath = $_SERVER['DOCUMENT_ROOT']."/".$NewSite;

                $update1 = $objsite->updateSites($companyid, $sitename, 'Completed', $siteid);
                if ($update1) {
                    $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'OK', 'message' => 'Site created succesfully', 'siteid' => $update1));
                } else {
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site.'));
                }

                $update2 = $objsiteenv->updateSiteStatus($xrefid, 'Completed');
                if ($update2 == null) {
                    $this->app->response
                         ->setStatusCode(403, "Error")
                         ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site environment.'));
                }

            } elseif ($InstallType == 'FRESH' && $drupal_distribution != 'null'){

                 $objdist = new distributions();
                 $distributionurl = $objdist->getDistributionurl($drupal_distribution);
                 if ($distributionurl == null) {
                 $this->app->response
                    ->setStatusCode(404, "Not Found")
                    ->setJsonContent(array('status' => 'ERROR', 'data' => 'Drupl distribution not found!'));
                 }

                 $ListenerAPI = "http://".$serverip."/Listener.php?apikey=".$apikey."&sitename=".$sitename."&companyid=".$companyid."&giturl=".$git_url."&siteurl=".$siteurl."&environment=".$environmentname."&InstallType=".$InstallType."&Distribution=".$drupal_distribution."&distributionurl=".trim($distributionurl)."&drup_dbname=".$drup_dbname."&db_ip=".$dbconnection_string."&dbuser=".$dbusername."&password=".$dbpassword."&username=".$gitUserName."&token=".$git_token."&repogiturl=".$RepoGitUrl."&NewSite=".$NewSite."&listeneraction=createsite&description=".$Description;
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
                    throw new Exception("Invalid URL",$e);
                }
                $filesystempath = $_SERVER['DOCUMENT_ROOT']."/".$NewSite;

                $update1 = $objsite->updateSites($companyid, $sitename, 'Completed', $siteid);
                if ($update1) {
                    $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'OK', 'message' => 'Site created succesfully', 'siteid' => $update1));
                } else {
                    $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site.'));
                }

                $update2 = $objsiteenv->updateSiteStatus($xrefid, 'Completed');
                if ($update2 == null) {
                    $this->app->response
                         ->setStatusCode(403, "Error")
                         ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site environment.'));
                }
            }
        }
        catch(Exception $e) {
            echo $e->getMessage();
        } 
        return $this->app->response;
     }

    public function delete($siteid) {

        try {
            if ($siteid == 'null') {
                 $flag = "failed";
                 $returnmsg = http_response_code(404);
                 $msgdesc = "Site ID does not exist!";
                return array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc );
            }

            $objsiteid = new sites();
            $sitename = $objsiteid->getSiteName($siteid);
            if ($sitename == null) {
                 $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Name does not exist!'));
            }

            # Get companyid 
            $companyid = $objsiteid->getCompanyId($siteid);
            if ($companyid == null){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            $objsiteenv = new siteenvironment();
            $siteurl = trim($objsiteenv->getSiteUrl($siteid));
            if ($siteurl == null) {
                 $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Url does not exist!'));
            }

            # Get environmentid 
            $environmentid = $objsiteenv->getEnvironmentId($siteid);
            if ($environmentid == null){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
            }

            # Get xrefid 
            $xrefid = $objsiteenv->getXrefID($siteid);
            if ($xrefid == null){
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

            // Need to ensure that it calls the delete funciton in the Listener. *****
	    $deletesitename = trim($sitename);
            $ListenerAPI = "http://".$serverip."/Listener.php?deletesitename=".trim($deletesitename)."&siteurl=".trim($siteurl)."&listeneraction=deletesite";

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
            catch(Exception $e) {
                throw new Exception("Invalid URL",$e);
            }

            // Check result and then take action
            $update1 = $objsiteid->updateSiteStatus('deleted', $siteid);
            if ($update1) {
                $this->app->response
                        ->setStatusCode(201, "deleted")
                        ->setJsonContent(array('status' => 'OK', 'message' => 'Site deleted succesfully', 'data' => $update1));
            } else {
                $this->app->response
                        ->setStatusCode(403, "Error")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the status of the site.'));
            }

            $update2 = $objsiteenv->updateSiteStatus($xrefid, 'Deleted');
            if ($update2 == null) {
                $this->app->response
                     ->setStatusCode(403, "Error")
                     ->setJsonContent(array('status' => 'ERROR', 'data' => 'Values are wrong for updating the site environment.'));
            }
        }
        catch(Exception $e){
            echo $e->getMessage();
        }
        return $this->app->response;
    }
}
