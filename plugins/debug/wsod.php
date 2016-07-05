<?php
/**
 * Meta Info
 * FILE_NAME: wsod.php
 * LABEL: White Screen / No Response (WSOD)
 * LINK_MAIN: /debug/wsod
 *
 */


respond('POST', '/debug/wsod', 'debug_wsod');

function debug_wsod(TsRequest $request, TsResponse $response)
{
    $response->data->title = "Debug White Sceen / No Response";
    if (isset($request->url)) {
        define('INCLUDE_WORDPRESS',true);

        set_error_handler('myErrorHandler');
        register_shutdown_function('fatalErrorShutdownHandler');

    } else {
        $response->data->simpleData = "Enter the Problematic URL Where you see the white screen.";
        $response->data->form = true;
        $response->data->formData = array(
            array(
                'name'  => 'link',
                'type' => 'hidden',
                'value' => '/debug/wsod'
            ),
            array(
                'name'  => 'url',
                'type' => 'url',
                'value' => TS_ABSURL,
                'label' => 'Problem Url'
            ),
            array(
                'name' => 'submit',
                'type' => 'submit',
                'value' => 'Check Issue'
            )
        );
    }
    $response->sendDataJson();
}

function afterWordPress() {
    echo 'Im Here';
}




function myErrorHandler($code, $message, $file, $line) {
    ob_end_clean();
    $errorCodes = array(1=>"E_ERROR",2=>"E_WARNING",4=>"E_PARSE",8=>"E_NOTICE",16=>"E_CORE_ERROR",32=>"E_CORE_WARNING",64=>"E_COMPILE_ERROR",128=>"E_COMPILE_WARNING",256=>"E_USER_ERROR",512=>"E_USER_WARNING",1024=>"E_USER_NOTICE",2047=>"E_ALL");

    $response['title'] = "Error Found";
    $response['flash']['danger'] = "[ ".$errorCodes[$code]." ] ".$message." in File ".$file." [ $line ]";
    $response['simpleData'] = " ";
    echo json_encode($response);

    // Did we get called ?


}

function fatalErrorShutdownHandler()
{
    $last_error = error_get_last();
    // fatal error
    myErrorHandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);

}