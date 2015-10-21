<?php
###Database connection info
$dbIP = "localhost";
$dbUsername = "root";
$dbPass = "";
$dbName = "fayton";
###

###Universal Sentinels
$EOT = "END OF TRANSMISSION\n**********\n";
$Reason = "";
$logDate = date('Y-m-d');
$logTxt = "C:\\wamp\\www\\Fayton\\Logs\\CreateGroup\\CreateGroup_$logDate.txt";
$chatDir = "C:\\wamp\\www\\Fayton\\Chat\\";
$handle = @fopen($logTxt, a);
###

###Settings
date_default_timezone_set('Europe/Istanbul');
###

###
####Section for implementing functions to make life easier
###
{
function randLetter()
{
	return chr(97 + mt_rand(0, 25));
}

function createToken()
{
	$newtoken = "TKN";
	for( $i = 0; $i < 3; $i++){
		$newtoken = $newtoken . randLetter();
	}
	for( $j = 0; $j < 11; $j++){
		$newtoken = $newtoken . rand(0, 9);
	}
	return $newtoken;
}

function tokenExpiry()
{
	return date('Y-m-d H:i:s',strtotime('+15 minutes'));
}

function currentDate()
{
	return date('Y-m-d H:i:s');
}

function packageLog($package)
{
	global $handle, $EOT, $logTxt;
	
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
	$sql = "SELECT password FROM admins WHERE adminid = '$LoginID'";
	$rs = mysqli_query($con, $sql);
	if($rs && mysqli_num_rows($rs) > 0){
		$row = mysqli_fetch_row($rs);
		$pass = $row[0];
		if ($pass == $LoginPass){
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
	global $handle, $EOT, $logTxt;
	
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

	####CREATE GROUP####
	if(isset($_POST["CreateGroup"], $_POST["AdminID"], $_POST["AdminPass"], $_POST["GroupName"], $_POST["GroupDescription"]) && $_POST["AdminID"] != "" && $_POST["AdminPass"] != "" && $_POST["GroupName"] != "" & $_POST["GroupDescription"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$GroupName = mysqli_real_escape_string($con, $_POST["GroupName"]);
		$GroupDescription = mysqli_real_escape_string($con, $_POST["GroupDescription"]);
		$AdminID = mysqli_real_escape_string($con, $_POST["AdminID"]);
		$AdminPass = mysqli_real_escape_string($con, $_POST["AdminPass"]);
		#Verify User
		verifyUser($con, $AdminID, $AdminPass);
		$package = array();
		
		$sql = "INSERT INTO groups (description, name) VALUES ('$GroupDescription', '$GroupName')";
		$result = mysqli_query($con, $sql);
		if(!$result){
			queryFailed($con, 2.1);
		}
		
		$result = mysqli_query($con, "SELECT LAST_INSERT_ID()");
		$ChatID = mysqli_fetch_row($result)[0];
		$chatFile = $chatDir . "Group_$ChatID.txt";
		file_put_contents($chatFile, "[" . date('Y-m-d H:i:s') . "] $GroupName group created!\n", FILE_APPEND | LOCK_EX);
		sendPackage($con, $package, true, "", "GROUP ADDED");
	}
	else{
		missingParams();
	}
?>