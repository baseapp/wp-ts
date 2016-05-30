<?php
/**
 * Created by PhpStorm.
 * User: gopal
 * Date: 11/5/16
 * Time: 4:26 PM
 */

/**
 * Meta Info
 * FILE_NAME: db_manager.php
 * LABEL: Database Manager
 * LINK_MAIN: /file_database/db_manager
 *
 */

if(!defined('WP_TS')) die('Invalid Access');

respond('POST','/file_database/file_manager', 'file_database_file_manager');

function file_database_db_manager(TsRequest $request, TsResponse $response)
{
    $out = "<form method='post' action='".$_SERVER["PHP_SELF"]."?ts_plugin=file_database/fm'>
            <input type=\"submit\" value=\"Launch file manager\" class=\"btn btn-primary\">
            </form>";
    $response->data->simpleData = $out;
    $response->sendDataJson();
}