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

        if(!is_dir($request->backup_path)) {
            mkdir($request->backup_path,755,true);
        }

        $mfiles = array('migrate.php','Mysqldump.php','Tar.php');

        foreach($mfiles as $mfile) {
            $source = TS_REMOTE_URL."plugins/migration/assets/".$mfile;
            $http = new Http();
            $http->execute($source);

            if(!$http->error) {
                file_put_contents($request->backup_path.'/'.$mfile, $http->result);
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
        $response->data->redirect = TS_ABSURL.'/wp-content/uploads/wp-ts/b6589fc6ab0dc82cf12099d1c2d40ab994e8410c/backup/migrate.php';

    } else {
        $response->data->simpleData = "Enter path for backup directory.";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
            array('name'  => 'backup_path', 'label' => 'Backup Path', 'type'  => 'text', 'value' => TS_PLUGIN_DIR.'backup'),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Backup')
        );
        $response->sendDataJson();
    }
    $response->sendDataJson();
}

