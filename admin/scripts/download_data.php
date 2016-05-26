<?php
/*
ARGOMENTI: 
1) NOME ESPERIMENTO
2) LINKS DATA
3) TIPO DI DATO
*/
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once "./vendor/autoload.php";
use PhpOrient\PhpOrient;
use PhpOrient\Protocols\Binary\Data\ID;
use PhpOrient\Protocols\Binary\Data\Record;
use PhpOrient\Protocols\Common\Constants;

$domains_permit = ['localhost'];


$PATH_ORIENT="/home/binding/orientdb/bin";
$PID=getmypid();
$ARGS=$argv;
$CURRENT_DIR=getcwd();
$NAME_EXPERIMENT=$ARGS[1];
$DATA_LINKS=explode(' ',$ARGS[2]);
$DATA_TYPES=explode(' ',$ARGS[3]);
$CURRENT_PERCENT=0;
$CURRENT_STATUS=0;
$cronometro = new Cronometro();
// 0 = starting_download, 1 = downloading, 2 parsing, 3 finished, -1 aborted





if(is_null($NAME_EXPERIMENT) || $NAME_EXPERIMENT==""){
	trace("Errore - Nome dell'esperimento mancante");
	exit(1);
}
if(count($DATA_LINKS)!=count($DATA_TYPES)){
	trace("Errore - Numero di Link e di tipi diverso");
	exit(1);
}


$client_db_system = new ClientDB();
$client_db_system->openDB('System');
$client_db_system->queryDB("DELETE FROM Task WHERE name = '$NAME_EXPERIMENT'");
$client_db_system->queryDB("INSERT INTO Task (name,file, status, date,percentage) VALUES ('$NAME_EXPERIMENT','$DATA_TYPES[$i]',$CURRENT_STATUS, ".time().", 0)");

//PREPARO LA CARTELLA
if (is_dir($CURRENT_DIR."/".$NAME_EXPERIMENT)) {
	delDir($CURRENT_DIR."/".$NAME_EXPERIMENT);
}

//cambio cartella
mkdir($CURRENT_DIR."/".$NAME_EXPERIMENT, 0765);
chdir($CURRENT_DIR."/".$NAME_EXPERIMENT);
$CURRENT_DIR=getcwd();

//salvo il mio pid

file_put_contents("pid",$PID);


trace("Inizio il download dei file");
$cronometro->start();
for($i = 0; $i < count($DATA_LINKS); ++$i) {
	$client_db_system->queryDB("UPDATE Task SET percentage = 100, file_name='$DATA_TYPES[$i].data' WHERE name = '$NAME_EXPERIMENT';");
	trace("Download del file $DATA_TYPES[$i].data:");
	downloadFile($DATA_LINKS[$i],$DATA_TYPES[$i].".data");

}
trace("Ho finito il download dei file [{$cronometro->stop()}]");

/* ------------------------*/

$CURRENT_PERCENT=0;
$CURRENT_STATUS=1;
$client_db_system->queryDB("UPDATE Task SET status = $CURRENT_STATUS,percentage = 0 WHERE name = '$NAME_EXPERIMENT';");
trace("Inizio a creare il Database");
$cronometro->start();

$client_db = new ClientDB();
//controllo se esiste il DB, in caso lo elimino (si può ovviare)
if($client_db->existDB($NAME_EXPERIMENT)){
	trace("ATTENZIONE - Database già esistente. Procedo con l'eliminazione");
	$client_db->deleteDB($NAME_EXPERIMENT);
}
$client_db->createDB($NAME_EXPERIMENT);

if(!$client_db->existDB($NAME_EXPERIMENT)){
	trace("ERRORE - Database non creato.");
	exit(1);
}

$client_db->openDB($NAME_EXPERIMENT);

