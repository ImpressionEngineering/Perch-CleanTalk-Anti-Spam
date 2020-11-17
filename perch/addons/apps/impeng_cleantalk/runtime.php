<?php

if (!isset($_SESSION)) session_start();

require_once "vendor/lib/Cleantalk.php";
require_once "vendor/lib/CleantalkRequest.php";
require_once "vendor/lib/CleantalkResponse.php";
require_once "vendor/lib/CleantalkHelper.php";
require_once "vendor/lib/CleantalkAPI.php";

use lib\CleantalkRequest;
use lib\Cleantalk;
use lib\CleantalkAPI;

function impeng_cleantalk_form_handler($SubmittedForm) {
        $API = new PerchAPI(1.0, 'impeng_cleantalk');
        $Settings = $API->get('Settings');
        $data = $SubmittedForm->data;
        $attributes = $SubmittedForm->form_attributes;
        // Perch formID actually returns the formKey
        $formKey = $SubmittedForm->formID;
        $formEnabled = $Settings->get('impeng_cleantalk_activate_form_'.$formKey)->val();
        
        // check settings to establish if Cleantalk is enabled for received form.
        If ($formEnabled == 1) {

                // get config file with form specific field names
                $configFilePath = __DIR__.'/config/config.'.$formKey.'.php';
                if(file_exists($configFilePath)) {
                        include ($configFilePath);
                        error_log("Config file is:");
                        error_log( print_r( $configFilePath, true ) );
                }
                else {
                        include(__DIR__.'/config/config.default.php');
                        error_log("impeng_cleantalk is using a default config file, create a specif config file for this form at ".$configFilePath);
                }
        
                if ($SubmittedForm->data[$honeypotFieldID] == "") {

                        // Configure CleanTalk Account
                        $config_url = 'http://moderate.cleantalk.org/api2.0/';
                        $auth_key = $Settings->get('impeng_cleantalk_auth_key')->settingValue(); // Set Cleantalk auth key

                        // get form fields in Clentalk variables
                        $sender_nickname = "";
                        if(isset($data[$nameFieldID])) {
                        $sender_nickname = $data[$nameFieldID];
                        }
                        $sender_email = "";
                        if(isset($data[$emailFieldID])) {
                        $sender_email = $data[$emailFieldID];
                        }
                        $message = null;
                        if (isset($data[$messageFieldID]) && $data[$messageFieldID] != '')
                        $message = $data[$messageFieldID];

                        // get client IP
                        $sender_ip = PerchUtil::get_client_ip();

                        //check if client has js enabled
                        $js_on = 0;
                        if (isset($data['js_on']) && $data['js_on'] == date("Y")) {
                                $js_on = 1;
                        }
                        
                        // set up Cleantalk message parameters
                        $ct_request = new CleantalkRequest();
                        $ct_request->auth_key = $auth_key;
                        $ct_request->agent = 'php-api';
                        $ct_request->sender_email = $sender_email;
                        $ct_request->sender_ip = $sender_ip;
                        $ct_request->sender_nickname = $sender_nickname;
                        $ct_request->js_on = $js_on;
                        $ct_request->message = $message;
                        $ct_request->submit_time = time() - (int) $_SESSION['ct_submit_time'];

                        //CleanTalk Sender Info
                        $sender_info = array(
                                'page_url' =>htmlspecialchars(@$_SERVER['SERVER_NAME'].@$_SERVER['REQUEST_URI']),
                                'REFFERRER' => htmlspecialchars(@$_SERVER['HTTP_REFERER']),
                                'USER_AGENT' => htmlspecialchars(@$_SERVER['HTTP_USER_AGENT']),
                                'fields_number' => sizeof($_POST),
                        );
                        $sender_info = json_encode($sender_info);
                        if ($sender_info === false) $sender_info = '';
                        $ct_request->sender_info = $sender_info;

                        $ct_request->stoplist_check = 1;
                        $ct_request->all_headers = json_encode(apache_request_headers());

                        //CleanTalk Post Info
                        $post_info['comment_type'] = 'contact';
                        $post_info = json_encode($post_info);
                                if ($post_info === false) $post_info = '';
                        $ct_request->post_info = $post_info;

                        //create Cleantalk request
                        $ct = new \lib\Cleantalk();
                        $ct->server_url = $config_url;
                        
                        // send to CleanTalk
                        $ct_result = $ct->isAllowMessage($ct_request);

                        //check CleanTalk response
                        if ($ct_result->allow !== 1) {
                                // Cleantalk designates this as spam so set honeypot field so that Perch Forms spam lists this response.
                                $SubmittedForm->data[$honeypotFieldID] = "CleantalkID=".$ct_result->id;
                        }
                }

        }
        // prevent passing on js_on field
        unset($SubmittedForm->data['js_on']);
        //Redispatch all submitted forms to Perch forms regardless of enabled/disabled and CleanTalk result
        $SubmittedForm->redispatch('perch_forms');

}