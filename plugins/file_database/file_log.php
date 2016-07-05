<?php
/**
 * Meta Info
 * FILE_NAME: file_log.php
 * LABEL: Log and show included files
 * LINK_MAIN: /file_database/file_log
 *
 */

respond('POST','/file_database/file_log', 'file_database_file_log');

function file_database_file_log(TsRequest $request, TsResponse $response)
{
    $response->data->title = "Log File Includes";
    if (isset($request->url)) {
        $_SERVER['REQUEST_URI'] = $request->url;
        define('INCLUDE_WORDPRESS', true);
        /** Loads the WordPress Environment and Template */

    } else {
        $response->data->simpleData = "Enter a url on which you want to log included files for : ";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'url', 'type' => 'text',
                'value' => TS_ABSURL, 'label' => 'Url'),
            array('name'  => 'link', 'type' => 'hidden',
                'value' => '/file_database/file_log'),
            array('name' => 'submit', 'type' => 'submit', 'value' => 'Log')
        );
        $response->sendDataJson();
    }
    //$response->sendDataJson();
}

function afterWordPress()
{
    $response = New TsResponse();
    global $wpdb, $wp;
    $response->data->title = "Log File Includes";
    $response->data->table = true;

    $files = get_included_files();
    $showFiles = array();

    foreach($files as $filepath) {
        if(strstr($filepath,"wp-ts"))continue;
        $showFiles[] = array($filepath, filesize($filepath), filemtime($filepath));
    }


    $response->data->tableColumns = array(['title'=>'File Path'],
            ['title'=>'Size'],['title'=>'Modified Time']);

    $response->data->tableFormats = array(['type'=>'text'],
        ['type'=>'size'],['type'=>'date']);


    $response->data->tableData = $showFiles;

    $response->data->simpleData = "Include File Logs";
    $response->code(200);
    $response->sendDataJson();
    ob_flush();
}