trace("Database creato [{$cronometro->stop()}]");
trace("Inizio il parsing dei file");
$cronometro->start();
for($i = 0; $i < count($DATA_TYPES); ++$i) {
	$client_db_system->queryDB("UPDATE Task SET percentage = 0, file_name='$DATA_TYPES[$i].data' WHERE name = '$NAME_EXPERIMENT';");
	$FILE_LINES = countLines("$DATA_TYPES[$i].data");
	trace("Inizio il parsing del file $DATA_TYPES[$i].data [$FILE_LINES righe]");
	if($DATA_TYPES[$i]=="samples"){

		/* creo la classe*/
		$CLASS_NAME=ucfirst($DATA_TYPES[$i]);
		trace("Creazione della classe $CLASS_NAME in corso...");
		$cronometro_local = new Cronometro();
		$cronometro_local->start();
		/*INIZIO IL PARSING */
		$file = new SplFileObject("$DATA_TYPES[$i].data");


		/*PRENDO LA PRIMA RIGA*/
		$file->seek(0);  
		$FIELD_ARRAY= explode("\t", 
			preg_replace('/[\]]/', '_1_',
				preg_replace('/[\[]/', '_0_',
					preg_replace('/[ ]/', '_', $file->current()))));
		$FIELD_STRING=preg_replace('/[\]]/', '_1_',
			preg_replace('/[\[]/', '_0_',
				preg_replace('/[ ]/', '_', 
					preg_replace('/[\t]/', ',', $file->current()))));
		/*Ho perparato la prima riga quindi creo la classe*/

		try {
			$client_db->queryDB("create class $CLASS_NAME");
		}
		catch(Exception $e){
			$client_db->queryDB("DROP class $CLASS_NAME");
			$client_db->queryDB("create class $CLASS_NAME");
		}


		foreach ($FIELD_ARRAY as $key => $propriety) {
			$client_db->queryDB( "CREATE PROPERTY $CLASS_NAME.$propriety STRING");
		}

		trace("Creazione della classe $CLASS_NAME completata [{$cronometro_local->stop()}]");

		trace("Inizio del parsing delle informazioni");
		$cronometro_local->start();
		$file_quieries = fopen('queries.sh', 'w');
		fwrite($file_quieries,"
			connect remote:localhost/$NAME_EXPERIMENT root root;
			SET ignoreErrors TRUE;
			SET echo FALSE;");

		for($i = 1; $i <= $FILE_LINES; $i++) {
			$file->seek($i);
			$VALUES='"'.implode('","', explode("\t",$file->current())).'"';
			$STRING = "INSERT INTO $CLASS_NAME ($FIELD_STRING) VALUES ($VALUES);";
			fwrite_stream($file_quieries,$STRING);
			unset($VALUES);
			unset($STRING);
			$perc = floor(min(100,($i / $FILE_LINES)*100));
			$CURRENT_PERCENT=$perc;
			traceline("Parsing in corso... $perc% [$i]");
			if($cronometro_local->passed(10)){
				$client_db_system->queryDB("UPDATE Task SET percentage = $perc WHERE name = '$NAME_EXPERIMENT';");
			}
			break;
		}
		fwrite($file_quieries,"DISCONNECT;");
		fclose($file_quieries);
		trace("Parsing completato [{$cronometro_local->stop()}]");
		$cronometro_local->start();
		trace("Inizio inserimento dati nel Database - attendere prego\r");
		exec("$PATH_ORIENT/console.sh $CURRENT_DIR/queries.sh");
		trace("Inserimento nel Database completato [{$cronometro_local->stop()}]");

	}
}


$client_db->closeDB();
$client_db_system->closeDB();

/*END*/





function traceline($string){
	echo $string."\r";
}

function trace($string){
	echo date('[d-m-Y H:i:s]')." ".$string.PHP_EOL;
}

/* FUNCTIONS  */
function secondsToTime($s){
	$h = floor($s / 3600);
	$s -= $h * 3600;
	$m = floor($s / 60);
	$s -= $m * 60;
	return $h.':'.sprintf('%02d', $m).':'.sprintf('%02d', $s);
}


function fwrite_stream($fp, $string) {
    for ($written = 0; $written < strlen($string); $written += $fwrite) {
        $fwrite = fwrite($fp, substr($string, $written));
        if ($fwrite === false) {
            return $written;
        }
    }
    return $written;
}

function downloadFile($url, $path){
	global $CURRENT_PERCENT;
	$newfname = $path;
	$dim_file_online = curl_get_file_size($url);
	$file = fopen ($url, 'rb');
	$current_byte=0;
	if ($file) {
		$newf = fopen ($newfname, 'wb');
		if ($newf) {
			while(!feof($file)) {
				$buffer = fread($file, 1024 * 8);
				fwrite($newf, $buffer, 1024 * 8);
				$current_byte += 1024 * 8;
				$perc = floor(min(100,($current_byte / $dim_file_online)*100));
				$CURRENT_PERCENT=$perc;
				//traceline("Download in corso... $perc%");
				traceline("Download in corso... $perc%");
				
			}
		}
	}
	if ($file) {
		fclose($file);
	}
	if ($newf) {
		fclose($newf);
	}
}


function curl_get_file_size( $url ) {

  // Assume failure.
	$result = -1;

	$curl = curl_init( $url );

  // Issue a HEAD request and follow any redirects.
	curl_setopt( $curl, CURLOPT_NOBODY, true );
	curl_setopt( $curl, CURLOPT_HEADER, true );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );

	$data = curl_exec( $curl );
	curl_close( $curl );

	if( $data ) {
		$content_length = "unknown";
		$status = "unknown";

		if( preg_match( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches ) ) {
			$status = (int)$matches[1];
		}

		if( preg_match( "/Content-Length: (\d+)/", $data, $matches ) ) {
			$content_length = (int)$matches[1];
		}

    // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
		if( $status == 200 || ($status > 300 && $status <= 308) ) {
			$result = $content_length;
		}
	}

	return $result;
}


