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
$logTxt = "C:\\wamp\\www\\Fayton\\Logs\\Events\\Events_$logDate.txt";
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

function eventPartOf($con, $EventID, $LoginID)
{
	#Check if user is a member of the group
	$sql = "SELECT * FROM eventmembers WHERE eventid = '$EventID' AND username = '$LoginID'";
	$rs = mysqli_query($con, $sql);
	if(mysqli_num_rows($rs) > 0){
		return 1;
	}
	else{
		return 0;
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

	####GET EVENTS LIST####
	if(isset($_POST["GetEventList"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		$i = 2;
		$now = currentDate();
		$MinKarma = "";
		$EventType = "";
		$EventLocation = "";
		$StartTime = "";
		#Check for additional filters set
		$sql = "SELECT * FROM events WHERE starttime > '$now'";
		if(isset($_POST["MinKarma"]) && $_POST["MinKarma"] != ""){
			$MinKarma = mysqli_real_escape_string($con, $_POST["MinKarma"]);
			$sql .= " AND minkarma => '$MinKarma'";
		}
		if(isset($_POST["EventType"]) && $_POST["EventType"] != ""){
			$EventType = mysqli_real_escape_string($con, $_POST["EventType"]);
			$sql .= " AND eventtype = '$EventType'";
		}
		if(isset($_POST["EventLocation"]) && $_POST["EventLocation"] != ""){
			$EventLocation = mysqli_real_escape_string($con, $_POST["EventLocation"]);
			$sql .= " AND location = '$EventLocation'";
		}
		if(isset($_POST["StartTime"]) && $_POST["StartTime"] != ""){
			$StartTime = mysqli_real_escape_string($con, $_POST["StartTime"]);
			$StartTime = date($StartTime, strtotime('+3 hours +2 minutes'));
			$sql .= " AND starttime > '$StartTime'";
		}
		if(isset($_POST["EndTime"]) && $_POST["EndTime"] != ""){
			$EndTime = mysqli_real_escape_string($con, $_POST["EndTime"]);
			$EndTime = date($EndTime, strtotime('+3 hours +2 minutes'));
			$sql .= " AND starttime < '$EndTime'";
		}
		#Query finalized statement
		$result = mysqli_query($con, $sql);
		if($result){
			while(($row = mysqli_fetch_row($result))){
				$EventID = $row[0];
				$EventName = $row[1];
				$EventLocation = $row[8];
				$StartTime = $row[6];
				$ParticipantLimit = $row[3];
				$EventType = $row[4];
				#Get event status
				if(date('Y-m-d H:i:s',strtotime('+24 hours')) < $StartTime){ //seconds will be removed in release
					$EventProtection = 0;
				}
				else if(date('Y-m-d H:i') > $EndTime){//seconds will be removed in release
					$EventProtection = 2;
				}
				else{
					$EventProtection = 1;
				}
				if($EventProtection != 2){
					$rs = mysqli_query($con, "SELECT * FROM eventmembers WHERE username = '$LoginID' AND eventid = '$EventID'");
					if($rs && (mysqli_num_rows($rs) == 0)){
						$rs = mysqli_query($con, "SELECT COUNT(*) as participants FROM eventmembers WHERE eventid = '$EventID'");
						if($rs){
							$participants = mysqli_fetch_row($rs)[0];
						}
						$tmp = array("$i" => array("EventID" => $EventID, "EventName" => $EventName, "EventLocation" => $EventLocation, "StartTime" => $StartTime, "Participants" => $participants, "ParticipantLimit" => $ParticipantLimit, "MinKarma" => $MinKarma, "EventType" => $EventType));
						$package = array_merge($package, $tmp);
						$i++;
					}
				}
			}
			$colnames = array("1" => array("0" => "EventID", "1" => "EventName", "2" => "EventLocation", "3" => "StartTime", "4" => "Participants", "5" => "ParticipantLimit", "6" => "MinKarma", "7" => "EventType"));
			$package = array_merge($colnames, $package);
			sendPackage($con, $package, true, "", "EVENT LIST SENT");
		}
		else{
			queryFailed($con, 2.1);
		}
	}

	####GET EVENT####
	else if(isset($_POST["GetEvent"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["GetEvent"] != "" && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$EventID = mysqli_real_escape_string($con, $_POST["GetEvent"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		$i = 2;
		
		#Get event details
		$sql = "SELECT * FROM events WHERE eventid = '$EventID'";
		$result = mysqli_query($con, $sql);
		if($result){
			$row = mysqli_fetch_row($result);
			$EventName = $row[1];
			$EventDescription = $row[8];
			$EventLocation = $row[7];
			$EventType = $row[4];
			$ParticipantLimit = $row[3];
			$StartTime = $row[5];
			$EndTime = $row[6];
			$Host = $row[2];
			
			$EventParameters = "";
			if($row[9] != ""){
				$EventParameters .= "Min Karma:$row[9]";
			}
			if($row[10] != ""){
				$EventParameters .= "\nMin Age:$row[10]";
			}
			if($row[11] != ""){
				$EventParameters .= "\nSex:$row[11]";
			}
			if($row[12] != ""){
				$EventParameters .= "\nMin Rating:$row[12]";
			}
			if($row[13] != ""){
				$EventParameters .= "\nMax Rating:$row[13]";
			}
			if($row[14] != ""){
				$EventParameters .= "\nRated:$row[14]";
			}
			if($row[15] != ""){
				$EventParameters .= "\nMember of Group:$row[15]";
			}
			if($row[16] != ""){
				$EventParameters .= "\nMax Age:$row[16]";
			}
			
			#Get participant details
			$sql = "SELECT * FROM eventmembers WHERE eventid = '$EventID'";
			$result = mysqli_query($con, $sql);
			if($result){
				while($row = mysqli_fetch_row($result)){
					$username = $row[0];
					$hidden = $row[1];
					$rs = mysqli_query($con, "SELECT name FROM user WHERE username = '$username'");
					$name = mysqli_fetch_row($rs)[0];
					$sql = "SELECT username FROM comments WHERE author = '$LoginID' AND username = '$username' AND eventid = '$EventID'";
					$rs = mysqli_query($con, $sql);
					if(mysqli_num_rows($rs) > 0){
						$Commentable = 0;
					}
					else{
						$Commentable = 1;
					}
					$tmp = array("$i" => array("UserID" => $username, "UserName" => $name, "Commentable" => $Commentable));
					$package = array_merge($package, $tmp);
					$i++;
				}
			}
			else{
				queryFailed($con, 2.2);
			}
			#Get event status
			if(date('Y-m-d H:i',strtotime('+24 hours')) < $StartTime){
				$EventProtection = 0;
			}
			else if(date('Y-m-d H:i') > $EndTime){
				$EventProtection = 2;
			}
			else{
				$EventProtection = 1;
			}
			
			$PartOf = eventPartOf($con, $EventID, $LoginID);
			$participants = $i - 2;
			$Participation = "$participants/$ParticipantLimit";
			$tmp = array("1" => array("EventID" => $EventID, "EventName" => $EventName, "EventLocation" => $EventLocation, "EventType" => $EventType, "EventDescription" => $EventDescription, "StartTime" => $StartTime, "EndTime" => $EndTime, "Host" => $Host, "ParticipantLimit" => $Participation, "EventProtection" => $EventProtection, "EventParameters" => $EventParameters, "PartOf" => $PartOf));
			$package = array_merge($tmp, $package);
			
			sendPackage($con, $package, true, "", "EVENT DETAILS SENT");
		}
		else{
			queryFailed($con, 2.1);
		}
		
	}
	####MY EVENTS####
	else if(isset($_POST["MyEvents"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		$i = 1;
		$GroupMembership = 0;
		
		#Is user a part of a group
		$sql = "SELECT groupid FROM groupmembers WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if(!$result){
			queryFailed($con, 2.1);
		}
		if(mysqli_num_rows($result) > 0){
			$GroupMembership = 1;
		}
		
		#Get all event user is a part of
		$sql = "SELECT eventid FROM eventmembers WHERE username = '$LoginID' AND participation = 0";
		$result = mysqli_query($con, $sql);
		if($result){
			while($row = mysqli_fetch_row($result)){
				$EventID = $row[0];
				#Get event details for each event
				$sql = "SELECT * FROM events WHERE eventid = '$EventID'";
				$rs = mysqli_query($con, $sql);
				if($rs){
					while($row = mysqli_fetch_row($rs)){
						$EventName = $row[1];
						$EventLocation = $row[8];
						$EventDate = $row[6];
						$tmp = array("$i" => array("EventID" => $EventID, "EventName" => $EventName, "EventLocation" => $EventLocation, "EventDate" => $EventDate));
						$package = array_merge($package, $tmp);
						$i++;
					}
					$status = true;
				}
				else{
					queryFailed($con, 2.2);
				}
			}
			#Send all events
			$fpackage = array("0" => array("status" => true, "reason" => "", "GroupMembership" => $GroupMembership));
			$package = array_merge($fpackage, $package);
			packageLog($package);
			file_put_contents($logTxt, "MY EVENTS SENT!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
		else{
			queryFailed($con, 2.0);
		}
	}
	
	####JOIN EVENT####
	else if(isset($_POST["EventJL"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["EventID"]) && $_POST["EventJL"] == 1 && $_POST["LoginID"] != "" && $_POST["LoginPass"] != "" ){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$EventID = mysqli_real_escape_string($con, $_POST["EventID"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		
		#Event exists
		$sql = "SELECT eventid FROM events WHERE eventid = '$EventID'";
		$result = mysqli_query($con, $sql);
		if(($result) && (mysqli_num_rows($result)) > 0){
			#Is user in the event already
			$sql = "SELECT participantlimit FROM events WHERE eventid = '$EventID'";
			$result = mysqli_query($con, $sql);
			if($result){
				$ParticipantLimit = mysqli_fetch_row($result)[0];
			}
			else{
				queryFailed($con, 2.2);
			}
			#Get name of user
			$sql = "SELECT name FROM user WHERE username = '$LoginID'";
			$rs = mysqli_query($con, $sql);
			$Name = mysqli_fetch_row($rs)[0];
			$sql = "SELECT participation FROM eventmembers WHERE eventid = '$EventID' AND username = '$LoginID'";
			$result = mysqli_query($con, $sql);
			#Does user meet the requirements
			if(($result) && (mysqli_num_rows($result) == 0)){
				$sql = "SELECT * FROM eventmembers WHERE eventid = '$EventID'";
				$result = mysqli_query($con, $sql);
				if($result && (mysqli_num_rows($result) < $ParticipantLimit)){
					$sql = "INSERT INTO eventmembers (username, eventid, hidden, participation) VALUES ('$LoginID', '$EventID', '0', '0'); ";
					$sql .= "INSERT INTO eventchats (eventid, username) VALUES ('$EventID', '$LoginID')";
					$result = mysqli_multi_query($con, $sql);
					if($result){
						#Add user to chat
						$chatFile = $chatDir . "Event_$EventID.txt";
						file_put_contents($chatFile, "[" . date('H:i') . "] $Name joined event!\n", FILE_APPEND | LOCK_EX);
						sendPackage($con, $package, true, "", "USER JOINED EVENT");
					}
					else{
						queryFailed($con, 2.3);
					}
				}
				else{
					sendPackage($con, $package, false, "", "EVENT FULL");
				}
			}
			#User already in the event
			else if($result){
				sendPackage($con, $package, false, "7", "USER ALREADY IN EVENT");
			}
			else{
				sendPackage($con, $package, false, "11", "REQUIREMENTS NOT MET");
			}
		}
		else if($result){
			sendPackage($con, $package, false, "8", "EVENT DOESN'T EXIST");
		}
		else{
			queryFailed($con, 2.1);
		}
	}
	
	####LEAVE EVENT####
	else if(isset($_POST["EventJL"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["EventID"]) && $_POST["EventJL"] == 0 && $_POST["LoginID"] != "" && $_POST["LoginPass"] != "" ){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$EventID = mysqli_real_escape_string($con, $_POST["EventID"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		
		$sql = "SELECT participation FROM eventmembers WHERE eventid = '$EventID' AND username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		$num = mysqli_num_rows($result);
		if(($result) && ($num > 0)){
			$sql = "SELECT host FROM events WHERE eventid = '$EventID'";
			$result = mysqli_query($con, $sql);
			$host = mysqli_fetch_row($result)[0];
			if($host == $LoginID){
				$sql = "DELETE FROM events WHERE eventid = '$EventID'";
				$result = mysqli_query($con, $sql);
				if(!$result){
					queryFailed($con, mysqli_error($con));
				}
				sendPackage($con, $package, true, "", "USER LEFT GROUP");
			}
			else{
				$participation = mysqli_fetch_row($result)[0];
				$sql = "SELECT starttime FROM events WHERE eventid = '$EventID'";
				$result = mysqli_query($con, $sql);
				if($result){
					$StartTime = mysqli_fetch_row($result)[0];
					#Remove user from event
					if(($participation == 0) && (date('Y-m-d H:i:s', strtotime("+1 day")) < $StartTime)){
						$sql = "DELETE FROM eventmembers WHERE eventid = '$EventID' AND username = '$LoginID'; ";
						$result = mysqli_query($con, $sql);
						if($result){
							sendPackage($con, $package, true, "", "USER LEFT EVENT");
						}
					}
					#Event is in protected status
					else{
						sendPackage($con, $package, false, 4, "EVENT IS PROTECTED");
					}
				}
				else{
					queryFailed($con, 2.11);
				}
			}
		}
		#User not in the event
		else if($result){
			$Reason = 13;
			$package = array("0" => array("status" => false, "reason" => $Reason));
			packageLog($package);
			file_put_contents("$logTxt", "USER NOT IN EVENT!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			exit();
		}
		#Query failed
		else{
			queryFailed($con, 2.1);
		}
	}
	
	else{ 
		missingParams();
    } 

?>