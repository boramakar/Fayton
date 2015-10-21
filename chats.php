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
$logTxt = "C:\\wamp\\www\\Fayton\\Logs\\Chats\\Chats_$logDate.txt";
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

function getChatDetails($con, $LoginID, $i, $ChatID, $ChatType)
{
	global $handle, $EOT, $logTxt;
	
	$package = array();
	if($ChatID == ""){
		#Select all event chats
		$sql = "SELECT eventid FROM eventchats WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			while($row = mysqli_fetch_row($result)){
				$ChatID = $row[0];
				$sql = "SELECT eventname FROM events WHERE eventid = '$ChatID'";
				$rs = mysqli_query($con, $sql);
				if(mysqli_num_rows($rs) > 0){
					$row =mysqli_fetch_row($rs);
					$ChatName = $row[0];
					$tmp = array("$i" => array("BelongsTo" => "1", "ID" => $ChatID, "ChatName" => $ChatName));
					$package = array_merge($package, $tmp);
					$i++;
				}
			}
		}
		else{
			queryFailed($con, 5.1);
		}
		#Select all group chats
		$sql = "SELECT groupid FROM groupchats WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			while($row = mysqli_fetch_row($result)){
				$ChatID = $row[0];
				$sql = "SELECT name FROM groups WHERE groupid = '$ChatID'";
				$rs = mysqli_query($con, $sql);
				if(mysqli_num_rows($rs) > 0){
					$row =mysqli_fetch_row($rs);
					$ChatName = $row[0];
					$tmp = array("$i" => array("BelongsTo" => "2", "ID" => $ChatID, "ChatName" => $ChatName));
					$package = array_merge($package, $tmp);
					$i++;
				}
			}
		}
		else{
			queryFailed($con, 5.2);
		}
		#Return all chats found
		return $package;
	}
	else{
		if($ChatType == "1"){
			$sql = "SELECT eventname FROM events WHERE eventid = '$ChatID'";
			$rs = mysqli_query($con, $sql);
			if($rs && (mysqli_num_rows($rs) > 0)){
				$row =mysqli_fetch_row($rs);
				$ChatName = $row[0];
				$tmp = array("$i" => array("BelongsTo" => "Event", "ChatID" => $ChatID, "ID" => $ChatID, "ChatName" => $ChatName));
				$package = array_merge($package, $tmp);
			}
			else{
				queryFailed($con, 5.3);
			}
			return $package;
		}
		else{
			$sql = "SELECT name FROM groups WHERE groupid = '$ChatID'";
			$rs = mysqli_query($con, $sql);
			if($rs){
				$row =mysqli_fetch_row($rs);
				$ChatName = $row[0];
				$tmp = array("$i" => array("BelongsTo" => "Group", "ChatID" => $ChatID, "ID" => $ChatID, "ChatName" => $ChatName));
				$package = array_merge($package, $tmp);
			}
			else{
				queryFailed($con, 5.4);
			}
			return $package;
		}
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

	####MY CHATS####
	if(isset($_POST["GetMyChats"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		
		#Get chat details
		$package = getChatDetails($con, $LoginID, 1, "", "");
		sendPackage($con, $package, true, "", "MY CHATS SENT");
	}
	
	####GET CHAT####
	else if(isset($_POST["GetChat"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["BelongsTo"]) && $_POST["GetChat"] != "" && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$ChatType = mysqli_real_escape_string($con, $_POST["BelongsTo"]);
		$ChatID = mysqli_real_escape_string($con, $_POST["GetChat"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		
		#Get chat details
		$package = getChatDetails($con, $LoginID, 1, $ChatID, $ChatType);
		
		if($ChatType == 1){
			$chatFile = $chatDir . "Event_$ChatID.txt";
		}
		else{
			$chatFile = $chatDir . "Group_$ChatID.txt";
		}
		$ChatData = file_get_contents($chatFile);
		$chatpackage = array("2" => array("ChatData" => $ChatData, "TimeStamp" => $timestamp));
		$package = array_merge($package, $chatpackage);
		sendPackage($con, $package, true, "", "CHAT SENT");
	}
	####UPDATE CHAT#### //COPY OF GET CHAT!! WILL BE RE-WERITTEN
	else if(isset($_POST["UpdateChat"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["BelongsTo"]) && $_POST["UpdateChat"] != "" && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$ChatType = mysqli_real_escape_string($con, $_POST["BelongsTo"]);
		$ChatID = mysqli_real_escape_string($con, $_POST["UpdateChat"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		
		#Get chat details
		$package = getChatDetails($con, $LoginID, 1, $ChatID, $ChatType);
		
		if($ChatType == 1){
			$chatFile = $chatDir . "Event_$ChatID.txt";
		}
		else{
			$chatFile = $chatDir . "Group_$ChatID.txt";
		}
		$ChatData = file_get_contents($chatFile);
		$chatpackage = array("2" => array("ChatData" => $ChatData, "TimeStamp" => $timestamp));
		$package = array_merge($package, $chatpackage);
		sendPackage($con, $package, true, "", "CHAT SENT");
	}
	
	####POST CHAT####
	else if(isset($_POST["PostChat"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["ChatData"], $_POST["BelongsTo"]) && $_POST["PostChat"] != "" && $_POST["LoginID"] != "" && $_POST["LoginPass"] != "" && $_POST["ChatData"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$ChatType = mysqli_real_escape_string($con, $_POST["BelongsTo"]);
		$ChatID = mysqli_real_escape_string($con, $_POST["PostChat"]);
		$ChatData = mysqli_real_escape_string($con, $_POST["ChatData"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		
		$sql = "SELECT name FROM user WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		$UserName = mysqli_fetch_row($result)[0];
		
		if($ChatType == 1){
			$chatFile = $chatDir . "Event_$ChatID.txt";
		}
		else{
			$chatFile = $chatDir . "Group_$ChatID.txt";
		}
		$chattime = date('H:i');
		$ChatData = "[" . $chattime . "] $UserName: $ChatData";
		file_put_contents($chatFile, "$ChatData\n", FILE_APPEND | LOCK_EX);
		sendPackage($con, $package, true, "", "CHAT UPDATED");
	}

	else{
		missingParams();
	}
?>