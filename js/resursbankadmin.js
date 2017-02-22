/*!
 * Resurs Bank AdminPanel
 */

var $RB = jQuery.noConflict();

$RB(document).ready(function ($) {
    jQuery('#woocommerce_resurs-bank_registerCallbacksButton').val(rb_buttons.registerCallbacksButton);
    jQuery('#woocommerce_resurs-bank_refreshPaymentMethods').val(rb_buttons.refreshPaymentMethods);
    jQuery('select[id*="finalizeIfBooked"]').bind("change", function () {
            if (this.value === "true") {
                var doUpdateMethods = confirm('This requires that callbacks are up to date. Do you want to fix this now?');
                if (doUpdateMethods) {
                    jQuery('input[id*="registerCallbacksButton"]').click();
                }
            }
        }
    );
    if (jQuery('#paymentMethodName').length > 0) {
        var methodName = jQuery('#paymentMethodName').html();
        var iconFieldName = "#woocommerce_" + methodName + "_icon";
        var iconField = jQuery(iconFieldName);
        if (iconField.length > 0) {
            iconField.after('<br><img src="' + iconField.val() + '">');
        }
    }
    var $el, $ps, $up, totalHeight;
    jQuery(".resurs-read-more-box .button").click(function () {
        jQuery('.resurs-read-more-box')
            .css({
                // Set height to prevent instant jumpdown when max height is removed
                "height": jQuery('#resursInfo').height,
                "max-height": 9999
            }).animate({
            "height": jQuery('#resursInfo').height
        });

        // fade out read-more
        jQuery('#resursInfoButton').fadeOut();

        // prevent jump-down
        return false;

    });
});
var fullFlowCollection = [];
var currentFlowCollection = [];
var flowRules = {
    "se": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
    "dk": ["resurs_bank_hosted"],
    "no": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
    "fi": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
};
function adminResursChangeFlowByCountry(o) {
    var country = o.value.toLowerCase();
    var ruleList = flowRules[country];
    if (fullFlowCollection.length == 0) {
        if (typeof flowRules[country] !== "undefined") {
            jQuery('#woocommerce_resurs-bank_flowtype option').each(function (i, e) {
                fullFlowCollection.push(e);
            });
        }
    }
    if (fullFlowCollection.length > 0) {
        jQuery('#woocommerce_resurs-bank_flowtype').empty();
        jQuery(fullFlowCollection).each(function (i, e) {
            var opVal = e.value.toLowerCase();
            if (jQuery.inArray(opVal, ruleList) > -1) {
                jQuery('#woocommerce_resurs-bank_flowtype').append(e);
            }
        });
    }
}

function resursEditProtectedField(currentField, ns) {
    $RB.ajax({
        url: rbAjaxSetup.ran,
        type: 'POST',
        data: {
            'wants': currentField.id,
            'ns': ns
        }
    }).done(
        function (data) {
            if (typeof data["success"] !== "undefined") {
                if (data["success"] === true) {
                    $RB('#' + currentField.id + "_value").val(data["response"]);
                }
            }
            resursProtectedFieldToggle(currentField.id);
        }
    ).fail(function (x, y) {
        alert("Fail (" + x + ", " + y + ")");
    });
}
function resursSaveProtectedField(currentFieldId, ns, cb) {
    var processId = $RB('#process_' + currentFieldId);
    if (processId.length > 0) {
        processId.html('<img src="' + rb_buttons.resursSpinner + '" border="0">');
    }
    var setVal = $RB('#' + currentFieldId + "_value").val();
    var subVal;

    if (currentFieldId == "woocommerce_resurs-bank_password") {
        subVal = $RB("#woocommerce_resurs-bank_login").val();
    }

    $RB.ajax({
        url: rbAjaxSetup.ran,
        type: 'post',
        data: {
            'puts': currentFieldId,
            'value': setVal,
            'ns': ns,
            's': subVal
        }
    }).done(function (data) {
        processId.html("");
        if (typeof data["success"] !== "undefined") {
            if (data["success"] === true) {
                if (typeof data["response"] === "object") {
                    var response = data["response"];
                    if (typeof response["element"] !== "undefined" && typeof response["html"] !== "undefined") {
                        $RB('#' + response["element"]).html(response["html"]);
                    }
                }
                if (cb !== "") {
                    runResursAdminCallback(cb, currentFieldId);
                }
            } else {
                if (typeof data["errorMessage"] !== "undefined" && data["errorMessage"] != "") {
                    if (processId.length > 0) {
                        processId.html('<div class="label label-danger" style="font-color: #990000;">' + data["errorMessage"] + '</div>');
                    } else {
                        alert("Not successful: " + data["errorMessage"]);
                    }
                } else {
                    alert("Not successful");
                }
            }
        }
        resursProtectedFieldToggle(currentFieldId);
    }).fail(function (x, y) {
        if (processId.length > 0) {
            processId.html("The saving on this field was unsuccessful.");
        } else {
            alert("The saving on this field was unsuccessful.");
        }
    });
}
function resursProtectedFieldToggle(currentField) {
    $RB('#' + currentField).toggle("medium");
    $RB('#' + currentField + "_hidden").toggle("medium");
}
function runResursAdminCallback(callbackName) {
    var setArg;
    var testProcElement;
    if (typeof arguments[1] !== "undefined") {
        setArg = arguments[1];
        testProcElement = $RB('#process_' + setArg);
        if (typeof testProcElement === "object") {
            testProcElement.html('<img src="' + rb_buttons.resursSpinner + '" border="0">');
        }
    }
    $RB.ajax({
        url: rbAjaxSetup.ran,
        type: "post",
        data: {
            'run': callbackName,
            'arg': setArg
        }
    }).done(function (data) {
        if (typeof testProcElement === "object") {
            testProcElement.html('');
        }
        if (typeof data["response"] === "object" && typeof data["response"][callbackName + "Response"] !== "undefined") {
            var response = data["response"][callbackName + "Response"];
            if (typeof response["element"] !== "undefined" && typeof response["html"] !== "undefined") {
                $RB('#' + response["element"]).html(response["html"]);
            }
            if (typeof data["success"] !== "undefined" && typeof data["errorMessage"] !== "undefined") {
                if (data["success"] === false && data["errorMessage"] !== "") {
                    if (typeof testProcElement === "object") {
                        testProcElement.html('<div class="label label-danger" style="font-color: #990000;">' + data["errorMessage"] + '</div>');
                    } else {
                        alert(data["errorMessage"]);
                    }
                }
            }
        }
    }).fail(function (x, y) {
        if (typeof testProcElement === "object") {
            testProcElement.html("Administration callback not successful");
        } else {
            alert("Administration callback not successful");
        }
    });
}