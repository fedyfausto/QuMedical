#!/bin/bash
clear;
PATH_ORIENT=/opt/orientdb/bin/console.sh;
PATH_FORFILES_WORK=/mnt/01D05FDBE3FE6140/test/;

function query {
	{
		if [ $# -lt 1 ]  #  Il numero di argomenti passati allo script è corretto?
	then
	/opt/orientdb/bin/console.sh "
		connect remote:localhost/System root root;
		SET ignoreErrors TRUE;
		SET echo FALSE;
		$1;
		DISCONNECT;
		";
	else
		/opt/orientdb/bin/console.sh "
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
	/opt/orientdb/bin/console.sh $(cat $1);
		
	}>/dev/null

} 


function show_time {
    num=$1
    min=0
    hour=0
    day=0
    if((num>59));then
        ((sec=num%60))
        ((num=num/60))
        if((num>59));then
            ((min=num%60))
            ((num=num/60))
            if((num>23));then
                ((hour=num%24))
                ((day=num/24))
            else
                ((hour=num))
            fi
        else
            ((min=num))
        fi
    else
        ((sec=num))
    fi
    echo "$day"d "$hour"h "$min"m "$sec"s;
}



PID=$$;
NUM_ARG=1;
E_ERR_ARG=65
E_NOFILE=66
STATUS=0; # 0 = starting_download, 1 = downloading, 2 downloaded

if curl -s http://localhost:2480/serverStatus 1> /dev/null ; then
    echo "" > /dev/null;
else
    echo "OrientDB is not running!"
    exit 1;
fi


#CREO IL TASK NEL DB

if [ $# -lt 1 ]  #  Il numero di argomenti passati allo script è corretto?
	then
	echo "Non hai passato un URL valido";
	exit $E_ERR_ARG
fi



while getopts "e:s:d:t:" optname
do
	case "$optname" in
		"e")
        EXPERIMENT=$OPTARG;
        ;;
		"s")
        SOURCE=$OPTARG;
        ;;
		"d")
		DOWNLOAD_FILES=($OPTARG);
		;;
		"t")
		TYPES_FILES=($OPTARG);
		;;
esac
done

#echo "scarico # [${#DOWNLOAD_FILES[@]}] file (${DOWNLOAD_FILES[@]}) di [${#TYPES_FILES[@]}] tipo (${TYPES_FILES[@]}) da $SOURCE "


{

	url_first=${DOWNLOAD_FILES[0]};
	fullfile=$(basename "$url_first");
	filename="${fullfile%.*}"
	rm -rf $EXPERIMENT;
	mkdir $EXPERIMENT;
	cd $EXPERIMENT;
	echo $PID > pid;
	echo $STATUS > status;

	

} > /dev/null;

STATUS=1;
echo $STATUS > status;
TIMESTAMP=$(date +%s);
FILE_SIZE=$(curl -sI ${DOWNLOAD_FILES[0]} | grep Content-Length | awk '{print $2}');
#AGGIORNO IL TASK NEL DB
echo "Caricamento del Db...."
query "DROP DATABASE remote:localhost/$EXPERIMENT root root;";
query "DELETE FROM Task WHERE name = '$EXPERIMENT';" >/dev/null;
query "INSERT INTO Task (name,file, status, date,percentage) VALUES ('$EXPERIMENT','$fullfile',$STATUS, $TIMESTAMP, 0);">/dev/null;
PERCENTAGE=0;
BYTE_NOW=0;
echo "DB Caricato!"



