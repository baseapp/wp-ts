<?php
/**
 * Meta Info
 * FILE_NAME: login_as.php
 * LABEL: Login as any User
 * LINK_MAIN: /user/login_as
 *
 */

respond('POST', '/user/login_as', 'user_login_as');


function user_login_as(TsRequest $request, TsResponse $response, TsApp $app)
{

    if (isset($request->login_as)) {

        require_once(TS_ABSPATH.'wp-blog-header.php');
        $user = get_userdatabylogin( $request->login_as );
        if($user) {
            $user_id = $user->ID;
            wp_set_current_user( $user_id, $request->login_as );
            wp_set_auth_cookie( $user_id );
            do_action( 'wp_login', $request->login_as );


            $response->data->simpleData = "Loggin in as ".$request->login_as." ...";
            $response->data->redirect = TS_ABSURL;
        } else {
            $response->data->simpleData = "Invalid Username specified";
        }

        // Over ride any status codes by wordpress
        http_response_code(200);
        
    } else {

        $response->data->title = "Login as User";

        $response->data->simpleData = "Enter the username you wish to login as -";
        $response->data->form = true;
        $response->data->formData = array(
            array('name' => 'link', 'type' => 'hidden', 'value' => $request->link),
            array('name' => 'login_as', 'type' => 'text', 'value' => '', 'label' => 'Username'),
            array('name' => 'submit', 'type' => 'submit', 'value' => 'Login As')
        );

    }

    $response->sendDataJson();
}