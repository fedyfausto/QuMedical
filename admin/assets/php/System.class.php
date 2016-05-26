<?php 

require "config.php";


class System{

	public function __construct() {


	}

	public static function getMemoryInfo(){
		exec('free -bo', $out);
		preg_match_all('/\s+([0-9]+)/', $out[1], $matches);
		list($total, $used, $free, $shared, $buffers, $cached) = $matches[1];
		$toret;
		$toret['total'] = $total;
		$toret['used'] = $used;
		$toret['free'] = $free;
		$toret['shared'] = $shared;
		$toret['buffer'] = $buffers;
		$toret['cached'] = $cached;

		return $toret;
	}

	public static function getMemoryInfoConverted(){
		exec('free -bo', $out);
		preg_match_all('/\s+([0-9]+)/', $out[1], $matches);
		list($total, $used, $free, $shared, $buffers, $cached) = $matches[1];
		$toret;
		$toret['total'] = System::convertMemory($total);
		$toret['used'] = System::convertMemory($used);
		$toret['free'] = System::convertMemory($free);
		$toret['shared'] = System::convertMemory($shared);
		$toret['buffer'] = System::convertMemory($buffers);
		$toret['cached'] = System::convertMemory($cached);


		$toret['perc_used'] = round((($used-$cached-$buffers)/$total)*100,0);

		$toret['perc_free'] = round(($free/$total)*100,0);

		$toret['perc_cached'] = round(($cached/$total)*100,0);

		$toret['perc_buffer'] = round(($buffers/$total)*100,0);


		return $toret;
	}

	public static function convertMemory($size)
	{
		$unit=array('b','kb','mb','gb','tb','pb');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	}


	public static function getNetDeviceList(){

		exec("ls /sys/class/net/",$out);
		return $out;
	}


	public static function getBytesReceived($device=null){
		if(is_null($device))
			$device="*";

		exec("cat /sys/class/net/$device/statistics/rx_bytes",$toret);

		$valret=0;
		if(is_array($toret)){
			foreach($toret as $val){
				$valret+=$val;
			}
		}
		else
			$valret = $toret;
		return $valret;
	}

	public static function getBytesTrasmitted($device=null){
		if(is_null($device))
			$device="*";

		exec("cat /sys/class/net/$device/statistics/tx_bytes",$toret);
		$valret=0;
		if(is_array($toret)){
			foreach($toret as $val){
				$valret+=$val;
			}
		}
		else
			$valret = $toret;
		return $valret;
	}

	public static function getServerLoad(){
		if (is_readable("/proc/stat")){
			// Collect 2 samples - each with 1 second period
			// See: https://de.wikipedia.org/wiki/Load#Der_Load_Average_auf_Unix-Systemen
			$statData1 = _getServerLoadLinuxData();
			sleep(1);
			$statData2 = _getServerLoadLinuxData();

			if((!is_null($statData1)) &&(!is_null($statData2)))
			{
				// Get difference
				$statData2[0] -= $statData1[0];
				$statData2[1] -= $statData1[1];
				$statData2[2] -= $statData1[2];
				$statData2[3] -= $statData1[3];

				// Sum up the 4 values for User, Nice, System and Idle and calculate
				// the percentage of idle time (which is part of the 4 values!)
				$cpuTime = $statData2[0] + $statData2[1] + $statData2[2] + $statData2[3];

				// Invert percentage to get CPU time, not idle time
				$load = 100 - ($statData2[3] * 100 / $cpuTime);
				return $load;
			}
		}
	}
	
	public static function getTotalSpace($path="/"){
		return disk_total_space($path);
	}
	public static function getFreeSpace($path="/"){
		return disk_free_space($path);
	}
}




function NumberWithCommas($in){
	return number_format($in);
}
function  WriteToStdOut($text){
	$stdout = fopen('php://stdout','w') or die($php_errormsg);
	fputs($stdout, "\n" . $text);
}
function sys_getloadavg_hack() { 
    $str = substr(strrchr(shell_exec("uptime"),":"),1); 
    $avs = array_map("trim",explode(",",$str)); 

    return $avs; 
} 
function _getServerLoadLinuxData(){
	if (is_readable("/proc/stat"))
	{
		$stats = @file_get_contents("/proc/stat");

		if ($stats !== false)
		{
			// Remove double spaces to make it easier to extract values with explode()
			$stats = preg_replace("/[[:blank:]]+/", " ", $stats);

			// Separate lines
			$stats = str_replace(array("\r\n", "\n\r", "\r"), "\n", $stats);
			$stats = explode("\n", $stats);

			// Separate values and find line for main CPU load
			foreach ($stats as $statLine)
			{
				$statLineData = explode(" ", trim($statLine));

				// Found!
				if
					(
					(count($statLineData) >= 5) &&
					($statLineData[0] == "cpu")
				)
				{
					return array(
						$statLineData[1],
						$statLineData[2],
						$statLineData[3],
						$statLineData[4],
					);
				}
			}
		}
	}

	return null;
}
?>