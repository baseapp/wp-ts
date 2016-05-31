<?php
define('PASSWORD', 'root');

/* No Editing Below this Line */

define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_TS',true);

$absdir = dirname(__FILE__);
$absdir = str_replace('\\','/',$absdir);
define('TS_ABSPATH', $absdir. '/');
if(is_file(TS_ABSPATH.'wp_ts_debug')){
    // Local Debug
    define('TS_REMOTE_URL', 'http://127.0.0.1/wp-ts/');
} else {
    define('TS_REMOTE_URL', 'https://raw.githubusercontent.com/baseapp/wp-ts/master/');
}

define('TS_WPINC', 'wp-includes/');

$dir = sha1(PASSWORD + TS_WPINC);

define('TS_PLUGIN_DIR', TS_ABSPATH . 'wp-content/uploads/ts-tmp/' . $dir . '/');

if (!is_dir(TS_PLUGIN_DIR))
    mkdir(TS_PLUGIN_DIR, 0777, true);

session_start();
$idletime = 3000; //after 300 seconds the user gets logged out
if (isset($_SESSION['timestamp']) && ( time() - $_SESSION['timestamp'] > $idletime) ) {
    session_destroy();
    session_unset();
} else {
    $_SESSION['timestamp'] = time();
}

require "include/auth.inc.php";
require "include/klein.inc.php";
require "include/functions.php";
require "include/TsError.php";
require "include/JsonOutput.php";
require "include/class.http.php";


if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && isset($_POST['link'])) {

    if (!file_exists(TS_PLUGIN_DIR . 'plugins.json'))
        downloadPlugin(TS_PLUGIN_DIR.'plugins.json');
    $options_file = file_get_contents(TS_PLUGIN_DIR . 'plugins.json');

    global $options;
    $options = json_decode($options_file, true);
    respond(function (TsRequest $request, TsResponse $response, TsApp $app) {
        $response->onError(function ($response, $err_msg) {
            $response->flash($err_msg, 'danger');
            $response->back();
        });
        $app->register('db', function () {
            $db_details = array();
            $configPath = TS_ABSPATH . 'wp-config.php';
            if (is_file($configPath)) {
                $c = file_get_contents($configPath);
                if ($c) {
                    preg_match('/define.*DB_NAME.*\'(.*)\'/', $c, $m);
                    $db_details['name'] = $m[1];

                    preg_match('/define.*DB_USER.*\'(.*)\'/', $c, $m);
                    $db_details['user'] = $m[1];

                    preg_match('/define.*DB_PASSWORD.*\'(.*)\'/', $c, $m);
                    $db_details['pass'] = $m[1];

                    preg_match('/define.*DB_HOST.*\'(.*)\'/', $c, $m);
                    $db_details['host'] = $m[1];
                    preg_match('/\$table_prefix.*\'(.*)\'/', $c, $m);
                    $db_details['prefix'] = $m[1];

                } else {

                }
            } else {

            }
            /*$db = new DB($db_details['user'], $db_details['pass'], $db_details['name'], $db_details['host']);
            $db->set_prefix($db_details['prefix']);*/
            $db = new stdClass();
            $db->info = $db_details;
            $db->link = mysqli_connect($db_details['host'],$db_details['user'],$db_details['pass'],$db_details['name']);
            return $db;
        });
    });

    foreach ($options as $level_name => $level) {
        foreach ($level['plugins'] as $file_name => $file) {
            if (in_array($_POST['link'], $file['links_all'])) {
                if (!file_exists(TS_PLUGIN_DIR . $level_name . '/' . $file_name . '.php')) {
                    downloadPlugin(TS_PLUGIN_DIR . $level_name . '/' . $file_name . '.php');
                }
                require TS_PLUGIN_DIR . $level_name . '/' . $file_name . '.php';
            }
        }
    }

    if (Auth::isLoggedIn()) {
        dispatch($_POST['link']);

        // wordpress include
        if (function_exists('afterWordPress') && defined('INCLUDE_WORDPRESS')) {
            ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE);
            declare(ticks = 1);
            /*register_tick_function(function(){
                $fp = fopen('/work/backnew.txt', 'a');
                fwrite($fp, debug_backtrace()[2]['file'] . "\n");
                fclose($fp);
            });*/
            register_tick_function(array($p3Profiler, 'ts_tick_handler'));
            require TS_ABSPATH . 'index.php';
            //ob_end_clean();
            ob_clean();
            afterWordPress();
            //http_response_code(200);
        }

    } else {
        $_POST['backlink'] = $_POST['link'];
        dispatch('/login');
    }


} elseif (isset($_GET['ts_plugin'])) {

    if (Auth::isLoggedIn()) {
        if (!file_exists(TS_PLUGIN_DIR . $_GET['ts_plugin'] . '.php')) {
            downloadPlugin(TS_PLUGIN_DIR . $_GET['ts_plugin'] . '.php', $_GET['ts_plugin'].".php");
        }
        require TS_PLUGIN_DIR . $_GET['ts_plugin'] . '.php';
    }
} else {
    $layout = "{{layout}}";
    echo base64_decode($layout);

}


