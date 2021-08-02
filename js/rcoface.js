var $RB = jQuery.noConflict();

// RCO Facelift Handler. If you are looking for the prior framework handler, it is available via rcojs.js - however,
// those scripts are disabled as we set rcoFacelift to true below.

// Do not set rcoFacelift to true, until there is something to handle.
$RB(document).ready(function ($) {
    if (typeof $ResursCheckout !== 'undefined') {
        // Set rcoFacelift to true if the new rco interface is available.
        rcoFacelift = true;
        getRcoFieldSetup();
    }
});
