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
$logTxt = "C:\\wamp\\www\\Fayton\\Logs\\Groups\\Groups_$logDate.txt";
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

	####GROUP SEARCH####
	if(isset($_POST["GroupSearch"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"])
	{
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$GroupSearch = mysqli_real_escape_string($con, $_POST["GroupSearch"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		
		if($GroupSearch != ""){
			#Get all groups with GroupSearch keyword in group name
			$sql = "SELECT * FROM groups WHERE name LIKE '%$GroupSearch%'";
		}
		else{
			#Get all groups
			$sql = "SELECT * FROM groups";
		}
		#Clear query from insertions
		$result = mysqli_query($con, $sql);
		if(mysqli_errno($con) > 0){
			echo mysqli_error($con);
		}
		#Initialize key value for row arrays
		$i = 1;
		if($result && mysqli_num_rows($result) > 0){
			#Create package to be sent at the end
			$package = array("0" => array("status" => true, "reason" => "", "SearchParam" => $GroupSearch));
			while($row = mysqli_fetch_row($result))
			{
				#Get necessary variable from the current row
				$GroupID = $row[0];
				$GroupName = $row[2];
				$PartOf = groupPartOf($con, $GroupID, $LoginID);
				#User isn't part of group
				#Get member count of the group
				$sql = "SELECT * FROM groupmembers WHERE groupid = '$GroupID'";
				$count = mysqli_query($con, $sql);
				$count = mysqli_num_rows($count);
				#Create temporary array
				$tmp = array("$i" => array("GroupID" => $GroupID, "GroupName" => $GroupName, "PartOf" => $PartOf, "GroupMemberCount" => $count));
				$package = array_merge($package, $tmp);
				#Increment key value
				$i++;
			}
			#Logging
			packageLog($package);
			file_put_contents($logTxt, "GROUP LIST SENT!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
		else if($result){
			$package = array("0" => array("status" => true, "reason" => "", "SearchParam" => $GroupSearch));
			packageLog($package);
			file_put_contents($logTxt, "NO GROUPS FOUND!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
		else{
			queryFailed($con, 2.1);
		}
	}
	
	####GET GROUP DETAILS####
	else if(isset($_POST["GetGroup"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$GroupID = mysqli_real_escape_string($con, $_POST["GetGroup"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		
		$sql = "SELECT * FROM groups WHERE groupid = '$GroupID'";
		$result = mysqli_query($con, $sql);
		if($result && mysqli_num_rows($result) > 0){
			$row = mysqli_fetch_row($result);
			$GroupName = $row[2];
			$GroupDescription = $row[1];
			$PartOf = groupPartOf($con, $GroupID, $LoginID);
			if($PartOf){
				$sql = "SELECT * FROM groupmembers WHERE username = '$LoginID'";
				$result = mysqli_query($con, $sql);
				$row = mysqli_fetch_row($result);
				$GroupXP = $row[2];
				$GroupRating = $row[3];
			}
			else{
				$GroupXP = false;
				$GroupRating = false;
			}
			$package = array("0" => array("status" => true, "reason" => $Reason), "1" => array("GroupID" => $GroupID, "GroupDescription" => $GroupDescription, "PartOf" => $PartOf, "GroupName" => $GroupName, "XP" => $GroupXP, "RAT" => $GroupRating));
			packageLog($package);
			file_put_contents($logTxt, "GROUP DETAIL SENT!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
		else if($result){
			$Reason = 9;
			$package = array("0" => array("status" => false, "reason" => $Reason));
			#Logging
			packageLog($package);
			file_put_contents($logTxt, "INVALID GROUP ID: $GroupID!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
		else{
			$Reason = 1;
			$package = array("0" => array("status" => true, "reason" => $Reason, "SearchParam" => $groupSearch));
			packageLog($package);
			file_put_contents($logTxt, "QUERY COULDN'T BE EXECUTED!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
	}
	####MY GROUPS####
	else if(isset($_POST["MyGroups"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variable from POST
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		
		$sql = "SELECT * FROM groupmembers WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		#User is in at least 1 group
		if($result && mysqli_num_rows($result) > 0){
			$i = 1;
			$status = true;
			$package = array("0" => array("status" => $status, "reason" => $Reason));
			while($row = mysqli_fetch_row($result)){
				#Get GroupID
				$GroupID = $row[1];
				#Get group name
				$sql = "SELECT * FROM groups WHERE groupid = '$GroupID'";
				$rs = mysqli_query($con, $sql);
				$row = mysqli_fetch_row($rs);
				$GroupName = $row[2];
				#Add group to package
				$tmp = array("$i" => array("GroupID" => $GroupID, "GroupName" => $GroupName));
				$package = array_merge($package, $tmp);
				$i++;
			}
			packageLog($package);
			file_put_contents($logTxt, "GROUP LIST SENT!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
		#User isn't part of any groups
		else if($result){
			$status = true;
			$package = array("0" => array("status" => $status, "reason" => $Reason));
			packageLog($package);
			file_put_contents($logTxt, "EMPTY LIST SENT!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
		else{
			queryFailed($con, 2.0);
		}
	}
	
	####JOIN GROUP####
	else if(isset($_POST["GroupJL"], $_POST["GroupID"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["GroupJL"] == 1 && $_POST["LoginID"] != "" && $_POST["LoginPass"] != "" && $_POST["GroupID"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$GroupID = mysqli_real_escape_string($con, $_POST["GroupID"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		
		#Verify user isn't already in group
		$sql = "SELECT * FROM groupmembers WHERE groupid = '$GroupID' AND username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result && (mysqli_num_rows($result) > 0)){
			sendPackage($con, $package, false, 10, "USER ALREADY IN GROUP");
		}
		
		#Get user details
		$sql = "SELECT * FROM user WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			#Get name of user
			$sql = "SELECT name FROM user WHERE username = '$LoginID'";
			$result = mysqli_query($con, $sql);
			$Name = mysqli_fetch_row($result)[0];
			$chatFile = $chatDir . "Group_$GroupID.txt";
			#Get premium info
			$row = mysqli_fetch_row($result);
			$premium = $row[2];
			$p_expiry = $row[3];
			#User is part of other group(s)
			$sql = "SELECT * FROM groupmembers WHERE username = '$LoginID'";
			$result = mysqli_query($con, $sql);
			if($result && mysqli_num_rows($result) > 0){
				#User has active premium //GroupLimit = 100
				if($premium && $p_expiry < currentDate()){
					if(mysqli_num_rows($result) < 100){
						#Insert user into chat, then group
						$sql = "INSERT INTO groupmembers (username, groupid, xp, rating) VALUES ('$LoginID', '$GroupID', 0, 1400); ";
						$sql .= "INSERT INTO groupchats (groupid, username) VALUES ('$GroupID', '$LoginID')";
						$result = mysqli_multi_query($con, $sql);
						if($result){
							
							#Update chat file
							file_put_contents($chatFile, "[" . date('H:i') . "] $Name joined group!\n", FILE_APPEND | LOCK_EX);
							$package = array("1" => array("GroupID" => $GroupID, "XP" => 0, "RAT" => 1400));
							sendPackage($con, $package, true, "", "USER JOINED GROUP");
						}
						#Insert Failed
						else{
							queryFailed($con, 2.1);
						}
					}
					#Max groups joined
					else{
						sendPackage($con, $package, false, "3", "GROUP LIMIT REACHED");
					}
				}
				#User doesn't have active premium //GroupLimit = 3
				else{
					if(mysqli_num_rows($result) < 3){
						#Insert user into chat, then group
						$sql = "INSERT INTO groupmembers (username, groupid, xp, rating) VALUES ('$LoginID', '$GroupID', 0, 1400); ";
						$sql .= "INSERT INTO groupchats (groupid, username) VALUES ('$GroupID', '$LoginID')";
						$result = mysqli_multi_query($con, $sql);
						if($result){
							$package = array("1" => array("GroupID" => $GroupID, "XP" => 0, "RAT" => 1400));
							sendPackage($con, $package, true, "", "USER JOINED GROUP");
						}
						#Insert failed
						else{
							queryFailed($con, 2.2);
						}
					}
					#Max groups joined
					else{
						sendPackage($con, $package, false, "3", "GROUP LIMIT REACHED");
					}
				}
			}
			#User has no previously joined groups
			else if($result){
				#Insert user into requested group
				$sql = "INSERT INTO groupmembers (username, groupid, xp, rating) VALUES ('$LoginID', '$GroupID', 0, 1400); ";
				$sql .= "INSERT INTO groupchats (groupid, username) VALUES ('$GroupID', '$LoginID')";
				$result = mysqli_multi_query($con, $sql);
				if($result){
						$package = array("1" => array("GroupID" => $GroupID, "XP" => 0, "RAT" => 1400));
						sendPackage($con, $package, true, "", "USER JOINED GROUP");
				}
				else{
					queryFailed($con, 2.3);
				}
			}
			else{
				queryFailed($con, 2.31);
			}
		}
		else{
			queryFailed($con, 2.4);
		}
		
	}
	
	####LEAVE GROUP####
	else if(isset($_POST["GroupJL"], $_POST["LoginID"], $_POST["LoginPass"], $_POST["GroupID"]) && $_POST["GroupJL"] == 0 && $_POST["LoginID"] != "" && $_POST["LoginPass"] != "" && $_POST["GroupID"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variable from POST
		$GroupID = mysqli_real_escape_string($con, $_POST["GroupID"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		
		$sql = "DELETE FROM groupmembers WHERE username = '$LoginID' AND groupid = '$GroupID'; ";
		$sql .= "DELETE FROM groupchats WHERE username = '$LoginID' AND groupid = '$GroupID'";
		$result = mysqli_multi_query($con, $sql);
		if($result && (mysqli_affected_rows($con) > 0)){
			sendPackage($con, $package, true, "", "USER LEFT GROUP");
		}
		else if($result){
			sendPackage($con, $package, false, "4", "USER NOT PART OF GROUP");
		}
		else{
			queryFailed($con, 2.1);
		}
	}
	
	####GET LEADERBOARD####
	else if(isset($_POST["Leaderboard"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["Leaderboard"] != "" && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$GroupID = mysqli_real_escape_string($con, $_POST["Leaderboard"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$CommentDate = currentDate();
		$package = array();
		$i = 0;
		
		$sql = "SELECT username, rating FROM groupmembers WHERE groupid = '$GroupID' ORDER BY rating DESC";
		$result = mysqli_query($con, $sql);
		if($result){
			while(($row = mysqli_fetch_row($result)) && ($i < 10)){
				$Username = $row[0];
				$Rating = $row[1];
				$sql = "SELECT name FROM user WHERE username = '$Username'";
				$res = mysqli_query($con, $sql);
				if($res){
					$Name = mysqli_fetch_row($res)[0];
					$i++;
					$tmp = array("$i" => array("Name" => $Name, "Rating" => $Rating));
					$package = array_merge($package, $tmp);
				}
				else{
					queryFailed($con, 2.1);
				}
			}
			sendPackage($con, $package, true, "", "LEADERBOARD SENT");
		}
		else{
			queryFailed($con, 2.2);
		}
	}
	
	else{
		missingParams();
	}

?>