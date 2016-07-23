<?php


date_default_timezone_set('America/New_York');
error_reporting(E_ALL);
ini_set('display_errors',true);

include "Mysqldump.php";

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

                    require_once('Tar.php');
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
    require_once('Tar.php');
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
 return $total;
}

function getDBInfo($configPath) {
    $f = $configPath;
    $c = file_get_contents($f);

    preg_match('/define.*DB_NAME.*\'(.*)\'/', $c, $m);
    $db['name'] = $m[1];

    preg_match('/define.*DB_USER.*\'(.*)\'/', $c, $m);
    $db['user'] = $m[1];

    preg_match('/define.*DB_PASSWORD.*\'(.*)\'/', $c, $m);
    $db['pass'] = $m[1];

    preg_match('/define.*DB_HOST.*\'(.*)\'/', $c, $m);
    $db['host'] = $m[1];

    return $db;
}


/**********************
 * Main Code 
 */

$backup = array('wp-content/');
//check wordpress installation 

$curPath = dirname(__FILE__).'/';
$curPath = str_replace('\\','/',$curPath);

$wpPath = dirname(dirname(dirname(dirname(dirname($curPath))))).'/';

$action = isset ($_GET['action'])?$_GET['action']:'default';

switch($action) {
    case 'backup':
        echo "Backing up <br />";
        $step = isset($_GET['step'])?$_GET['step']:0;
        $size = isset ($_GET['size'])?$_GET['size']:10;
        $entryNum = isset ($_GET['entry'])?$_GET['entry']:0;
        
        if($step == 0) {
            echo "Backing Database:";
            
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

            $db = getDBInfo($wpPath.'wp-config.php');

            $dump = new Ifsnop\Mysqldump\Mysqldump('mysql:host='.$db['host'].';dbname='.$db['name'],$db['user'],$db['pass'],$dumpSettings);
            $dump->start($curPath.'db.sql');

            echo 'Done<br />';
            // goto step to 
            echo '<META http-equiv="refresh" content="0;URL=migrate.php?action=backup&step=1&size='.$size.'" timeout="2">';
    
        } else {
            foreach($backup as $path) {
                    star($wpPath,$wpPath.$path,$curPath.'files_'.$step.'.tar',$size,$entryNum);
            }            
            
            if($_entryNum > 0) {
                echo '<META http-equiv="refresh" content="0;URL=migrate.php?action=backup&step='.($step+1).'&size='.$size.'&entry='.$_entryNum.'" timeout="2">';
            } else {
                echo 'Finished';
                // Lets write a Backup report
            }
            
            
        }
        
        
        
        break;
    default:
        // display info 
        printf('Wordpress Path : %s<br />',$wpPath);
        
        $db = getDBInfo($wpPath.'wp-config.php');

        if(function_exists('mysqli_connect')) {

            $conn = mysqli_connect($db['host'],$db['user'],$db['pass']) or die("Database error");
            mysqli_select_db($conn, $db['name']);

            $result  = mysqli_query($conn,"set names 'utf8'");

            $query   = sprintf('SELECT table_schema "'.$db['name'].'",Round(Sum(data_length + index_length) / 1024 / 1024, 1) "size" FROM   information_schema.tables GROUP  BY table_schema;');

            $result  = mysqli_query($conn,$query);
            $row     = mysqli_fetch_array($result);


        } else {
            $conn = mysql_connect($db['host'],$db['user'],$db['pass']) or die("Database error");
            mysql_select_db($db['name'], $conn);

            //SOLUTION::  add this comment before your 1st query -- force multiLanuage support
            $result  = mysql_query("set names 'utf8'");

            $query   = sprintf('SELECT table_schema "'.$db['name'].'",Round(Sum(data_length + index_length) / 1024 / 1024, 1) "size" FROM   information_schema.tables GROUP  BY table_schema;');

            $result  = mysql_query($query);
            $row     = mysql_fetch_array($result);
        }


        
        printf('Database : %0.2f MB <br />', $row['size'] );
        
        foreach($backup as $path) {
            $info = getDirectorySize($wpPath.$path);
            printf($path.' : %0.2f MB <br />', ($info['size'] / (1024*1024)) );
        }       
}

?>

<form>
    <input type="text" value="10" name="size"></input>
    <input type="hidden" name="action" value="backup"></input>
    <input type="submit" value="backup"></input>
</form>