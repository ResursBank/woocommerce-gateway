<?php

add_action('wp_ajax_resurs_bank_backend', 'resurs_bank_ajax_backend');
add_action('wp_ajax_nopriv_resurs_bank_backend', 'resurs_bank_ajax_backend');

/**
 * Backend AJAX calls lands here, regardless of destination
 * Currently under construction
 */
function resurs_bank_ajax_backend()
{
    $ajaxResponse = array(
        'success' => false,
        'response' => array(),
        'faultstring' => null,
        'tokenAccepted' => false
    );

    if (wp_verify_nonce(isset($_REQUEST['token']) ? $_REQUEST['token'] : null, 'resursBankBackendRequest')) {
        $ajaxResponse['tokenAccepted'] = true;
    }


    if (is_admin()) {
        // Admin actions goes here and won't be available in any other way
    }

    header('Content-type: application/json; charset=utf-8');
    echo json_encode($ajaxResponse);

    die;
}