<?php
/**
 * Meta Info
 * FILE_NAME: migration_fetch.php
 * LABEL: Fetch a remote Wordpress Backup for restore
 * LINK_MAIN: /migration/migration_fetch
 *
 */

respond('POST', '/migration/migration_fetch', 'migration_migration_fetch');

function migration_migration_fetch(TsRequest $request, TsResponse $response)
{

    $response->data->title = "Fetch Remote Wordpress Backup";

    if (isset($request->backup_fetch)) {

        // Fetch Database


    } else if (isset($request->backup_url)) {

        $backup_url = $request->backup_url;
        $backup_path = TS_ABSPATH.$request->backup_path;

        $backup = downloadFile($backup_url.'backup.txt');

        $response->data->simpleData = "Checking Backup - ";

        if($backup) {

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
                array('name'  => 'backup_url', 'type'  => 'hidden', 'value' => $request->backup_url),
                array('name'  => 'backup_fetch', 'type'  => 'hidden', 'value' => $request->backup_path),
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
            array('name'  => 'backup_path', 'label' => 'Backup Path', 'type'  => 'text','hint'=>'Backup Will be stored in this directory','value' => 'backups/'.TS_SECRET.'/'.date('Y-m-d',time()).'/'),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Check Backup')
        );
        $response->sendDataJson();
    }
    $response->sendDataJson();



}