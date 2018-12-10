<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_resurs_bank_backend', 'resurs_bank_ajax_backend');
add_action('wp_ajax_nopriv_resurs_bank_backend', 'resurs_bank_ajax_backend');

function reset_bank_ajax_token_accept()
{
    $return = false;
    try {
        $return = Resursbank_Core::resursbank_verify_nonce();
    } catch (\Exception $e) {
        $return = 2;
    }
    return $return;
}

/**
 * @param $tokenType
 * @return bool
 */
function resurs_bank_token_rejected($tokenType)
{
    if ($tokenType === 2) {
        return true;
    }
    return false;
}

function resurs_bank_show_ajax_response($ajaxResponse)
{
    header('Content-type: application/json; charset=utf-8');
    echo json_encode($ajaxResponse);
    die;
}

/**
 * @param $run
 * @param $ajaxResponse
 * @return mixed|void
 */
function resurs_bank_ajax_filters($run, $ajaxResponse)
{
    $ajaxResponse = apply_filters('resursbank_admin_backend', $ajaxResponse, isset($_REQUEST) ? $_REQUEST : array());

    if (!is_null($run)) {
        $ajaxResponse = apply_filters(
            'resursbank_admin_backend_' . $run,
            $ajaxResponse,
            isset($_REQUEST) ? $_REQUEST : array()
        );
    }
    if (is_admin()) {
        $ajaxResponse = apply_filters(
            'resursbank_backend_admin',
            $ajaxResponse,
            isset($_REQUEST) ? $_REQUEST : array()
        );
    }

    return $ajaxResponse;
}

/**
 * Backend AJAX calls lands here, regardless of destination
 * Currently under construction
 */
function resurs_bank_ajax_backend()
{
    $run = null;
    if (isset($_REQUEST['run'])) {
        $run = $_REQUEST['run'];
    }

    $tokenType = reset_bank_ajax_token_accept();

    $ajaxResponse = array(
        'success' => false,
        'response' => array(),
        'faultstring' => null,
        'tokenAccepted' => (($tokenType === 1) ? true : false),
        'tokenRejected' => resurs_bank_token_rejected($tokenType),
    );

    if (resurs_bank_token_rejected($tokenType)) {
        resurs_bank_show_ajax_response($ajaxResponse);
    }

    $ajaxResponse = resurs_bank_ajax_filters($run, $ajaxResponse);

    resurs_bank_show_ajax_response($ajaxResponse);
}