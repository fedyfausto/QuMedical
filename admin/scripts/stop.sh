#!/bin/bash
function query {
	{
		if [ $# -lt 1 ]  #  Il numero di argomenti passati allo script è corretto?
	then
	/home/binding/orientdb/bin/console.sh "
		connect remote:localhost/System root root;
		SET ignoreErrors TRUE;
		SET echo FALSE;
		$1;
		DISCONNECT;
		";
	else
		/home/binding/orientdb/bin/console.sh "
		connect remote:localhost/$1 root root;
		SET ignoreErrors TRUE;
		SET echo FALSE;
		$2;
		DISCONNECT;
		";
	fi
		
	}>/dev/null

} 

function queryfile {
	{
	/home/binding/orientdb/bin/console.sh $(cat $1);
		
	}>/dev/null

} 
PID=$$;
NUM_ARG=1;

if [ $# -lt 1 ]  #  Il numero di argomenti passati allo script è corretto?
then
  echo "Non hai passato un PID valido";
  exit $E_ERR_ARG;
fi
PID_PROCESS=$(cat "./$1/pid");
kill -9 $PID_PROCESS;
echo "Processo killato";
query "UPDATE Task SET status = -1, percentage = 100 WHERE name = '$1';" > /dev/null;
query "DROP DATABASE $1;" > /dev/null;
rm -rf $1;
