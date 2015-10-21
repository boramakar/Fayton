<?php
###Database connection info
$dbIP = "localhost";
$dbUsername = "root";
$dbPass = "";
$dbName = "Fayton";
###

###Universal Sentinels
$EOT = "END OF TRANSMISSION\n**********\n";
$Reason = "";
$logDate = date('Y-m-d');
$logTxt = "C:\\wamp\\www\\Fayton\\Logs\\CreateEvent\\CreateEvent_$logDate.txt";
$chatDir = "C:\\wamp\\www\\Fayton\\Chat\\";
$handle = @fopen($logTxt, a);
###

###Settings
date_default_timezone_set('Europe/Istanbul');
###

###
####Section for implementing functions to make life easier
###
function randLetter()
{
	return chr(97 + mt_rand(0, 25));
}

function checkTokenExpiry()
{
	
	return date('Y-m-d H:i:s',strtotime('+15 minutes'));
}

function currentDate()
{
	return date('Y-m-d H:i:s');
}

function rowsPerPage()
{
	if(isset($_POST["RowsPerPage"])){
		return $_POST["RowsPerPage"];
	}
	else{
		return 50;
	}
}

function packageLog($package)
{
	global $logTxt, $EOT;
	$timestamp = currentDate();
	foreach($package as $index => $packageArray)
	{
		file_put_contents($logTxt, '**INDEX** [' . $timestamp . ']' . " Index: $index\n", FILE_APPEND | LOCK_EX);
		foreach($packageArray as $param => $val)
		{
			if($val == false){
				$val = '0';
			}
			file_put_contents($logTxt, '**PACKAGE** [' . $timestamp . ']' . " Param: $param; Value: $val\n", FILE_APPEND | LOCK_EX);
		}
	}
}

function dbConnect()
{
	global $handle, $EOT, $logTxt;
	global $dbIP, $dbUsername, $dbPass, $dbName;
	
	$con = mysqli_connect("$dbIP", "$dbUsername", "$dbPass", "$dbName");
	if(mysqli_connect_errno()){
		echo 'Database connection error: ' . mysqli_connect_error();
		file_put_contents($logTxt, "DATABASE CONNECTION ERROR!\n$EOT", FILE_APPEND | LOCK_EX);
		fclose($handle);
		exit();
	}
	else{
		return $con;
	}
}

function missingParams()
{
	global $handle, $EOT, $logTxt;
	
	$package = array("0" => array("status" => false, "reason" => 999));
	packageLog($package);
	file_put_contents($logTxt, "MISSING PARAMETERS!\n$EOT", FILE_APPEND | LOCK_EX);
    echo json_encode($package);
	fclose($handle);
	exit();
}

function verifyUser($con, $LoginID, $LoginPass)
{
	global $handle, $EOT, $logTxt;
	
	$timestamp = currentDate();
	$sql = "SELECT password FROM user WHERE username = '$LoginID'";
	$rs = mysqli_query($con, $sql);
	if($rs && mysqli_num_rows($rs) > 0){
		$row = mysqli_fetch_row($rs);
		$pass = $row[0];
		if ($pass = $LoginPass){
			file_put_contents($logTxt, '**VERIFED** [' . $timestamp . ']' . " Param: $LoginID; Value: $LoginPass\n", FILE_APPEND | LOCK_EX);
			return true;
		}
	}
	$package = array("0" => array("status" => false, "reason" => 998));
	packageLog($package);
	file_put_contents($logTxt, "USER NOT VERIFIED!\n$EOT", FILE_APPEND | LOCK_EX);
    echo json_encode($package);
	fclose($handle);
	exit();
}

function groupPartOf($con, $GroupID, $LoginID)
{
	#Check if user is a member of the group
	$sql = "SELECT * FROM groupmembers WHERE groupid = '$GroupID' AND username = '$LoginID'";
	$rs = mysqli_query($con, $sql);
	if(mysqli_num_rows($rs) > 0){
		return 1;
	}
	else{
		return 0;
	}
}

function queryFailed($con, $reason)
{
	global $handle, $logTxt, $EOT;
	
	if(!isset($reason)){
		$reason = 2;
	}
	$package = array("0" => array("status" => false, "reason" => $reason));
	#Logging
	packageLog($package);
	file_put_contents($logTxt, "QUERY FAILED!\n$EOT", FILE_APPEND | LOCK_EX);
	echo json_encode($package);
	fclose($handle);
	mysqli_close($con);
	exit();
}

function sendPackage($con, $package, $status, $reason, $explanation)
{
	global $handle, $EOT, $logTxt;
	
	if($package == ""){
		$package = array("0" => array("status" => $status, "reason" => $reason));
	}
	else{
		$fpackage = array("0" => array("status" => $status, "reason" => $reason));
		$package = array_merge($fpackage, $package);
	}
	packageLog($package);
	file_put_contents($logTxt, "$explanation!\n$EOT", FILE_APPEND | LOCK_EX);
	echo json_encode($package);
	fclose($handle);
	mysqli_close($con);
	exit();
	
}

###
####End of function implementation section
###

#######LOGGING
$timestamp = currentDate();
global $logTxt;

