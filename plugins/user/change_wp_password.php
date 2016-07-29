<?php
/**
 * Meta Info
 * FILE_NAME: change_wp_password.php
 * LABEL: Change WordPress Admin Password
 * LINK_MAIN: /user/change_wp_password
 *
 */

respond('POST', '/user/change_wp_password', 'user_change_wp_password');

/**
 * Change WordPress admin password.
 *
 * @param $request
 * @param $response
 * @param $app
 */
function user_change_wp_password(TsRequest $request, TsResponse $response,TsApp $app)
{
    if ($request->password) {

        require_once(TS_ABSPATH.'wp-blog-header.php');
        wp_set_password($request->password, 1 );

        $response->data->simpleData = "Password changed as requested.";

        // Over ride any status codes by wordpress
        http_response_code(200);

    } else {
        $response->data->title = "Change WP-Admin Password";
        $response->data->simpleData = "Please enter new password for WP-Admin";
        $response->data->form = true;
        $response->data->formData = array(
            array('name'  => 'link', 'type'  => 'hidden','value' => $request->link ),
            array('name'  => 'password', 'label' => 'New WP-Admin Password', 'type'  => 'password', 'value' => ''),
            array('name'  => 'submit', 'type'  => 'submit', 'value' => 'Change Password')
        );
    }

    $response->sendDataJson();
}
