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
    if (isset($request->email)) {
        /*$request->validate('email', 'Enter a valid email')->isEmail();
        include TS_ABSPATH.TS_WPINC.'class-phpmailer.php';
        include TS_ABSPATH.TS_WPINC.'class-smtp.php';
        $phpmailer = new PHPMailer(true);
        //$phpmailer->setFrom('gopalkildoliya@gmail.com');
        $phpmailer->addAddress($request->email);
        $phpmailer->Subject = "Test email";
        $phpmailer->Body = "This is a test email from wordpress";
        $phpmailer->IsMail();
        if (!$phpmailer->send()) {
            $response->flash("Unable to send Email !!! ", 'danger');
        } else {
            $response->flash('Mail Send !!! ', 'success');
        }*/
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
