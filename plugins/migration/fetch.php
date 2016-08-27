<?php
/**
 * Meta Info
 * FILE_NAME: fetch.php
 * LABEL: Fetch a remote Wordpress Backup for restore
 * LINK_MAIN: /migration/fetch
 *
 */

respond('POST', '/migration/fetch', 'migration_fetch');

function migration_fetch(TsRequest $request, TsResponse $response)
{

    $response->data->title = "Fetch Remote Wordpress Backup";

    if (isset($request->fetch_url)) {

        $fetchUrl = $request->fetch_url;
        $fetchPath = TS_ABSPATH.$request->fetch_path;

        debug_log("Starting Backup Fetch");

        // Starts with 0 to count
        if(is_file($fetchPath.'backup.info')) {
            $backup = file_get_contents($fetchPath . 'backup.info');
        } else {
            if(!is_dir($fetchPath)) {
                mkdir($fetchPath,0755,true);
            }
            downloadFile($fetchUrl.'backup.info',$fetchPath.'backup.info');
            $backup = file_get_contents($fetchPath . 'backup.info');
        }

        if(!is_file($fetchPath.'backup.info')){
            $response->flash("Error reading : ".$fetchPath.'backup.info', 'danger');
            $response->sendDataJson();
            return;
        }

        $fetchPart = 0;
        if(isset($request->fetch_part)) {
            $fetchPart = $request->fetch_part;
        }

        $files = explode("\n",$backup);

        if(isset($files[$fetchPart]) && !empty(trim($files[$fetchPart]))) {
            list($fetchFile,$size) = explode(",",$files[$fetchPart],2);

            debug_log("Downloading file : ".$fetchUrl.$fetchFile);

            downloadFile($fetchUrl.$fetchFile,$fetchPath.$fetchFile);

            if(!is_file($fetchPath.$fetchFile)){
                debug_log("Error downloading : ".$fetchUrl.$fetchFile);
                $response->flash("Error downloading : ".$fetchUrl.$fetchFile, 'danger');
                $response->sendDataJson();
                return;
            } else {
                debug_log("Download Successful to : ".$fetchPath.$fetchFile."( ".filesize($fetchPath.$fetchFile)." )");
            }

            $response->data->form = true;
            $response->data->formData = array(
                array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
                array('name'  => 'fetch_url', 'type'  => 'hidden', 'value' => $request->fetch_url),
                array('name'  => 'fetch_path', 'type'  => 'hidden', 'value' => $request->fetch_path),
                array('name'  => 'fetch_part', 'type'  => 'hidden', 'value' => $fetchPart + 1)
            );
            $response->data->simpleData = "Downloading : ".$fetchUrl.$fetchFile;
            $response->data->formSubmit = true;
        } else {
            // Were done
            debug_log("Download Complete");
            $response->data->simpleData = "Download Complete";
        }


    } else if (isset($request->backup_url)) {

        $backup_url = $request->backup_url;
        $backup_path = TS_ABSPATH.$request->backup_path;

        $backup = downloadFile($backup_url.'backup.info');

        $response->data->simpleData = "Checking Backup - ";

        if($backup && strpos($backup,'b.sql,')) {

            $totalFiles = 0;
            $totalSize = 0;

            $files = explode("\n",$backup);
            foreach($files as $file) {
                list($bfile,$bsize) = explode(',',$file,2);
                $totalFiles += 1;
                $totalSize += $bsize;
            }

            $response->data->form = true;
            $response->data->simpleData = sprintf("Backup Found<br />Files : %d<br />Size : %.2f MB",$totalFiles, $totalSize / (1024*1024) );
            $response->data->formData = array(
                array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
                array('name'  => 'fetch_url', 'type'  => 'hidden', 'value' => $request->backup_url),
                array('name'  => 'fetch_path', 'type'  => 'hidden', 'value' => $request->backup_path),
                array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Fetch Backup')
            );
            $response->sendDataJson();

        } else {

            $response->data->simpleData = "Backup Not Found at : ".$backup_url;

        }

        // Error getting backup information

    } else {
        $response->data->simpleData = "Enter path for backup directory.";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
            array('name'  => 'backup_url', 'type'  => 'text','label'=>'Remote Backups url','value'=>''),
            array('name'  => 'backup_path', 'label' => 'Backup Path', 'type'  => 'text','hint'=>'Backup Will be stored in this directory','value' => 'wp-ts/' . TS_SECRET . '/backups/'.date('Y-m-d',time()).'/'),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Check Backup')
        );
        $response->sendDataJson();
    }
    $response->sendDataJson();



}