file_put_contents($logTxt, "NEW TRANSMISSION START\n", FILE_APPEND | LOCK_EX);
foreach ($_POST as $param_name => $param_val) {
	file_put_contents($logTxt, '**$_POST** [' . $timestamp . ']' . " Param: $param_name; Value: $param_val\n", FILE_APPEND | LOCK_EX);
}
#######END OF LOGGING

	####CREATE EVENT####
	if(isset($_POST["CreateEvent"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["EventLocation"], $_POST["EventType"], $_POST["EventName"], $_POST["StartTime"], $_POST["EndTime"], $_POST["EventDescription"], $_POST["ParticipantLimit"])){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$StartTime = mysqli_real_escape_string($con, $_POST["StartTime"]);
		$EndTime = mysqli_real_escape_string($con, $_POST["EndTime"]);
		$EventLocation = mysqli_real_escape_string($con, $_POST["EventLocation"]);
		$EventName = mysqli_real_escape_string($con, $_POST["EventName"]);
		$EventType = mysqli_real_escape_string($con, $_POST["EventType"]);
		$EventDescription = mysqli_real_escape_string($con, $_POST["EventDescription"]);
		$ParticipantLimit = mysqli_real_escape_string($con, $_POST["ParticipantLimit"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$MinKarma = NULL;
		$MinRating = NULL;
		$MaxRating = NULL;
		$PartOf = NULL;
		$Rated = NULL;
		$MinAge = NULL;
		$MaxAge = NULL;
		$Sex = NULL;		
		
		if(isset($_POST["MinKarma"]) && $_POST["MinKarma"] != ""){
			$MinKarma = mysqli_real_escape_string($con, $_POST["MinKarma"]);
		}
		if(isset($_POST["MinRating"]) && $_POST["MinRating"] != ""){
			$MinRating = mysqli_real_escape_string($con, $_POST["MinRating"]);
		}
		if(isset($_POST["MaxRating"]) && $_POST["MaxRating"] != ""){
			$MaxRating = mysqli_real_escape_string($con, $_POST["MaxRating"]);
		}
		if(isset($_POST["PartOf"]) && $_POST["PartOf"] != ""){
			$PartOf = mysqli_real_escape_string($con, $_POST["PartOf"]);
		}
		if(isset($_POST["Rated"]) && $_POST["Rated"] != ""){
			$Rated = mysqli_real_escape_string($con, $_POST["Rated"]);
		}
		if(isset($_POST["MinAge"]) && $_POST["MinAge"] != ""){
			$MinAge = mysqli_real_escape_string($con, $_POST["MinAge"]);
		}
		if(isset($_POST["MaxAge"]) && $_POST["MaxAge"] != ""){
			$MaxAge = mysqli_real_escape_string($con, $_POST["MaxAge"]);
		}
		if(isset($_POST["Sex"]) && $_POST["Sex"] != ""){
			$Sex = mysqli_real_escape_string($con, $_POST["Sex"]);
		}
		
		$sql = "INSERT INTO events (`eventname`, `host`, `participantlimit`, `eventtype`, `starttime`, `endtime`, `location`, `description`, `minkarma`, `MinAge`, `Sex`, `MinRating`, `MaxRating`, `Rated`, `PartOf`, `MaxAge`) VALUES ('$EventName', '$LoginID', '$ParticipantLimit', '$EventType', '$StartTime', '$EndTime', '$EventLocation', '$EventDescription', '$MinKarma', '$MinAge', '$Sex', '$MinRating', '$MaxRating', '$Rated', '$PartOf', '$MaxAge')";
		$result = mysqli_query($con, $sql);
		if(!$result){
			queryFailed($con, 2.1);
		}
		
		$sql = "INSERT INTO eventmembers (username, eventid, hidden, participation) VALUES ('$LoginID', LAST_INSERT_ID(), 0, 0)";
		$result = mysqli_query($con, $sql);
		if(!$result){
			queryFailed($con, 2.2);
		}
		
		$result = mysqli_query($con, "SELECT LAST_INSERT_ID()");
		$EventID = mysqli_fetch_row($result)[0];
		
		$sql = "INSERT INTO eventchats (eventid, username) VALUES ('$EventID', '$LoginID')";
		$result = mysqli_query($con, $sql);
		if(!$result){
			queryFailed($con, 2.3);
		}
		
		#Get the name of user
		$sql = "SELECT name FROM user WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		$Name = mysqli_fetch_row($result)[0];
		
		#Create chat file and send result of event creation
		$result = mysqli_query($con, "SELECT LAST_INSERT_ID()");
		$ChatID = mysqli_fetch_row($result)[0];
		$chatFile = $chatDir . "Event_$ChatID.txt";
		file_put_contents($chatFile, "[" . date('Y-m-d H:i:s') . "] $EventName event created!\n[" . date('H:i') . "] $Name joined event!\n", FILE_APPEND | LOCK_EX);
		$package = array("1" => array("EventID" => $EventID));
		sendPackage($con, $package, true, "", "EVENT CREATED");
	}
	
	else{
		missingParams();
	}
?>