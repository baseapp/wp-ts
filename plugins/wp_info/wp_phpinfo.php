<?php
/**
 * Meta Info
 * FILE_NAME: wp_phpinfo.php
 * LABEL: Show PHP Info
 * HELP : Show the Server PHP Info using phpinfo()
 * LINK_MAIN: /wp_info/wp_phpinfo
 */

if(!defined('WP_TS')) die('Invalid Access');

respond('POST','/wp_info/wp_phpinfo', 'file_info_wp_phpinfo');

function file_info_wp_phpinfo(TsRequest $request, TsResponse $response)
{
    $out = "<form method='post' action='".$_SERVER["PHP_SELF"]."?ts_plugin=wp_info/phpinfo'>
            <input type=\"submit\" value=\"Show PHP Info\" class=\"btn btn-primary\">
            </form>";
    $response->data->simpleData = $out;
    $response->sendDataJson();
}