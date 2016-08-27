<?php
/**
 * Meta Info
 * FILE_NAME: restore.php
 * LABEL: Restore Wordpress from a local backup
 * LINK_MAIN: /migration/restore
 *
 */

respond('POST', '/migration/restore', 'migration_restore');

function migration_restore(TsRequest $request, TsResponse $response,TsApp $app)
{

    $response->data->title = "Restore Wordpress From Backup";

    if (isset($request->backup_path)) {

        $backupPath = TS_ABSPATH.$request->backup_path;
        $backup = file_get_contents($backupPath . 'backup.info');

        if($backup) {

            $restorePart = 0;
            if (isset($request->restore_part)) {
                $restorePart = $request->restore_part;
            }

            $files = explode("\n", $backup);

            if (isset($files[$restorePart])) {
                list($restoreFile, $size) = explode(",", $files[$restorePart], 2);

                if (strstr($restoreFile, 'sql')) {
                    // Import into Database , Most hosts timeout at 30 Seconds
                    $maxRuntime = 15;
                    $deadline = time()+$maxRuntime;
                    $sqlFile = $backupPath.$restoreFile;
                    $progressFilename = $sqlFile.'_filepointer'; // tmp file for progress
                    $errorFilename = $sqlFile.'_error'; // tmp file for erro

                    $fp = fopen($sqlFile, 'r');

                    $filePosition = 0;
                    if( file_exists($progressFilename) ){
                        $filePosition = file_get_contents($progressFilename);
                        fseek($fp, $filePosition);
                    }

                    $queryCount = 0;
                    $query = '';
                    while( $deadline>time() AND ($line=fgets($fp, 1024000)) ){
                        if(substr($line,0,2)=='--' OR trim($line)=='' ){
                            continue;
                        }

                        $query .= $line;
                        if( substr(trim($query),-1)==';' ){
                            if( !mysqli_query($app->db->link,$query) ){
                                $error = 'Error performing query \'<strong>' . $query . '\': ' . mysqli_error($app->db->link);
                                file_put_contents($errorFilename, $error."\n");
                                exit;
                            }
                            $query = '';
                            file_put_contents($progressFilename, ftell($fp)); // save the current file position for
                            $queryCount++;
                        }
                    }

                    if( feof($fp) ){
                        // Move to next part
                        $restorePart += 1;
                        $response->data->simpleData = 'Database imported , Extracting Files';
                    }else{
                        //echo ftell($fp).'/'.filesize($filename).' '.(round(ftell($fp)/filesize($filename), 2)*100).'%'."\n";
                        $response->data->simpleData = $queryCount.' queries processed!,Please wait';
                    }

                } else if (strstr($restoreFile, 'tar')) {
                    // Restore wp_content
                    if($restorePart == 1) {
                        // Move wp_content to a temporary path
                        rename(TS_ABSPATH.'wp-content',TS_ABSPATH.'wp-content-'.time());
                        // Download Tar extractor
                        downloadFile(TS_REMOTE_URL."plugins/migration/assets/Tar.php",TS_TEMP_DIR.'Tar.php');
                    }
                    include (TS_TEMP_DIR.'Tar.php');

                    $tarFile = $backupPath.$restoreFile;

                    $tar = new splitbrain\PHPArchive\Tar();
                    $tar->open($tarFile);
                    $tar->extract(TS_ABSPATH);
                    $restorePart +=1 ;
                }

                $response->data->form = true;
                $response->data->formData = array(
                    array('name' => 'link', 'type' => 'hidden', 'value' => $request->link),
                    array('name' => 'backup_path', 'type' => 'hidden', 'value' => $request->backup_path),
                    array('name' => 'restore_part', 'type' => 'hidden', 'value' => $restorePart)
                );
                $response->data->simpleData = "Restoring : " . $restoreFile;
                $response->data->formSubmit = true;

            } else {
                // Extraction completed
                $response->data->simpleData = "Restore Complete";
            }
        } else {

        }
    }
    else if (isset($request->restore_path)) {
        $restorePath = TS_ABSPATH.$request->restore_path;
        $backup = file_get_contents($restorePath . 'backup.info');

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
                array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
                array('name'  => 'backup_path', 'type'  => 'hidden', 'value' => $request->restore_path),
                array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Restore Backup')
            );
            $response->sendDataJson();

        } else {
            // Backup Not found

            $response->data->simpleData = "No Backup Found";
        }


    } else {
        $response->data->simpleData = "Enter path of backup directory";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
            array('name'  => 'restore_path', 'label' => 'Backup Path', 'type'  => 'text','hint'=>'Directory where the backup files exist.','value' => 'wp-ts/' . TS_SECRET . '/backups/'.date('Y-m-d',time()).'/'),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Check Backup')
        );

    }

    $response->sendDataJson();

}