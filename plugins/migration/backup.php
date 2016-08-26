<?php
/**
 * Meta Info
 * FILE_NAME: backup.php
 * LABEL: Backup Wordpress Database and Files
 * LINK_MAIN: /migration/backup
 *
 */


respond('POST', '/migration/backup', 'migration_backup');


/*********************
 * General Functions
 ********************/

$startCompress = false;

$_tarSize = 0;
$_entryNum = 0;


function star($basepath,$source, $destination,$size = 10,$entryNum=0)
{
    $tarFiles = array();
    global $_tarSize,$_entryNum;

    if (!file_exists($source)) {
        echo 'Source File does not exist <br />';
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file)
        {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;

            // Ignore backups
            if(strstr($file,'backup') && strstr($file,'.tar.gz'))
                continue;

            if(strstr($file,'backup') && strstr($file,'.zip'))
                continue;

            // Ignore wp cache
            if(strstr($file,'wp-content/cache/'))
                continue;

            //$file = realpath($file);

            if (is_dir($file) === true)
            {
                if($_entryNum >= $entryNum) {
                    //$zip->addEmptyDir(str_replace($basepath , '', $file . '/'));
                    //$zipFiles[] = $file."/";
                    if (count(glob($file.'/*')) === 0 ) {
                        $tarFiles[] = $file."/";
                    }
                }
                $_entryNum++;
            }
            else if (is_file($file) === true)
            {
                if($_entryNum >= $entryNum) {
                    //$zip->addFromString(str_replace($basepath , '', $file), file_get_contents($file));
                    $tarFiles[] = $file;
                    $_tarSize += filesize($file);
                }
                $_entryNum++;

                if($_tarSize > $size*1024*1024) {

                    $tar = new splitbrain\PHPArchive\Tar();
                    $tar->create($destination);
                    foreach($tarFiles as $tarFile) {
                        $fileInfo = splitbrain\PHPArchive\FileInfo::fromPath($tarFile);
                        $relativePath = str_replace($basepath,'',"/".$tarFile);
                        $fileInfo->setPath($relativePath);
                        $tar->addFile($tarFile,$fileInfo);
                    }
                    $tar->close();
                    return;
                }

            }
        }
    }

    $_entryNum = -1;

    // write using pcl zip
    $tar = new splitbrain\PHPArchive\Tar();
    $tar->create($destination);
    foreach($tarFiles as $tarFile) {
        $fileInfo = splitbrain\PHPArchive\FileInfo::fromPath($tarFile);
        $relativePath = str_replace($basepath,'',"/".$tarFile);
        $fileInfo->setPath($relativePath);
        $tar->addFile($tarFile,$fileInfo);
    }
    $tar->close();

    return true;
}


function getDirectorySize($path)
{
    $totalsize = 0;
    $totalcount = 0;
    $dircount = 0;
    if($handle = opendir($path))
    {
        while (false !== ($file = readdir($handle)))
        {
            $nextpath = $path . '/' . $file;
            if($file != '.' && $file != '..' && !is_link ($nextpath))
            {
                if(is_dir($nextpath))
                {
                    $dircount++;
                    $result = getDirectorySize($nextpath);
                    $totalsize += $result['size'];
                    $totalcount += $result['count'];
                    $dircount += $result['dircount'];
                }
                else if(is_file ($nextpath))
                {
                    $totalsize += filesize ($nextpath);

                    $mb = 1024*1024;

                    if(filesize ($nextpath) > 10*$mb) {
                        echo $nextpath.' : '. ( filesize ($nextpath) / $mb ) .' MB<br />';
                    }


                    $totalcount++;
                }
            }
        }
    }
    closedir($handle);
    $total['size'] = $totalsize;
    $total['count'] = $totalcount;
    $total['dircount'] = $dircount;
    return $total;s
}



function migration_backup(TsRequest $request, TsResponse $response, TsApp $app)
{
    $response->data->title = "WordPress Backup Script";

    if (isset($request->backup_path)) {

        global $_entryNum;

        $backupPath = TS_ABSPATH.$request->backup_path;
        $backupLog = TS_ABSPATH.$request->backup_path.'/backup.info';
        $backupStep = 0;
        if(isset($request->backup_step)){
            $backupStep = $request->backup_step;
        }

        if($backupStep == 0) {
            // Set things up
            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0777, true);s
            }

            if(!is_dir($backupPath)) {
                $response->data->simpleData = "Error creating : ".$backupPath;
                $response->sendDataJson();
                return;
            }

            downloadFile(TS_REMOTE_URL . 'plugins/migration/assets/Mysqldump.php', TS_TEMP_DIR . 'Mysqldump.php');
            downloadFile(TS_REMOTE_URL . 'plugins/migration/assets/Tar.php', TS_TEMP_DIR . 'Tar.php');
        }

        // Dump Mysql

        if($backupStep == 0) {
            include (TS_TEMP_DIR.'Mysqldump.php');

            $dumpSettings = array(
                'no-data' => false,
                'add-drop-database' => false,
                'add-drop-table' => true,
                'single-transaction' => true,
                'lock-tables' => false,
                'add-locks' => true,
                'extended-insert' => true,
                'disable-foreign-keys-check' => false
            );

            $db = $app->db->info;

            $dump = new Ifsnop\Mysqldump\Mysqldump('mysql:host='.$db['host'].';dbname='.$db['name'],$db['user'],$db['pass'],$dumpSettings);
            $dump->start($backupPath.'db.sql');

            file_put_contents($backupLog,'db.sql,'.filesize($backupPath.'db.sql')."\n");

            $backupStep += 1;

        } else {
            include (TS_TEMP_DIR.'Tar.php');

            star(TS_ABSPATH,TS_ABSPATH.'wp-content/',$backupPath.'files_'.$backupStep.'.tar',$request->part_size,$request->part_entry);

            file_put_contents($backupLog,file_get_contents($backupLog).'files_'.$backupStep.'.tar,'.filesize($backupPath.'files_'.$backupStep.'.tar')."\n");

            $backupStep += 1;

        }

        if($_entryNum >= 0) {

            $response->data->form = true;
            $response->data->formData = array(
                array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
                array('name'  => 'backup_path', 'type'  => 'hidden', 'value' => $request->backup_path),
                array('name'  => 'part_size', 'type'  => 'hidden', 'value' => $request->part_size),
                array('name'  => 'part_entry', 'type'  => 'hidden', 'value' => $_entryNum),
                array('name'  => 'backup_step', 'type'  => 'hidden', 'value' => $backupStep)
            );
            $response->data->simpleData = "Backing up part : ".$backupStep."<br />Please Wait ...";
            $response->data->formSubmit = true;
        } else {
            $response->data->simpleData = "Backup Completed.<br />Your Backup Url - ";
            $response->data->form = true;
            $response->data->formData = array(array('name'  => 'backup_url','type'  => 'text','clipboard'=>true,'value' => TS_ABSURL."/".$request->backup_path));
        }

    } else {
        $response->data->simpleData = "Enter path for backup directory.";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'link', 'type'  => 'hidden', 'value' => $request->link),
            array('name'  => 'part_entry', 'type'  => 'hidden', 'value' => 0),
            array('name'  => 'backup_path', 'label' => 'Backup Path', 'type'  => 'text', 'value' => 'wp-ts/' . TS_SECRET . '/backups/'.date('Y-m-d',time()).'/'),
            array('name'  => 'part_size', 'label' => 'Part Size', 'type'  => 'text','hint'=>'Size of each backup file in MB, dont change unless required.', 'value' => 10 ),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Backup Now')
        );
        $response->sendDataJson();
    }
    $response->sendDataJson();
}

