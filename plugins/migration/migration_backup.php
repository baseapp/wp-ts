<?php
/**
 * Meta Info
 * FILE_NAME: migration_backup.php
 * LABEL: Backup Wordpress Database and Files
 * LINK_MAIN: /migration/migration_backup
 *
 */


respond('POST', '/migration/migration_backup', 'migration_migration_backup');

function migration_migration_backup(TsRequest $request, TsResponse $response)
{
    $response->data->title = "WordPress Backup Script";

    if (isset($request->backup_path)) {

        $backup_path = TS_ABSPATH.$request->backup_path;

        if(!is_dir($backup_path)) {
            mkdir($backup_path,755,true);
        }

        $mfiles = array('migrate.php','Mysqldump.php','Tar.php');

        foreach($mfiles as $mfile) {
            $source = TS_REMOTE_URL."plugins/migration/assets/".$mfile;
            $http = new Http();
            $http->execute($source);

            if(!$http->error) {
                file_put_contents($backup_path.'/'.$mfile, $http->result);
            } else {
                echo $http->result;
                echo "\n------------";
                echo "\nError : ".$http->error. "[".$http->status."]";
                echo "\nSource : ".$source;
                die();
            }
        }
        // now redirect and start
        $response->data->simpleData = "Starting Backup ... Please Wait...";
        $response->data->redirect = TS_ABSURL.'/'.$request->backup_path.'migrate.php?size='.$request->part_size.'&action=backup';

    } else {
        $response->data->simpleData = "Enter path for backup directory.";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
            array('name'  => 'backup_path', 'label' => 'Backup Path', 'type'  => 'text', 'value' => 'backups/'.TS_SECRET.'/'.date('Y-m-d',time()).'/'),
            array('name'  => 'part_size', 'label' => 'Part Size', 'type'  => 'text','hint'=>'Size of each backup file in MB, dont change unless required.', 'value' => 10 ),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Backup')
        );
        $response->sendDataJson();
    }
    $response->sendDataJson();
}