for ((i=0; i<${#DOWNLOAD_FILES[@]}; ++i))
do
	url=${DOWNLOAD_FILES[$i]};
	echo "Inizio il download del file $url"
	#echo "Download del file '$url' in corso...";
	{
		fullfile=$(basename "$url");
		FILE_SIZE=$(curl -sI $url | grep Content-Length | awk '{print $2}');
		RET=$(wget -O ${TYPES_FILES[$i]}.data -bqc "$url");
		PID_WGET=$(echo $RET | sed 's/[^0-9]//g');
		echo $PID_WGET > pid_wget;
		CHEKKO=$(ps ax | grep $PID_WGET | grep -v grep | awk '/^/ {if(NR==1){ print $1;}}');
		re='^[0-9]+$';
		query "UPDATE Task SET percentage = $PERCENTAGE, file_name='$url' WHERE name = '$EXPERIMENT';" > /dev/null;
		
		
	} > /dev/null;


		while [[ $CHEKKO =~ $re ]] && [ $CHEKKO -gt 0 ] 
		do

			BYTE_NOW=$(stat -c %s "${TYPES_FILES[$i]}.data");
			PERCENTAGE=$(awk "BEGIN { pc=100*${BYTE_NOW}/${FILE_SIZE}; i=int(pc); print (pc-i<0.5)?i:i+1 }")
			CHEKKO=$(ps ax | grep $PID_WGET | grep -v grep | awk '/^/ {if(NR==1){ print $1;}}');
			#echo "Ancora sta scaricando il file $url... $PERCENTAGE%";
			echo -ne "Download in corso... %$PERCENTAGE "\\r;
			query "UPDATE Task SET percentage = $PERCENTAGE WHERE name = '$EXPERIMENT';" > /dev/null;
			sleep 5;
		done
		
		echo "Ho finito il download del file $url";
		PERCENTAGE=0;
		BYTE_NOW=0;
done
echo "Ho finito tutti i Download!";
STATUS=2;
echo $STATUS > status;
PERCENTAGE=0;
BYTE_NOW=0;
query "UPDATE Task SET status = $STATUS, percentage = $PERCENTAGE WHERE name = '$EXPERIMENT';" > /dev/null;
#inizio il parsing


echo "Creazione del DB $EXPERIMENT in corso...."
query "create database remote:localhost/$EXPERIMENT root root plocal;";
echo "DB $EXPERIMENT Creato!";

echo "Inizio il parsing dei dati....";
echo "Farò il parsing dei file (${TYPES_FILES[@]}).data";


for ((f=0; f<${#TYPES_FILES[@]}; ++f))
do
	if [ ${TYPES_FILES[$f]} == "samples" ]; then

		TS_START=$(date +%s);
		numlines=$(wc -l  < "${TYPES_FILES[$f]}.data");
		echo "Inizio del parsing del file ${TYPES_FILES[$f]}.data [$numlines righe]";
		query "UPDATE Task SET percentage = $PERCENTAGE, file_name='${TYPES_FILES[$f]}.data' WHERE name = '$EXPERIMENT';" > /dev/null;
		OIFS=$IFS;
		IFS=$'\t';
		CLASSE=${TYPES_FILES[$f]^};

		#PRENDO LA PRIMA LINEA
		head -n 1 "${TYPES_FILES[$f]}.data" > line.data;

		#PREPARO LA LISTA DEI FIELD
		FIELDS_FILE=$(head -n 1 ${TYPES_FILES[$f]}.data | sed -r 's/ /_/g' | sed -r 's/\[/_0_/g' | sed -r 's/\]/_1_/g');
		fieldArray=($FIELDS_FILE);

		#HO BISOGNO DI AVERE PER OGNI ELEMENTO UNA COSA DEL GENERE: "CREATE PROPERTY [ELEMENTO] STRING;"
		#PREPARO IL FILE DI QUERY
		FIELDS_FILE=$(head -n 1 ${TYPES_FILES[$f]}.data | sed -r 's/ /_/g' | sed -r 's/\[/_0_/g' | sed -r 's/\]/_1_/g' | awk -v classe=$CLASSE '{for(i=1;i<=NF;i++){printf "CREATE PROPERTY %s.%s STRING; ", classe,$i}}');


		echo "Creazione della Classe $CLASSE in corso...";
		query $EXPERIMENT "DROP CLASS $CLASSE;" > /dev/null;
		query $EXPERIMENT "CREATE CLASS $CLASSE;";
		QUERY_TODO=$FIELDS_FILE;

		query $EXPERIMENT $QUERY_TODO;
		#TOLGO LA RIGA DAL FILE COSI RECUPERO SPAZIO, SI POTREBBE EVITARE AVENDO SPAZIO SUFFICIENTE
		tail -n +2 "${TYPES_FILES[$f]}.data" > bigfile.new && mv -f bigfile.new "${TYPES_FILES[$f]}.data";
		
		NOW=$(date +%s);
		PASS_STRING=$(show_time $(($NOW - $TS_START)));
		echo "Creazione della Classe $CLASSE completata! [$PASS_STRING]";


		#INIZIO A PARSARE LE RIGHE
		numlines=$(wc -l  < "${TYPES_FILES[$f]}.data");
		echo "Inizio del Parsing delle informazioni [$numlines righe]...."

		#PREPARO UNA STRINGA DEL TIPO CAPO1, .... , CAMPO N
		FIELD_STRING=$(echo ${fieldArray[@]} | tr ' ' ','); 
		QUERY_TODO="";

		TS_START=$(date +%s);
		echo "connect remote:localhost/$EXPERIMENT root root;SET ignoreErrors TRUE;SET echo FALSE;">/mnt/01D05FDBE3FE6140/test/queries.sh;
		TS_UPDATE=$(date +%s);
		NOW=0;
		UPDATE_ON=5;
		for (( c=1; c<=$numlines; c++ ))
		do

			$(sed "1q;d" ${TYPES_FILES[$f]}.data | awk -F "\t" -v classe="$CLASSE" -v fields="$FIELD_STRING" 'BEGIN {toprint="INSERT INTO "classe" ("fields") ";} {for(i=1;i<=NF;i++){$i="\""$i"\","}; } END{$NF=substr($NF, 1, length($NF)-1);toprint=toprint"VALUES ("$0");\n"; print toprint;}' >> '/mnt/01D05FDBE3FE6140/test/queries.sh');
			tail -n +2 "${TYPES_FILES[$f]}.data" > bigfile.new && mv -f bigfile.new "${TYPES_FILES[$f]}.data";
		done
		echo "DISCONNECT;">>/mnt/01D05FDBE3FE6140/test/queries.sh;
		NOW=$(date +%s);
		PASS_STRING=$(show_time $(($NOW - $TS_START)));
		echo "Parsing completato [$PASS_STRING]";
		echo "Inizio l'inserimento...."
		/opt/orientdb/bin/console.sh /mnt/01D05FDBE3FE6140/test/queries.sh > /dev/null;
		#query $EXPERIMENT $QUERY_TODO;
		NOW=$(date +%s);
		PASS_STRING=$(show_time $(($NOW - $TS_START)));
		echo "Inserimento Completato! [$PASS_STRING]";
	else

		TS_START=$(date +%s);
		numlines=$(wc -l  < "${TYPES_FILES[$f]}.data");
		echo "Inizio del parsing del file ${TYPES_FILES[$f]}.data [$numlines righe]";
		query "UPDATE Task SET percentage = $PERCENTAGE, file_name='${TYPES_FILES[$f]}.data' WHERE name = '$EXPERIMENT';" > /dev/null;
		OIFS=$IFS;
		IFS=$'\t';
		CLASSE=${TYPES_FILES[$f]^};



		#PRENDO LA PRIMA LINEA
		head -n 1 "${TYPES_FILES[$f]}.data" > line.data;

		#PREPARO LA LISTA DEI FIELD
		FIELDS_FILE=$(head -n 1 ${TYPES_FILES[$f]}.data | sed -r 's/ /_/g' | sed -r 's/\[/_0_/g' | sed -r 's/\]/_1_/g');
		fieldArray=($FIELDS_FILE);

		echo "Creazione della Classe $CLASSE in corso...";
		query $EXPERIMENT "DROP CLASS $CLASSE;" > /dev/null;
		query $EXPERIMENT "CREATE CLASS $CLASSE;";
		tail -n +2 "${TYPES_FILES[$f]}.data" > bigfile.new && mv -f bigfile.new "${TYPES_FILES[$f]}.data";

		NOW=$(date +%s);
		PASS_STRING=$(show_time $(($NOW - $TS_START)));
		echo "Creazione della Classe $CLASSE completata! [$PASS_STRING]";

		#INIZIO A PARSARE LE RIGHE
		numlines=$(wc -l  < "${TYPES_FILES[$f]}.data");
		echo "Inizio del Parsing delle informazioni [$numlines righe]...."

		#PREPARO UNA STRINGA DEL TIPO CAPO1, .... , CAMPO N
		FIELD_STRING=$(echo ${fieldArray[@]} | tr ' ' ','); 
		echo ${FIELD_STRING[@]} > /mnt/01D05FDBE3FE6140/test/fields_processed.data

		TS_START=$(date +%s);
		#PER ESEGUIRE UPDATE DEL TASK OGNI TOT (5 sec)
		echo "connect remote:localhost/$EXPERIMENT root root;SET ignoreErrors TRUE;SET echo FALSE;">/mnt/01D05FDBE3FE6140/test/queries.sh;
		TS_UPDATE=$(date +%s);
		NOW=0;
		UPDATE_ON=5;
		for (( c=1; c<=$numlines; c++ ))
		do

			$(sed "1q;d" ${TYPES_FILES[$f]}.data | awk -F "\t" -v classe="$CLASSE" 'BEGIN {getline fields < "/mnt/01D05FDBE3FE6140/test/fields_processed.data";toprint="INSERT INTO "classe" ("fields") ";} {for(i=1;i<=NF;i++){$i="\""$i"\","}; } END{$NF=substr($NF, 1, length($NF)-1);toprint=toprint"VALUES ("$0");\n"; print toprint;}' >> '/mnt/01D05FDBE3FE6140/test/queries.sh');
			tail -n +2 "${TYPES_FILES[$f]}.data" > bigfile.new && mv -f bigfile.new "${TYPES_FILES[$f]}.data";

		done
		echo "DISCONNECT;">>/mnt/01D05FDBE3FE6140/test/queries.sh;
		NOW=$(date +%s);
		PASS_STRING=$(show_time $(($NOW - $TS_START)));
		echo "Parsing completato [$PASS_STRING]";
		echo "Inizio l'inserimento...."
		/opt/orientdb/bin/console.sh /mnt/01D05FDBE3FE6140/test/queries.sh > /dev/null;
		NOW=$(date +%s);
		PASS_STRING=$(show_time $(($NOW - $TS_START)));
		echo "Inserimento Completato! [$PASS_STRING]";

	fi
done
STATUS=3;
echo $STATUS > status;
PERCENTAGE=100;
query "UPDATE Task SET status = $STATUS, percentage = $PERCENTAGE WHERE name = '$EXPERIMENT';" > /dev/null;
exit;