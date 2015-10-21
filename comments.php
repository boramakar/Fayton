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
$logTxt = "C:\\wamp\\www\\Fayton\\Logs\\Comments\\Comments_$logDate.txt";
$commentDir = "C:\\wamp\\www\\Fayton\\Comments\\";
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
	return date('Y-m-d');
}

function rowsPerPage()
{
	if(isset($_POST["RowsPerPage"])){
		return mysqli_real_escape_string($con, $_POST["RowsPerPage"]);
	}
	else{
		return 10;
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
	
	if($package == array()){
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
	file_put_contents($logTxt, '**' . '** [' . $timestamp . ']' . " Param: $param_name; Value: $param_val\n", FILE_APPEND | LOCK_EX);
}
#######END OF LOGGING

	####POST COMMENT####
	if(isset($_POST["PostAttend"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["Data"], $_POST["To"]) && $_POST["PostAttend"] != "" && $_POST["LoginID"] != "" && $_POST["LoginPass"] != "" && $_POST["Data"] != "" && $_POST["To"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$EventID = mysqli_real_escape_string($con, $_POST["PostAttend"]);
		$Attend = mysqli_real_escape_string($con, $_POST["Data"]);
		$To = mysqli_real_escape_string($con, $_POST["To"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$CommentDate = currentDate();
		$package = array();
		
		if($Attend == "Yes"){
			$Attend == 1;
			$karmachange = '+ 1';
		}
		else{
			$Attend = 0;
			$karmachange = '- 1';
		}
		
		$sql = "UPDATE profile SET karma = karma $karmachange WHERE username = '$To'; ";
		$sql .= "INSERT INTO comments (author, username, eventid, commentdate, attendance) VALUES ('$LoginID', '$To', '$EventID', '$CommentDate', '$Attend')";
		$result = mysqli_multi_query($con, $sql);
		if($result){
			sendPackage($con, $package, true, "", "ATTENDANCE ADDED");
		}
		else{
			queryFailed($con, 2.1);
		}
	}
	
	else if(isset($_POST["PostComment"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["Data"], $_POST["To"]) && $_POST["PostComment"] != "" && $_POST["LoginID"] != "" && $_POST["LoginPass"] != "" && $_POST["Data"] != "" && $_POST["To"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$EventID = mysqli_real_escape_string($con, $_POST["PostComment"]);
		$CommentText = mysqli_real_escape_string($con, $_POST["Data"]);
		$To = mysqli_real_escape_string($con, $_POST["To"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$CommentDate = currentDate();
		$package = array();
		
		$sql = "UPDATE comments SET commenttext = '$CommentText', commentdate = '$CommentDate' WHERE author = '$LoginID' AND username = '$To' AND eventid = '$EventID'";
		$result = mysqli_multi_query($con, $sql);
		if($result){
			sendPackage($con, $package, true, "", "COMMENT TEXT ADDED");
		}
		else{
			queryFailed($con, 2.1);
		}
	}
	else{
		missingParams();
	}
?>