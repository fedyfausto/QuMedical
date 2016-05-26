<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once "config.php";
require_once "System.class.php";
require_once "Process.class.php";
require_once "vendor/autoload.php";
use PhpOrient\PhpOrient;
use PhpOrient\Protocols\Binary\Data\ID;

$domains_permit = ['localhost'];



if(isset($_POST['action']) && (in_array($_SERVER['HTTP_HOST'],$domains_permit))){

	$func = $_POST['action'];
	switch($func){
		case 'taskList':
			echo json_encode((object) array('status' => 200,"data" =>taskList()));
			break;

		case 'deleteTask':
			echo json_encode((object) array('status' => 200,"data" =>deleteTask($_POST['rid'])));
			break;
		case 'abortTask':
			echo json_encode((object) array('status' => 200,"data" =>abortTask($_POST['rid'])));
			break;

		case 'countTask':
			echo json_encode((object) array('status' => 200,"data" =>countTask($_POST['status'])));
			break;

		case 'ramUsage':
			echo json_encode((object) array('status' => 200,"data" =>System::getMemoryInfo()));
			break;

		case 'netInterfaces':

			echo json_encode((object) array('status' => 200,"data" =>System::getNetDeviceList()));
			break;

		case 'getBandwidth':

			if(isset($_POST['int']) && !is_null($_POST['int']) && $_POST['int']!=""){
				$interface=$_POST['int'];
				if(isset($_POST['int']) && !is_null($_POST['int'])){
					$interface=$_POST['int'];
				}
				$obj = (object) array('download' => System::getBytesReceived($interface),"upload" =>System::getBytesTrasmitted($interface));
				echo json_encode((object) array('status' => 200,"data" =>$obj));
			}
			else{
				$devices = System::getNetDeviceList();
				$arr = [];
				foreach ($devices as $key => $objc){
					$arr[$objc] = (object) array('download' => System::getBytesReceived($objc),"upload" =>System::getBytesTrasmitted($objc));
				}
				echo json_encode((object) array('status' => 200,"data" =>$arr));
			}
			break;

		case 'getServerLoad':
			echo json_encode((object) array('status' => 200,"data" =>System::getServerLoad()));

			break;

		case 'getDiskSpace':
			echo json_encode((object) array('status' => 200,"data" =>(object) array('total' => System::getTotalSpace(),"free" =>System::getFreeSpace(), "used"=>(System::getTotalSpace()-System::getFreeSpace()))));

			break;
		default:
			echo json_encode((object) array('status' => 404));
			break;
	}

}
else{
	echo json_encode((object) array('status' => 404));
}
exit(0);


function connectDB(){
	$client = new PhpOrient();
	$client->configure( array(
		'username' => 'root',
		'password' => 'root',
		'hostname' => 'localhost',
		'port'     => 2424,
	));

	$client->connect();
	$client->dbOpen( 'System', 'admin', 'admin' );
	return $client;
}
function taskList(){

	$client = connectDB();
	return $client->query( 'SELECT * FROM Task' );

}
function startTask($files){

	exec ( PATH_SCRIPTS . "request_task.sh $files",$out);

	return true;


}
function abortTask($index){

	$client = connectDB();
	$record = $client->recordLoad( new ID( $index ) )[0];
	$name = $record->getOData()['name'];
	exec (PATH_SCRIPTS . "request_stop.sh $name");
	return $record->getOData();

}
function deleteTask($index){

	$client = connectDB();
	$client->recordDelete( new ID( $index) );
	return true;

}
function countTask($status){
	$client = connectDB();
	$record = $client->query( "SELECT COUNT(*) FROM Task WHERE status ='$status''" )[0];
	return $record->getOData()['COUNT'];

}

?>