<?php

/**
 * Description of StreamingVideo
 *
 * @author kevinmwando
 */
use ControllerBase as base;
class StreamingVideo extends Controller {
    /*     * *****************************************************************************
     * StreamingVideoProvider Ltd. video publishing API example client library.     *
     * version 1.8                                                                  *
     * update date: 20/01/2015                                                      *
     *                                                                              *
     * DISCLAIMER: This library is provided AS IS without any express or implied    *
     * warranties for purpose or fittness. Use it at your own discretion and your   *
     * own risk.                                                                    *
     *                                                                              *
     * Purpose:                                                                     *
     * This client library implements the calls needed to access the SVP publisher  *
     * API. This library may be used for reference and as a starting point to build *
     * up from for any applications that may need integration with the SVP platform.*
     *                                                                              *
     * Sections in the library:                                                     *
     * 1. UTILITY FUNCTIONS                                                         *
     * Utility functions used by other functions in the library.                    *
     * 2. SVP CALLS IMPLEMENTATION FUNCTIONS                                        *
     * Functions implementing the actual call procedures for access to the SVP API, *
     * including token retrieval and re-requesting.                                 *
     * 3. SVP CALL ENCAPSULATION FUNCTIONS                                          *
     * These functions are to be used by the publisher's application. They          *
     * encapsulate all actual service calls in easy to use native PHP funcitons.    *
     * ***************************************************************************** */

    /*     * *****************************************************************************
     * 1. UTILITY FUNCTIONS                                                         *
     * ***************************************************************************** */

//Creates a HTTP Request by given URL & arguments. Returns a text response or exit if connection error occurs.
    function httpGet($url, $arg = '') {
        if (function_exists('curl_init')) {
            return self::httpGetX($url, $arg);
        } else if (function_exists('fopen')) {
            return self::httpGetT($url, $arg);
        } else {
            die("No available HTTP Requesters !");
        }
    }

//Creates a HTTP Request by given URL & arguments (Needed CURL php extension).
    function httpGetX($url, $arg = '') {
        $sargs = '';
        if (is_array($arg)) {
            foreach ($arg as $k => $v) {
                if (strlen($sargs))
                    $sargs .= '&';
                $sargs .= $k . '=' . urlencode($v);
            }
        }
        if (strlen($sargs))
            $sargs = '?' . $sargs;

        $ch = curl_init($url . $sargs);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 900);

        $res = curl_exec($ch);
        curl_close($ch);

        return $res;
    }

//Creates a HTTP Request by given URL & arguments.
    function httpGetT($url, $arg = '') {
        $sargs = '';
        if (is_array($arg)) {
            foreach ($arg as $k => $v) {
                if (strlen($sargs))
                    $sargs .= '&';
                $sargs .= $k . '=' . urlencode($v);
            }
        }
        if (strlen($sargs))
            $sargs = '?' . $sargs;
        $file_stream = @fopen($url . $sargs, 'r');
        if ($file_stream) {
            $response = '';
            while ($data_row = fread($file_stream, 1024)) {
                $response .= $data_row;
            }
            fclose($file_stream);
            return $response;
        } else {
            return false;
        }
    }

// Simple XML parser function. Set first_record = true if you expect more than one result.
    function getTextBetweenTags($string, $tagname, $first_record = false) {
        $pattern = "/<$tagname>(.*?)<\/$tagname>/";
        if ($first_record) {
            preg_match($pattern, $string, $matches);
        } else {
            preg_match_all($pattern, $string, $matches);
        }

        return isset($matches[1]) ? $matches[1] : false;
    }

