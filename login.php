<?php 
###Database connection info
$dbIP = "localhost";
$dbUsername = "root";
$dbPass = "";
$dbName = "Fayton";
###

###Mail related info
$mailHeader ='From: no-reply@fayton.com' . "\r\n" .
'Reply-To: ' . "\r\n" .
'X-Mailer: PHP/' . phpversion();
	
$welcomeMessage = "Hello!\n\nThere is supposed to be a welcome message here but we are currently working on getting it right! We deeply apologize for still not having a proper message but we also give you a BIG hug for joining the family!\n\nEnjoy your time!\nRegard,\nFayton Development Team";
###

###Universal Sentinels
$EOT = "END OF TRANSMISSION\n**********\n";
$Reason = "";
$logDate = date('Y-m-d');
$logTxt = "C:\\wamp\\www\\Fayton\\Logs\\Login\\Login_$logDate.txt";
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
			file_put_contents($logTxt, '**VERIFED** [' . $timestamp . ']' . " Param: $LoginID; Value: $LoginPass; Pass: $pass\n", FILE_APPEND | LOCK_EX);
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

###
####End of function implementation section
###

#######LOGGING
$timestamp = currentDate();

file_put_contents($logTxt, "NEW TRANSMISSION START\n", FILE_APPEND | LOCK_EX);
foreach ($_POST as $param_name => $param_val) {
	file_put_contents($logTxt, '**$_POST** [' . $timestamp . ']' . " Param: $param_name; Value: $param_val\n", FILE_APPEND | LOCK_EX);
}
#######END OF LOGGING

	####REGISTER####
	#Client sent a valid Register request
	if (isset($_POST["Register"], $_POST["RegisterID"], $_POST["RegisterPass"], $_POST["RegisterName"], $_POST["DateOfBirth"], $_POST["SEX"]) && $_POST["RegisterID"] != "" && $_POST["RegisterPass"] != "" && $_POST["RegisterName"] != "" && $_POST["DateOfBirth"] && $_POST["SEX"]){
		#Connect to Database
		$con = dbConnect();
		#Protection against insertion
		$RegisterName = mysqli_real_escape_string($con, $_POST["RegisterName"]);
		$RegisterID = mysqli_real_escape_string($con, $_POST["RegisterID"]);
		$RegisterPass = mysqli_real_escape_string($con, $_POST["RegisterPass"]);
		$DateOfBirth = mysqli_real_escape_string($con, $_POST["DateOfBirth"]);
		$SEX = mysqli_real_escape_string($con, $_POST["SEX"]);
		
		$Pos = strpos($RegisterID, '@');
		if ($Pos !== false && $Pos != 0 && $Pos == strrpos($RegisterID, '@') && $Pos < strlen($RegisterID)){
			#Check if username(email) is already in the Database
			$sql = "SELECT * FROM user WHERE username = '$RegisterID'";
			$rs = mysqli_query($con, $sql);
			if(($rs) && (mysqli_fetch_array($rs))) {
				$package = array("0" => array("status" => false, "reason" => 6));
				packageLog($package);
				file_put_contents($logTxt, "EMAIL ADDRESS IN USE!\n$EOT", FILE_APPEND | LOCK_EX);
				echo json_encode($package);
				fclose($handle);
				mysqli_close($con);
				exit();
			}
			else {
				#Create Token for user
				do{
				$Token = createToken();
				} while (mysqli_query($con, "Select U.LoginID FROM users U WHERE Token = '$Token'"));
				#Get current date with time
				$currentDate = currentDate();
				#Insert new user into User table
				$sql = "INSERT INTO user (username, token, premium, p_expire, name, t_expire, password) VALUES ('$RegisterID', '$Token', 0, '$currentDate', '$RegisterName', '$currentDate', '$RegisterPass'); ";
				$sql .= "INSERT INTO profile (username, aboutme, dateofbirth, sex, subtext, karma) VALUES ('$RegisterID', 'About ME', '$DateOfBirth', '$SEX', 'Edit Me', 0)";
				$result = mysqli_multi_query($con, $sql);
				$num = mysqli_affected_rows($con);
				if($num){
					#User created successfully
					$package = array("1" => array("token" => $Token));
					sendPackage($con, $package, true, "", "SUCCESSFUL REGISTRATION");
				}
				else{
					#No rows added to Database
					queryFailed($con);
				}
			}
		}
		else{
			#Username is not an email
			$package = array("0" => array("status" => false, "reason" => 1));
			packageLog($package);
			file_put_contents($logTxt, "INVALID EMAIL!\n$EOT", FILE_APPEND | LOCK_EX);
			//echo 'Invalid email address!';
			echo json_encode($package);
			fclose($handle);
			mysqli_close($con);
			exit();
		}
		
	}
	
	####LOGIN####
    #Client sent a valid login request
    else if (isset($_POST["Login"], $_POST["LoginID"], $_POST["LoginPass"]) && $_POST["LoginID"] != "" && $_POST["LoginPass"] != ""){
		#Connect to Database 
		$con = dbConnect();
		#Escape special characters to avoid SQL injection attacks 
		$LoginID = mysqli_real_escape_string($con, $_POST["LoginID"]);
		$LoginPass = mysqli_real_escape_string($con, $_POST["LoginPass"]);
		verifyUser($con, $LoginID, $LoginPass);
		
		do{
		$Token = createToken();
		} while (mysqli_query($con, "Select LoginID FROM users WHERE Token = '$Token'"));
			
		#Update the database for new token and token_expiry
		file_put_contents($logTxt, "LOGIN SUCCESSFUL!\n", FILE_APPEND | LOCK_EX);
		$t_Expiry = tokenExpiry();
		$sql = "UPDATE user SET t_expire = '$t_Expiry' , token = '$Token' WHERE username = '$LoginID'";
		$result = mysqli_query($con, $sql);
		if($result){
			#Send the token to the client with successful login info
			$package = array("1" => array("token" => $Token));
			sendPackage($con, $package, true, "", "TOKEN UPDATED!");
		}
		else{
			#Failed to update the database
			queryFailed($con);
		}			
    }
	else{ 
		missingParams();
    } 
?>