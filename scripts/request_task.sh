#!/bin/bash
cd /var/www/html/analisi_dati/scripts/;
#nohup ./download_data.sh -e "EsperimentoTest" -s "DBMedical" -d "http://193.62.193.80/arrayexpress/files/E-MTAB-3732/E-MTAB-3732.sdrf.txt http://193.62.193.80/arrayexpress/files/E-MTAB-3732/E-MTAB-3732.processed.1.zip/processedMatrix.Aurora.july2015.txt" -t "samples processed"
nohup ./download_data.sh -e $1 -s $2 -d $3 -t "samples processed" >logger.log 2>errors.log &