function delDir($dir) { 
	$files = array_diff(scandir($dir), array('.','..')); 
	foreach ($files as $file) { 
		(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
	} 
	return rmdir($dir); 
} 

function countLines($file){
	$count = 0;
	$fp = fopen( $file, 'r');

	while(!feof( $fp)) {
		fgets($fp);
		$count++;
	}

	fclose( $fp);
	return $count;
}


class Cronometro {
	public $time = 0;
	private $passed_time=0;   

	function start() { 
		$this->time=time();
		$this->passed_time=$this->time;
	}

	function stop(){
		return $this->secondsToTime((time() - $this->time));
	}
	function passed($sec){
		if((time() - $this->passed_time)>=$sec){
			$this->passed_time = time();
			return true;
		}
		else{
			return false;
		}
	}

	function secondsToTime($s){
		$h = floor($s / 3600);
		$s -= $h * 3600;
		$m = floor($s / 60);
		$s -= $m * 60;
		return $h.':'.sprintf('%02d', $m).':'.sprintf('%02d', $s);
	}
}


class ClientDB {
	public $client;
	public $db_opened=false;
	public $db="";

	public function __construct() {
		$this->client = new PhpOrient();
		$this->client->configure( array(
			'username' => 'root',
			'password' => 'root',
			'hostname' => 'localhost',
			'port'     => 2424,
			));
		$this->client->connect();
	}

	public function connect(){
		$this->client = new PhpOrient();
		$this->client->configure( array(
			'username' => 'root',
			'password' => 'root',
			'hostname' => 'localhost',
			'port'     => 2424,
			));
		$this->client->connect();
		$this->db_opened=false;
	}

	public function listDB(){
		return $this->client->dbList();
	}
	public function existDB($db){
		return $this->client->dbExists($db);
	}

	public function openDB($db){
		if($this->client->DBExists($db)){
			$this->client->dbOpen( $db, 'admin', 'admin' );
			$this->db_opened=true;
			$this->db=$db;
		}
	}

	public function closeDB(){
		if($this->db_opened && $this->db!=""){
			$this->client->dBClose($this->db);
			$this->db_opened=false;
			$this->db="";
		}
	}

	public function deleteDB($db){
		$this->client->dbDrop($db);
		$this->db_opened=false;
		$this->db="";
		$this->connect();
	}

	public function createDB($db){
		$this->client->dbCreate($db);
	}

	public function queryDB($query){
		if($this->db_opened){
			return $this->client->command($query);
		}
		else return false;
	}

}

?>
