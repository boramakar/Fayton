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
{
$logDate = date('Y-m-d');
$logTxt = "C:\\wamp\\www\\Fayton\\Logs\\Profile\\Profile_$logDate.txt";
}
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
	foreach($package as $param => $val)
	{
		if(is_array($val)){
			packageLog($val);
		}
		else{
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

function queryFailed($con,$reason)
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

function getAttendance($con, $Username, $EventID)
{
	$Attended = 0;
	$sql = "SELECT attendance FROM comments WHERE username = '$Username' AND eventid = '$EventID'";
	$result = mysqli_query($con, $sql);
	while($row = mysqli_fetch_row($result)){
		$attendance = $row[0];
		if($attendance){
			$Attended++;
		}
		else{
			$Attended--;
		}
	}
	if($Attended == 0){
		return 2;
	}
	else if($Attended > 0){
		return 1;
	}
	else{
		return 0;
	}
}
}
###
####End of function implementation section
###

#######LOGGING
$timestamp = currentDate();
global $logTxt;

$handle = @fopen($logTxt, a);
file_put_contents($logTxt, "NEW TRANSMISSION START\n", FILE_APPEND | LOCK_EX);
foreach ($_POST as $param_name => $param_val) {
	file_put_contents($logTxt, '**$_POST** [' . $timestamp . ']' . " Param: $param_name; Value: $param_val\n", FILE_APPEND | LOCK_EX);
}
#######END OF LOGGING

	####MY PROFILE####
	if(isset($_POST["MyProfile"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		$i = 3;
		$groupIter = 0;
		$eventIter = 0;
		$commentIter = 0;
		
		###Format of the package to be sent
		#0 => Response {"status", "reason"}
		#1 => User {"Name","Premium"}
		#2 => Profile {"AboutMe", "Avatar", "DateOfBirth", "Sex", "SubText", "Karma"}
		#3 => Groups {0 => {"GroupID", "GroupName"}, 1=> , ... n=>}
		#4 => Events {0 => {"EventID", "EventName", "Participation", "EventLocation", "StartTime"}}
		#5 => Comments {0 => {"CommentID", "Author", "CommentText", "EventID"}}
		###
		
		#Get User info //1
		$sql = "SELECT * FROM user WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			$rowUser = mysqli_fetch_row($result);
			$userName = $rowUser[4];
			$Premium = $rowUser[2];
			$userPackage = array("1" => array("Name" => $userName, "Premium" => $Premium));
			$package = array_merge($package, $userPackage);
			mysqli_free_result($result);
		}
		else{
			queryFailed($con, 2.1);
		}
		
		#Get Profile info //2
		$sql = "SELECT * FROM profile WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			$rowProfile = mysqli_fetch_row($result);
			$AboutMe = $rowProfile[1];
			$Avatar = $rowProfile[2];
			$DateOfBirth = $rowProfile[3];
			$Sex = $rowProfile[4];
			$SubText = $rowProfile[5];
			$Karma = $rowProfile[6];
			$profilePackage = array("2" => array("AboutMe" => $AboutMe, "Avatar" => $Avatar, "DateOfBirth" => $DateOfBirth, "Sex" => $Sex,"SubText" => $SubText,"Karma"=> $Karma));
			$package = array_merge($package, $profilePackage);
		}
		else{
			queryFailed($con, 2.2);
		}
		
		#Get Group info //3
		$sql = "SELECT groupid FROM groupmembers WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			$groupPackage = array();
			while(($rowGroup = mysqli_fetch_row($result)) && ($groupIter < 8)){
				$GroupID = $rowGroup[0];
				$sql = "SELECT name FROM groups WHERE groupid = '$GroupID'";
				$rs = mysqli_query($con, $sql);
				if($rs){
					while($rowGroup = mysqli_fetch_row($rs)){
						$GroupName = $rowGroup[0];
						$tmp = array("$i" => array("GroupID" => $GroupID, "GroupName" => $GroupName));
						$groupPackage = array_merge($groupPackage, $tmp);
						$i++;
						$groupIter++;
					}
				}
				else{
					queryFailed($con, 2.31);
				}
			}
			$package = array_merge($package, $groupPackage);
		}
		else{
			queryFailed($con, 2.3);
		}
		
		#Get Event info //4
		$sql = "SELECT * FROM eventmembers WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			$eventPackage = array();
			while($rowEvent = mysqli_fetch_row($result)){
				$EventID = $rowEvent[1];
				$hidden = $rowEvent[2];
				$Participation = $rowEvent[3];
				$sql = "SELECT * FROM events WHERE eventid = '$EventID'";
				$rs = mysqli_query($con, $sql);
				if($rs && !$hidden){
					while(($event = mysqli_fetch_row($rs)) && ($eventIter < 8)){
						$EventName = $event[1];
						$EventLocation = $event[8];
						$StartTime = $event[5];
						$EndTime = $event[6];
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
						if($EventProtection == 2){
							getAttendance($con, $LoginID, $EventID);
							$tmp = array("$i" => array("EventID" => $EventID, "EventName" => $EventName, "Participation"=> $Participation, "EventLocation"=> $EventLocation, "StartTime"=> $StartTime));
							$eventPackage = array_merge($eventPackage, $tmp);
							$i++;
							$eventIter++;
						}
					}
				}
				else{
					queryFailed($con, 2.41);
				}
			}
			$package = array_merge($package, $eventPackage);
		}
		else{
			queryFailed($con, 2.4);
		}
		
		#Get Comment Info//5
		$sql = "SELECT * FROM comments WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			$commentPackage = array();
			while(($rowComment = mysqli_fetch_row($result)) && ($commentIter < 8)){
				$CommentID = $rowComment[0];
				$EventID = $rowComment[3];
				$CommentText = $rowComment[4];
				$CommentDate = $rowComment[5];
				$AuthorID = $rowComment[1];
				$sql = "SELECT name FROM user WHERE username = '$AuthorID'";
				$rs = mysqli_query($con, $sql);
				if($rs){
					$Author = mysqli_fetch_row($rs)[0];
					$tmp = array("$i" => array("CommentID" => $CommentID, "Author" => $Author, "CommentText"=> $CommentText, "EventID"=> $EventID, "CommentDate"=> $CommentDate));
					$commentPackage = array_merge($commentPackage, $tmp);
					$i++;
					$commentIter++;
				}
				else{
					queryFailed($con, 2.51);
				}
			}
			$package = array_merge($package, $commentPackage);
		}
		else{
			queryFailed($con, 2.5);
		}
		
		#All data retrieved
		#Send Package
		$fpackage = array("0" => array("status" => true, "reason" => $Reason, "GroupCount" => $groupIter, "EventCount" => $eventIter, "CommentCount" => $commentIter));
		$package = array_merge($fpackage, $package);
		packageLog($package);
		file_put_contents($logTxt, "PROFILE SENT!\n$EOT", FILE_APPEND | LOCK_EX);
		echo json_encode($package);
		fclose($handle);
		mysqli_close($con);
		exit();
	}
	
	####UPDATE PROFILE####
	else if(isset($_POST["UpdateProfile"], $_POST["LoginID"], $_POST["LoginPass"] , $_POST["Data"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"] != "" && $_POST["UpdateProfile"] != ""){
		##UpdateProfile Values
		#1 : AboutMe
		#2 : Avatar
		#3 : Date of Birth
		#4 : Sex
		#5 : Subtext
		
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$UpdateProfile = mysqli_real_escape_string($con, $_POST["UpdateProfile"]);
		$Data = mysqli_real_escape_string($con, $_POST["Data"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		
		#Update AboutMe
		if($UpdateProfile == 1){
			$ColName = 'aboutme';
		}
		#Update Avatar
		else if($UpdateProfile == 2){
			$ColName = 'avatar';
		}
		/*
		#Update DateOfBirth
		else if($UpdateProfile == 3){
			$ColName = 'dateofbirth';
		}
		#Update Sex
		else if($UpdateProfile == 4){
			$ColName = 'sex';
		}
		*/
		#Update SubText
		else if($UpdateProfile == 5){
			$ColName = 'subtext';
		}
		#Invalid Update Code
		else{
			$package = array();
			sendPackage($con, $package, false, "1", "INVALID UPDATE PARAMETER!");
		}
		
		#Execute query
		$sql = "UPDATE profile SET `$ColName` = '$Data' WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			$package = array("0" => array("status" => true, "reason" => $Reason));
			packageLog($package);
			file_put_contents("$logTxt", "ABOUT ME UPDATED!\n$EOT", FILE_APPEND | LOCK_EX);
			echo json_encode($package);
			fclose($handle);
			exit();
		}
		else{
			queryFailed($con, 2.1);
		}
	}
	
	####GET PROFILE####
	else if(isset($_POST["GetProfile"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["GetProfile"] != "" && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to database
		$con = dbConnect();
		#Get clean variables from POST
		$Username = mysqli_real_escape_string($con, $_POST["GetProfile"]);
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		#Verify User
		verifyUser($con, $LoginID, $LoginPass);
		$package = array();
		$i = 3;
		$groupIter = 0;
		$eventIter = 0;
		$commentIter = 0;
		
		###Format of the package to be sent
		#0 => Response {"status", "reason"}
		#1 => User {"Name","Premium"}
		#2 => Profile {"AboutMe", "Avatar", "DateOfBirth", "Sex", "SubText", "Karma"}
		#3 => Groups {0 => {"GroupID", "GroupName"}, 1=> , ... n=>}
		#4 => Events {0 => {"EventID", "EventName", "Participation", "EventLocation", "StartTime"}}
		#5 => Comments {0 => {"CommentID", "Author", "CommentText", "EventID"}}
		###
		
		#Get User info //1
		$sql = "SELECT * FROM user WHERE username = '$Username'";
		$result = mysqli_query($con, $sql);
		if($result){
			$rowUser = mysqli_fetch_row($result);
			$userName = $rowUser[4];
			$Premium = $rowUser[2];
			$userPackage = array("1" => array("Name" => $userName, "Premium" => $Premium));
			$package = array_merge($package, $userPackage);
			mysqli_free_result($result);
		}
		else{
			queryFailed($con, 2.1);
		}
		
		#Get Profile info //2
		$sql = "SELECT * FROM profile WHERE username = '$Username'";
		$result = mysqli_query($con, $sql);
		if($result){
			$rowProfile = mysqli_fetch_row($result);
			$AboutMe = $rowProfile[1];
			$Avatar = $rowProfile[2];
			$DateOfBirth = $rowProfile[3];
			$Sex = $rowProfile[4];
			$SubText = $rowProfile[5];
			$Karma = $rowProfile[6];
			$profilePackage = array("2" => array("AboutMe" => $AboutMe, "Avatar" => $Avatar, "DateOfBirth" => $DateOfBirth, "Sex" => $Sex,"SubText" => $SubText,"Karma"=> $Karma));
			$package = array_merge($package, $profilePackage);
		}
		else{
			queryFailed($con, 2.2);
		}
		
		#Get Group info //3
		$sql = "SELECT groupid FROM groupmembers WHERE username = '$Username'";
		$result = mysqli_query($con, $sql);
		if($result){
			$groupPackage = array();
			while(($rowGroup = mysqli_fetch_row($result)) && ($groupIter < 8)){
				$GroupID = $rowGroup[0];
				$sql = "SELECT name FROM groups WHERE groupid = '$GroupID'";
				$rs = mysqli_query($con, $sql);
				if($rs){
					while($rowGroup = mysqli_fetch_row($rs)){
						$GroupName = $rowGroup[0];
						$tmp = array("$i" => array("GroupID" => $GroupID, "GroupName" => $GroupName));
						$groupPackage = array_merge($groupPackage, $tmp);
						$i++;
						$groupIter++;
					}
				}
				else{
					queryFailed($con, 2.31);
				}
			}
			$package = array_merge($package, $groupPackage);
		}
		else{
			queryFailed($con, 2.3);
		}
		
		#Get Event info //4
		$sql = "SELECT * FROM eventmembers WHERE username = '$Username'";
		$result = mysqli_query($con, $sql);
		if($result){
			$eventPackage = array();
			while($rowEvent = mysqli_fetch_row($result)){
				$EventID = $rowEvent[1];
				$hidden = $rowEvent[2];
				$sql = "SELECT * FROM events WHERE eventid = '$EventID'";
				$rs = mysqli_query($con, $sql);
				if($rs && !$hidden){
					while(($event = mysqli_fetch_row($rs)) && ($eventIter < 8)){
						$EventName = $event[1];
						$EventLocation = $event[8];
						$StartTime = $event[5];
						$EndTime = $event[6];
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
						if($EventProtection == 2){
							$Participation = getAttendance($con, $Username, $EventID);
							$tmp = array("$i" => array("EventID" => $EventID, "EventName" => $EventName, "Participation"=> $Participation, "EventLocation"=> $EventLocation, "StartTime"=> $StartTime));
							$eventPackage = array_merge($eventPackage, $tmp);
							$i++;
							$eventIter++;
						}
					}
				}
				else{
					queryFailed($con, 2.41);
				}
			}
			$package = array_merge($package, $eventPackage);
		}
		else{
			queryFailed($con, 2.4);
		}
		
		#Get Comment Info//5
		$sql = "SELECT * FROM comments WHERE username = '$Username'";
		$result = mysqli_query($con, $sql);
		if($result){
			$commentPackage = array();
			while(($rowComment = mysqli_fetch_row($result)) && ($commentIter < 8)){
				$CommentID = $rowComment[0];
				$EventID = $rowComment[3];
				$CommentText = $rowComment[4];
				$CommentDate = $rowComment[5];
				$AuthorID = $rowComment[1];
				$sql = "SELECT name FROM user WHERE username = '$AuthorID'";
				$rs = mysqli_query($con, $sql);
				if($rs){
					$Author = mysqli_fetch_row($rs)[0];
					$tmp = array("$i" => array("CommentID" => $CommentID, "Author" => $Author, "CommentText"=> $CommentText, "EventID"=> $EventID, "CommentDate"=> $CommentDate));
					$commentPackage = array_merge($commentPackage, $tmp);
					$i++;
					$commentIter++;
				}
				else{
					queryFailed($con, 2.51);
				}
			}
			$package = array_merge($package, $commentPackage);
		}
		else{
			queryFailed($con, 2.5);
		}
		
		#All data retrieved
		#Send Package
		$fpackage = array("0" => array("status" => true, "reason" => $Reason, "GroupCount" => $groupIter, "EventCount" => $eventIter, "CommentCount" => $commentIter));
		$package = array_merge($fpackage, $package);
		packageLog($package);
		file_put_contents($logTxt, "PROFILE SENT!\n$EOT", FILE_APPEND | LOCK_EX);
		echo json_encode($package);
		fclose($handle);
		mysqli_close($con);
		exit();
	}
	
	####MISSING PARAMETERS####
	else {
		missingParams();
	}
?>