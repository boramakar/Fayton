<?php
class Logging
{
	private $logTxt;
	
	public function setPath($path)
	{
		$logTxt = $path;
	}
	
	public function logPost($_POST)
	{
		$timestamp = currentDate();
global $logTxt;

$handle = @fopen($logTxt, a);
file_put_contents($logTxt, "NEW TRANSMISSION START\n", FILE_APPEND | LOCK_EX);
foreach ($_POST as $param_name => $param_val) {
	file_put_contents($logTxt, '**$_POST** [' . $timestamp . ']' . " Param: $param_name; Value: $param_val\n", FILE_APPEND | LOCK_EX);
	}
}
?>