// Safely set html entities.
    function htmlCleanText($text) {
        return htmlentities(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    }

// Simple function to create a SelectBox from array.
    function arrayToCombo($name, $selected, $array, $attr = '') {
        $result = '<select name="' . htmlCleanText($name) . '" id="' . htmlCleanText($name) . '" ' . $attr . '>';
        foreach ($array as $key => $value) {
            $result .= '<option value="' . htmlCleanText($key) . '"';
            $result .= ($key == $selected) ? ' selected="selected"' : '';
            $result .= '>';
            $result .= htmlCleanText($value);
            $result .= '</option>';
        }
        $result .= '</select>';
        return $result;
    }

    function postVal($key, $default = '') {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    function getVal($key, $default = '') {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    function arrayVal($array, $key, $default = '') {
        return isset($array[$key]) ? $array[$key] : $default;
    }

// General function for getting a token.
    function getToken() {
        //global $api_key, $api_code;
        // Check for locally saved token.
        
        $token = self::loadToken();
        if (!$token) {
            $token_response = self::generateToken($this->base->settings['Stream']['api_key'], $this->base->settings['Stream']['api_code']);
            if (!$token_response['error_code']) {
                $token = $token_response['token'];
                // Save the token locally in order to avoid generating a token in every call of service.
                self::saveToken($token);
            } else {
                return $token_response;
            }
        }
        return array('error_code' => 0, 'token' => $token);
    }

// Call SVP Service
    function callSVPService($service, $arg, $raw_data = false) {
        global $entry_point;

        $count_attempts = 3;
        $token_error_codes = array(2004, 2005, 2006);
        $counter = 0;
        // Make a attempts to call a SVP API service.
        // With this we can automatically generate a new token if the token is empty,
        // the token is not valid or the validation period of the token is expired.
        while (true) {
            $counter++;
            $token_response = self::getToken();
            // If token error occurs - exit calling service.
            if ($token_response['error_code']) {
                return array('error_code' => $token_response['error_code'], 'response' => '');
            }
            $token = $token_response['token'];
            $arg['token'] = $token;

            $response = self::callSVP_API_Service($service, $arg);
            $result = self::getTextBetweenTags($response, 'result', true);

            if (!$result) {
                if (!$raw_data) {
                    return array('error_code' => -1, 'response' => $response);
                } else if (!$response) {
                    return array('error_code' => -1, 'response' => 'Empty server response');
                } else {
                    $error_code = 0;
                }
            } else if ($result == 'ERROR') {
                $error_code = self::getTextBetweenTags($response, 'code', true);
            } else if ($result == 'OK') {
                $error_code = 0;
            }

            if (!$error_code) {
                return array('error_code' => 0, 'response' => $response);
            } else if (in_array($error_code, $token_error_codes)) {
                // The service response contains a token error. Try to generate a new token.
                self::saveToken(0);
            }

            if ($counter >= $count_attempts) {
                return array('error_code' => $error_code, 'response' => $response);
            }
        }
    }

// End-point service call. Do not use this directly.Use callSVPService function.
    function callSVP_API_Service($service, $arg) {
        global $entry_point;
        $arg['l'] = 'api';
        $arg['a'] = $service;
        return self::httpGet($entry_point, $arg);
    }

// Get the error class message. For actual error message of the given error code look at documentation (APPENDIX 1)
    function getErrorMessage($error_code) {
        $arg['error_code'] = $error_code;
        $response = self::callSVP_API_Service('svp_get_error_message', $arg);
        return self::getTextBetweenTags($response, 'error_message', true);
    }

// Generate a token.
    function generateToken($api_key, $api_code) {
        $arg['api_key'] = $api_key;
        $arg['api_code'] = $api_code;

        $token = null;
        $response = self::callSVP_API_Service('svp_auth_get_token', $arg);
        $result = self::getTextBetweenTags($response, 'result', true);

        if ($result == 'OK') {
            $token = self::getTextBetweenTags($response, 'auth_token', true);
            return array('error_code' => 0, 'token' => $token);
        } else if ($result == 'ERROR') {
            $error_code = self::getTextBetweenTags($response, 'code', true);
            return array('error_code' => $error_code, 'token' => $token);
        }

        return array('error_code' => -1, 'token' => $token);
    }

    /*     * *****************************************************************************
     * SVP CALL ENCAPSULATION FUNCTIONS                                             *
     * ***************************************************************************** */

// Upload a new video. Optional parameters: channel_ref, video_title, tag_number, tag_string & user_ref
    function uploadVideo($upload_location_ref, $channel_ref, $file_name, $video_title, $tag_number, $tag_string, $user_ref) {
        $arg['upload_location_ref'] = $upload_location_ref;
        $arg['channel_ref'] = $channel_ref;
        $arg['file_name'] = $file_name;
        $arg['video_title'] = $video_title;
        $arg['tag_number'] = $tag_number;
        $arg['tag_string'] = $tag_string;
        $arg['user_ref'] = $user_ref;
        $service_response = self::callSVPService('svp_upload_video', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $ref_no = self::getTextBetweenTags($response_data, 'ref_no', true);

        return array('error_code' => 0, 'data' => array('ref_no' => $ref_no));
    }

// Get upload source list.
    function getUploadSourcesList() {
        $service_response = self::callSVPService('svp_list_upload_sources', array());

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $ref_numbers = self::getTextBetweenTags($response_data, 'ref_no');
        $names = self::getTextBetweenTags($response_data, 'name');

        return array('error_code' => 0, 'data' => array('ref_numbers' => $ref_numbers, 'names' => $names));
    }

// Get channels list.
    function getChannelsList() {
        $service_response = self::callSVPService('svp_list_video_playlists', array());

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $ref_numbers = self::getTextBetweenTags($response_data, 'ref_no');
        $titles = self::getTextBetweenTags($response_data, 'title');

        return array('error_code' => 0, 'data' => array('ref_numbers' => $ref_numbers, 'titles' => $titles));
    }

// Get users list.
    function getUsersList() {
        $service_response = self::allSVPService('svp_list_users', array());

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $ref_numbers = self::getTextBetweenTags($response_data, 'ref_no');
        $user_names = self::getTextBetweenTags($response_data, 'user_name');
        $login_names = self::getTextBetweenTags($response_data, 'login_name');

        return array('error_code' => 0, 'data' => array('ref_numbers' => $ref_numbers, 'user_names' => $user_names, 'login_names' => $login_names));
    }

// Get videos list.
    function getVideosList($filters = array()) {
        $service_response = self::callSVPService('svp_list_videos', $filters);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $ref_numbers = self::getTextBetweenTags($response_data, 'ref_no');
        $titles = self::getTextBetweenTags($response_data, 'title');

        return array('error_code' => 0, 'data' => array('ref_numbers' => $ref_numbers, 'titles' => $titles));
    }

// Get video images list.
    function getVideoImagesList($video_ref) {
        $arg = array();
        $arg['video_ref'] = $video_ref;

        $service_response = self::callSVPService('svp_list_video_images', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $numbers = self::getTextBetweenTags($response_data, 'number');
        $names = self::getTextBetweenTags($response_data, 'name');
        $extensions = self::getTextBetweenTags($response_data, 'extension');
        $primaries = self::getTextBetweenTags($response_data, 'primary');

        return array('error_code' => 0, 'data' => array('numbers' => $numbers, 'names' => $names, 'extensions' => $extensions, 'primaries' => $primaries));
    }

// Get video image.
    function getVideoImage($image_file, $type = null) {
        $arg = array();
        $arg['image_file'] = $image_file;
        if ($type !== null) {
            $arg['type'] = $type;
        }

        $service_response = self::callSVPService('svp_get_video_image', $arg, true);

        if ($service_response['error_code']) {
            return $service_response;
        }

        return array('error_code' => 0, 'data' => $service_response['response']);
    }

// Get primary video image.
    function getPrimaryVideoImage($video_ref, $type = null) {
        $arg = array();
        $arg['video_ref'] = $video_ref;
        if ($type !== null) {
            $arg['type'] = $type;
        }

        $service_response = self::callSVPService('svp_get_primary_video_image', $arg, true);

        if ($service_response['error_code']) {
            return $service_response;
        }

        return array('error_code' => 0, 'data' => $service_response['response']);
    }

// Set primary video image.
    function setVideoPrimaryImage($video_ref, $number) {
        $arg = array();
        $arg['video_ref'] = $video_ref;
        $arg['number'] = $number;

        $service_response = self::callSVPService('svp_set_primary_video_image', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Set video image.
    function setVideoImage($video_ref, $number, $url, $type) {
        $arg = array();
        $arg['video_ref'] = $video_ref;
        $arg['number'] = $number;
        $arg['url'] = $url;
        $arg['type'] = $type;

        $service_response = self::callSVPService('svp_set_video_image', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// New broadcast feed.
    function newBroadcastFeed($whitelabel, $video_title, $tag_number, $tag_string, $channel_ref) {
        $arg = array();
        $arg['whitelabel'] = $whitelabel;
        $arg['video_title'] = $video_title;
        $arg['tag_number'] = $tag_number;
        $arg['tag_string'] = $tag_string;
        $arg['channel_ref'] = $channel_ref;

        $service_response = self::callSVPService('svp_broadcast_feed', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);
        $ref_no = self::getTextBetweenTags($response_data, 'ref_no', true);
        $video_key = self::getTextBetweenTags($response_data, 'video_key', true);

        return array('error_code' => 0, 'data' => array('result' => $result, 'ref_no' => $ref_no, 'video_key' => $video_key));
    }

// Start broadcast.
    function startBroadcast($video_ref) {
        $arg = array();
        $arg['video_ref'] = $video_ref;

        $service_response = self::callSVPService('svp_start_broadcast', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Stop broadcast.
    function stopBroadcast($video_ref) {
        $arg = array();
        $arg['video_ref'] = $video_ref;

        $service_response = self::callSVPService('svp_stop_broadcast', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Start recording.
    function startRecording($video_ref) {
        $arg = array();
        $arg['video_ref'] = $video_ref;

        $service_response = self::callSVPService('svp_start_recording', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Stop recording.
    function stopRecording($video_ref) {
        $arg = array();
        $arg['video_ref'] = $video_ref;

        $service_response = self::callSVPService('svp_stop_recording', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Get advanced packages list.
    function getAdvancedPackagesList() {
        $service_response = callSVPService('svp_list_advanced_packages', array());

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $ref_numbers = self::getTextBetweenTags($response_data, 'ref_no');
        $names = self::getTextBetweenTags($response_data, 'name');

        return array('error_code' => 0, 'data' => array('ref_numbers' => $ref_numbers, 'names' => $names));
    }

// Assign video tickets.
    function assignVideoTickets($video_ref, $layout, $tickets, $package_ref, $protection_type, $ticket_title, $ticket_description, $ticket_price, $expiry_days, $expiry_months, $expiry_years, $total_allowed_views) {
        $arg['video_ref'] = $video_ref;
        $arg['layout'] = $layout;
        $arg['tickets'] = $tickets;
        $arg['package_ref'] = $package_ref;
        $arg['protection_type'] = $protection_type;
        $arg['ticket_title'] = $ticket_title;
        $arg['ticket_description'] = $ticket_description;
        $arg['ticket_price'] = $ticket_price;
        $arg['expiry_days'] = $expiry_days;
        $arg['expiry_months'] = $expiry_months;
        $arg['expiry_years'] = $expiry_years;
        $arg['total_allowed_views'] = $total_allowed_views;

        $service_response = self::callSVPService('svp_assign_video_tickets', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Set free video mode.
    function setFreeVideoMode($video_ref) {
        $arg['video_ref'] = $video_ref;

        $service_response = self::callSVPService('svp_set_free_video_mode', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Set video protection mode.
    function setVideoProtectionMode($video_ref, $protection_wall, $seconds) {
        $arg['video_ref'] = $video_ref;
        $arg['protection_wall'] = $protection_wall;
        $arg['seconds'] = $seconds;

        $service_response = self::callSVPService('svp_set_video_protection_mode', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Generate ticket passwords.
    function generateTicketPasswords($video_ref, $channel_ref, $layout, $ticket, $package_ref, $expiry_days, $expiry_months, $expiry_years, $total_allowed_views, $total_allowed_views_per_video, $count_passwords) {
        $arg['video_ref'] = $video_ref;
        $arg['channel_ref'] = $channel_ref;
        $arg['layout'] = $layout;
        $arg['ticket'] = $ticket;
        $arg['package_ref'] = $package_ref;
        $arg['expiry_days'] = $expiry_days;
        $arg['expiry_months'] = $expiry_months;
        $arg['expiry_years'] = $expiry_years;
        $arg['total_allowed_views'] = $total_allowed_views;
        $arg['total_allowed_views_per_video'] = $total_allowed_views_per_video;
        $arg['count_passwords'] = $count_passwords;

        $service_response = self::callSVPService('svp_generate_ticket_passwords', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);
        $passwords = getTextBetweenTags($response_data, 'password');

        return array('error_code' => 0, 'data' => array('result' => $result, 'passwords' => $passwords));
    }

// Set free video mode.
    function confirmPPVOrder($order_key, $email, $first_name, $last_name, $transaction_id) {
        $arg['order_key'] = $order_key;
        $arg['email'] = $email;
        $arg['first_name'] = $first_name;
        $arg['last_name'] = $last_name;
        $arg['transaction_id'] = $transaction_id;

        $service_response = self::callSVPService('svp_confirm_ppv_order', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// New rtsp cam.
    function newRTSPcam($rtsp_name, $rtsp_url, $new_broadcast = false, $whitelabel = false, $video_title = false, $tag_number = false, $tag_string = false, $channel_ref = false) {
        $arg = array();
        $arg['rtsp_name'] = $rtsp_name;
        $arg['rtsp_url'] = $rtsp_url;
        if ($new_broadcast === 'yes') {
            $arg['new_broadcast'] = $new_broadcast;
            $arg['whitelabel'] = $whitelabel;
            $arg['video_title'] = $video_title;
            $arg['tag_number'] = $tag_number;
            $arg['tag_string'] = $tag_string;
            $arg['channel_ref'] = $channel_ref;
        }

        $service_response = self::callSVPService('svp_create_rtsp', $arg);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $result = self::getTextBetweenTags($response_data, 'result', true);
        $ref_no = self::getTextBetweenTags($response_data, 'ref_no', true);
        $clip = self::getTextBetweenTags($response_data, 'video_key', true);

        return array('error_code' => 0, 'data' => array('result' => $result, 'ref_no' => $ref_no, 'clip' => $clip));
    }

// Get rtsp list.
    function getRTSPList($filters = array()) {
        $service_response = callSVPService('svp_list_rtsp', $filters);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];

        $ref_numbers = self::getTextBetweenTags($response_data, 'ref_no');
        $names = self::getTextBetweenTags($response_data, 'name');
        $urls = self::getTextBetweenTags($response_data, 'url');
        $statuses = self::getTextBetweenTags($response_data, 'status');
        $errors = self::getTextBetweenTags($response_data, 'message');

        return array('error_code' => 0, 'data' => array('ref_numbers' => $ref_numbers, 'names' => $names, 'urls' => $urls, 'statuses' => $statuses, 'errors' => $errors));
    }

// Remove rtsp cam.
    function removeRTSPcam($filters = array()) {
        $service_response = self::callSVPService('svp_remove_rtsp', $filters);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];
        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Edit rtsp cam
    function editRTSPcam($filters = array()) {
        $service_response = self::callSVPService('svp_edit_rtsp', $filters);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];
        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

// Attach broadcast resource
    function broadcastAttachResource($filters = array()) {
        $service_response = self::callSVPService('svp_broadcast_attach_resource', $filters);

        if ($service_response['error_code']) {
            return $service_response;
        }

        $response_data = $service_response['response'];
        $result = self::getTextBetweenTags($response_data, 'result', true);

        return array('error_code' => 0, 'data' => array('result' => $result));
    }

    function saveToken($token) {
        $_SESSION['token'] = $token;
    }

    // Loading the locally saved token.
    // Do not use SESSION in server to server environment!
    // Loading the locally saved token.
    // Do not use SESSION in server to server environment!
    function loadToken() {
        return isset($_SESSION['token']) ? $_SESSION['token'] : null;
    }
}
