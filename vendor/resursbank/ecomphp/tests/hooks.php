<?php

function inject_test_storeid() {
	return 700;
}

function ecom_inject_payload( $payload ) {
	$payload['add_a_problem_into_payload'] = true;
	if ( isset( $payload['signing'] ) ) {
		unset( $payload['signing'] );
	}

	return $payload;
}