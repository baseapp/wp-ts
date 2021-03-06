<?php
/**
 * Core functions for WordPress Troubleshooter
 */

    respond('POST', '/login', 'login');
    respond(array('POST','GET'), '/home/[:sublevel]?', 'home');
    respond('POST', '/logout', 'logout');
    respond('POST', '/quick-search', 'quick_search');

/**
 * Login to the troubleshooter
 * @param $request
 * @param $response
 */
function login(TsRequest $request, TsResponse $response)
{
    if(Auth::isLoggedIn())
        home($request, $response);
    if ($request->password) {
        if (Auth::logIn($request->password)) {
            $response->flash("Logged in", "success");
            /*if(isset($request->backlink)){
                $response->discard(true);
                dispatch($request->backlink);
            } else*/
                home($request, $response);
        } else {
            $response->flash("Wrong password !!!", 'danger');
            $response->code(401);
        }
    } else {
        $response->flash("Please login first!!!", "danger");
        $response->data->title = "Home";
        $response->data->simpleData = "Please enter the password to access the troubleshooter.<br>
                             The password is given at the begaining of the script.";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'link', 'type'  => 'hidden', 'value' => '/login'),
            array('name'  => 'password', 'label' => 'Password', 'type'  => 'password', 'value' => ''),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Login')
        );
        $response->sendDataJson();
    }
}

/**
 * Logout from troubleshooter
 * @param $request
 * @param $response
 */
function logout(TsRequest $request, TsResponse $response)
{
    Auth::logOut();
    $response->flash("Logged Out !!!");
    $response->data->title = "Log Out";
    $response->data->form = true;
    $response->data->formData = array(
        array('name'  => 'link', 'type'  => 'hidden', 'value' => '/home' ),
        array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Home' )
    );
    $response->sendDataJson();
}

/**
 * Shows the home level and sub-level menu.
 * @param $request
 * @param $response
 */
function home (TsRequest $request, TsResponse $response)
{
    global $options;
    if(isset($request->sublevel))
    {
        $response->data->title = $options[$request->sublevel]['label'];
        $response->data->simpleData = $options[$request->sublevel]['label'];
        $options = $options[$request->sublevel]['plugins'];
        array_walk($options, function(&$v, $k){
            $v = array('type'=> 'radio', 'name'=>'link', 'value'=>$v['link_main'], 'label'=>$v['label']);
        });
    }else{
        $response->data->title = "Home";
        $response->data->simpleData = "Welcome to <strong>WordPress TroubleShooter</strong>. Select a troubleshoot action. ";
        array_walk($options, function(&$v, $k){
            $v = array('type'=> 'radio', 'name'=>'link', 'value'=>'/home/'.$k, 'label'=>$v['label']);
        });
    }
    $options = array_values($options);
    //$options[] = ['name'  => 'link', 'type'  => 'radio','value' => '/logout', 'label'=>'Logout'];
    $options[] = array('name'  => 'submit', 'type'  => 'submit','value' => 'Continue');
    $response->data->form = true;
    $response->data->formData = $options;
    $response->sendDataJson();
}

function downloadFile($source,$path = false){

    $http = new Http();

    if($path) {
        $http->execute($source, '', '', false, $path);
    } else {
        $http->execute($source);
    }

    if(!$http->error) {
        return $http->result;
    } else {
        return false;
    }
}

function downloadPlugin($path, $name = false) {

    if(!$name) {
        $name = str_replace(TS_PLUGIN_DIR,"",$path);
    }

    if(!is_dir(dirname($path))) {
        mkdir(dirname($path),0755,true);
    }

    $source = TS_REMOTE_URL."plugins/".$name;

    $http = new Http();
    $http->execute($source);

    if(!$http->error) {
        file_put_contents($path, $http->result);
    } else {
        echo $http->result;
        echo "\n------------";
        echo "\nError : ".$http->error. "[".$http->status."]";
        echo "\nSource : ".$source;
        die();
    }
}

function quick_search(TsRequest $request, TsResponse $response)
{
    global $options;
    $links=array();
    foreach($options as $name => $details){
        $links[] = array('link' =>'/home/'.$name, 'label' => $details['label']);
        foreach($details['plugins'] as $k => $v){
            $links[] = array('link' =>$v['link_main'], 'label' => $v['label']);
        }
    }
    $outlinks = array();
    foreach($links as $link){
        if (false === stripos( strtolower($link['label']), $request->str))
              continue;
            else {
                $link['label'] = str_ireplace($request->str, "<strong>".$request->str."</strong>", $link['label']);
                $outlinks[] = $link;
            }
    }
    $response->json($outlinks);
}

function getBreadcrumbs($link)
{
    global $options;
    $list = array();
    $trim_link = trim($link, '/');
    $link_arr = explode('/', $trim_link);
    if ( ! empty($link_arr)) {
        $list[] = array('link'=>'/home', 'label'=> 'Home');
        foreach($options as $level_name=>$level) {
            if (sizeof($link_arr >=2) && $link_arr[0] !== 'home') {
                if ($link_arr[0] === $level_name)
                   $list[] = array('link'=>'/home/'.$level_name, 'label'=> $level['label']);
                if (isset($level['plugins'])) {
                    foreach ($level['plugins'] as $file_name => $file) {
                        if (in_array($link, $file['links_all']) && $link !== $file['link_main']) {
                            $list[] = array('link' => $file['link_main'], 'label' => $file['label']);
                        }
                    }
                }
            }

        }
    }
    return $list;
}

function debug_log($message) {
    error_log(date('Y-m-d H:i:s',time()).' : ',3,TS_LOGS_DIR.'debug.log');
    error_log($message."\n",3,TS_LOGS_DIR.'debug.log');
}

function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

/*register_shutdown_function(function(){
    if(defined('TS_PLUGIN_DIR'))
        delTree(TS_PLUGIN_DIR);
});*/

