/*!
 * Resurs Bank AdminPanel
 */

var $RB = jQuery.noConflict();

$RB(document).ready(function ($) {
    // Only run this when the elements are correct
    if (jQuery('#callbackContent').length > 0) {
        if (typeof adminJs["requestForCallbacks"] !== "undefined" && (adminJs["requestForCallbacks"] === false || adminJs["requestForCallbacks"] == "" || null === adminJs["requestForCallbacks"])) {
            runResursAdminCallback("getMyCallbacks", "showResursCallbackArray");
        } else {
            doUpdateResursCallbacks();
        }
    }

    // TODO: This might come back when stuff are cleared out
    /*
     if (jQuery('#paymentMethodName').length > 0) {
     var methodName = jQuery('#paymentMethodName').html();
     var iconFieldName = "#woocommerce_" + methodName + "_icon";
     var iconField = jQuery(iconFieldName);
     if (iconField.length > 0) {
     iconField.after('<br><img src="' + iconField.val() + '">');
     }
     }
     */

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
var sessionWarnCount = 0;
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
    var spinnerBlock = $RB('#' + currentField.id + "_spinner");
    var hiddenBlock = $RB('#' + currentField.id + "_hidden");
    spinnerBlock.html('<img src="' + adminJs.resursSpinner + '" border="0">');
    spinnerBlock.show("medium");
    hiddenBlock.hide("medium");
    $RB('#' + currentField.id).hide("medium");
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
            resursProtectedFieldToggle(currentField.id, "show");
        }
    ).fail(function (x, y) {
        alert("Fail (" + x + ", " + y + ")");
    });
}
function resursSaveProtectedField(currentFieldId, ns, cb) {
    var processId = $RB('#process_' + currentFieldId);
    if (processId.length > 0) {
        processId.html('<img src="' + adminJs.resursSpinner + '" border="0">');
    }
    var setVal = $RB('#' + currentFieldId + "_value").val();
    var subVal;
    var envVal;

    if (currentFieldId == "woocommerce_resurs-bank_password") {
        subVal = $RB("#woocommerce_resurs-bank_login").val();
        $RB("#woocommerce_resurs-bank_serverEnv option").each(function (i, d) {
            if (d.selected) {
                envVal = d.value;
            }
        });
    }

    $RB.ajax({
        url: rbAjaxSetup.ran,
        type: 'post',
        data: {
            'puts': currentFieldId,
            'value': setVal,
            'ns': ns,
            's': subVal,
            'e': envVal
        }
    }).done(function (data) {
        processId.html("");
        if (typeof data["success"] !== "undefined") {
            if (data["success"] === true) {
                if (typeof data["response"] === "object") {
                    var response = data["response"];
                    if (typeof response["element"] !== "undefined" && typeof response["html"] !== "undefined") {
                        if (typeof response["element"] === "object") {
                            $RB.each(response["element"], function (elementIndex, elementName) {
                                $RB('#' + elementName).html(response["html"]);
                            });
                        } else {
                            $RB('#' + response["element"]).html(response["html"]);
                        }
                    }
                }
                if (cb !== "") {
                    runResursAdminCallback(cb, currentFieldId);
                }
            } else {
                if (typeof data["errorMessage"] !== "undefined" && data["errorMessage"] != "") {
                    if (processId.length > 0) {
                        processId.html('<div id="errSaveField' + currentFieldId + '" class="labelBoot labelBoot-danger" style="font-color: #990000;">' + data["errorMessage"] + '</div>');
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
    if (typeof arguments[1] !== "undefined") {
        if (arguments[1] === "show") {
            $RB('#' + currentField).hide("medium");
            $RB('#' + currentField + "_hidden").show("medium");
            $RB('#' + currentField + "_spinner").hide("medium");
        }
        if (arguments[1] === "hide") {
            $RB('#' + currentField).show("medium");
            $RB('#' + currentField + "_hidden").hide("medium");
            $RB('#' + currentField + "_spinner").show("medium");
        }
    } else {
        $RB('#' + currentField).toggle("medium");
        $RB('#' + currentField + "_hidden").toggle("medium");
        $RB('#' + currentField + "_spinner").toggle("medium");
    }
}
function runResursAdminCallback(callbackName) {
    var setArg;
    var argData = {};
    var testProcElement;
    if (typeof arguments[2] !== "undefined") {
        argData = arguments[2];
    }
    if (typeof arguments[1] !== "undefined") {
        setArg = arguments[1];
        if (typeof setArg !== "function") {
            testProcElement = $RB('#process_' + setArg);
            if (testProcElement.length > 0 && typeof testProcElement === "object") {
                testProcElement.html('<img src="' + adminJs.resursSpinner + '" border="0">');
            }
        }
    }
    var dataObject = {
        'run': callbackName,
        'arg': typeof setArg !== "function" ? setArg : "",
        'data': argData
    };
    $RB.ajax({
        url: rbAjaxSetup.ran,
        type: "post",
        data: dataObject
    }).done(function (data) {
        if (typeof window[setArg] === "function") {
            window[setArg](data);
        } else {
            if (typeof setArg === "function") {
                setArg(data);
            }
        }
        if (typeof testProcElement === "object") {
            testProcElement.html('');
        }

        if (typeof callbackName !== "undefined" && typeof data["response"] === "object" && data["response"] != null && typeof data["response"][callbackName + "Response"] !== "undefined") {
            var response = data["response"][callbackName + "Response"];
            if (typeof response["element"] !== "undefined" && typeof response["html"] !== "undefined") {
                $RB('#' + response["element"]).html(response["html"]);
            }
            if (typeof data["success"] !== "undefined" && typeof data["errorMessage"] !== "undefined") {
                if (data["success"] === false && data["errorMessage"] !== "") {
                    if (typeof testProcElement === "object") {
                        testProcElement.html('<div id="cbError' + callbackName + '" class="labelBoot labelBoot-danger" style="font-color: #990000;">' + data["errorMessage"] + '</div>');
                    } else {
                        if (typeof data["session"] !== "undefined") {
                            if (data["session"] == "0") {
                                sessionWarnCount++;
                                if (sessionWarnCount < 2) {
                                    if (data["errorMessage"] != "") {
                                        alert(data["errorMessage"]);
                                    }
                                }
                            } else {
                                if (data["errorMessage"] != "") {
                                    alert(data["errorMessage"]);
                                }
                            }
                        } else {
                            if (data["errorMessage"] != "") {
                                alert(data["errorMessage"]);
                            }
                        }
                    }
                }
            }
        } else {
            if (typeof data["errorMessage"] !== "undefined") {
                if (typeof data["element"] !== "undefined") {
                    $RB('#' + data["element"]).html(data["errorMessage"]);
                } else {
                    if (typeof data["session"] !== "undefined") {
                        if (data["session"] == "0") {
                            sessionWarnCount++;
                            if (sessionWarnCount < 2) {
                                if (data["errorMessage"] != "") {
                                    alert(data["errorMessage"]);
                                }
                            }
                        } else {
                            if (data["errorMessage"] != "") {
                                alert(data["errorMessage"]);
                            }
                        }
                    } else {
                        if (data["errorMessage"] != "") {
                            alert(data["errorMessage"]);
                        }
                    }
                }
            }
        }

    }).fail(function (x, y) {
        console.log("Failed in runResursAdminCallback(): " + y);
        if (typeof window[setArg] === "function") {
            window[setArg]([]);
        }
        if (typeof testProcElement === "object") {
            testProcElement.html("Administration callback not successful");
        } else {
            //alert("Administration callback not successful");
        }
    });
}

function showResursCallbackArray(cbArrayResponse) {
    var useCacheNote = false;   // For future use
    if (typeof cbArrayResponse !== "undefined" && typeof cbArrayResponse["response"] !== "undefined" && typeof cbArrayResponse["response"] !== null && typeof cbArrayResponse["success"] && cbArrayResponse["success"] !== false) {
        if (typeof cbArrayResponse["response"]["getMyCallbacksResponse"] !== "undefined") {
            var callbackListSize = 0;
            var callbackResponse = cbArrayResponse["response"]["getMyCallbacksResponse"];
            var isCached = callbackResponse["cached"];
            $RB.each(callbackResponse["callbacks"], function (cbName, cbObj) {
                callbackListSize++;
            });
            if (callbackListSize > 0) {
                var callbackContent = '<table class="wc_gateways widefat" cellspacing="0" cellpadding="0">';
                callbackContent += '<thead class="rbCallbackTableStatic"><tr><th class="rbCallbackTableStatic">Callback</th><th class="rbCallbackTableStatic">URI</th></tr></thead>';
                if (useCacheNote && isCached) {
                    callbackContent += '<tr><td colspan="2" style="padding: 2px !important;font-style: italic;">' + adminJs["callbackUrisCache"] + (adminJs["callbackUrisCacheTime"] != "" ? " (" + adminJs["callbackUrisCacheTime"] + ")" : "") + '</td></tr>';
                }
                $RB.each(callbackResponse["callbacks"], function (cbName, cbObj) {
                    // uriTemplates must not be null
                    if (cbName !== "" && typeof cbObj !== "undefined") {
                        callbackContent += '<tr><th class="rbCallbackTableStatic" width="25%">' + cbName + '</th><td class="rbCallbackTableStatic rbCallbackTableFont" ' + (isCached ? 'style="font-style:italic !important;"' : "") + ' width="75%">' + cbObj + "</td></tr>";
                    }
                });
                callbackContent += "</table><br>";
                callbackContent += '<input type="button" onclick="doUpdateResursCallbacks()" value="' + adminJs["update_callbacks"] + '"><br>';
                $RB('#callbackContent').html(callbackContent);
            } else {
                if (adminJs["requestForCallbacks"] === "1") {
                    $RB('#callbackContent').html('<b><i>' + adminJs["noCallbacksSet"] + '</i></b>');
                }
            }
        } else {
            $RB('#callbackContent').html("");
        }
        if (typeof cbArrayResponse["errorMessage"] !== "undefined" && cbArrayResponse["errorMessage"] !== "") {
            $RB('#callbackContent').html(cbArrayResponse["errorMessage"]);
        }
    } else {
        if (typeof cbArrayResponse["errorMessage"] !== "undefined" && cbArrayResponse["errorMessage"] !== "") {
            $RB('#callbackContent').html(cbArrayResponse["errorMessage"]);
        }
    }
}

var startResursCallbacks = 0;
var callbacksNotReceivedCheck = false;
var runningCbTest = null;
var lastRecvContent = "";
function doUpdateResursCallbacks() {
    startResursCallbacks = Math.round(new Date() / 1000);
    if ($RB('#receivedCallbackConfirm').length > 0) {
        $RB('#lastCbRec').html('<div class="labelBoot labelBoot-danger">' + adminJs["callbacks_pending"] + '</div>');
        $RB('#receivedCallbackConfirm').remove();
    }
    noCallbacksReceived();
    runningCbTest = window.setInterval("noCallbacksReceived()", 1000);
    lastRecvContent = $RB('#lastCbRec').html();
    $RB('#callbackContent').html('<img src="' + adminJs.resursSpinner + '" border="0">');
    runResursAdminCallback("setMyCallbacks", "updateResursCallbacksResult");
}

var timeBeforeSlowCallbacks = 15;
function noCallbacksReceived() {
    var stopResursCallbacks = Math.round(new Date() / 1000);
    var resursCallbacksTimeDiff = stopResursCallbacks - startResursCallbacks;
    var curRecvContent = $RB('#lastCbRec').html();
    if ($RB('#receivedCallbackConfirm').length > 0) {
        window.clearInterval(runningCbTest);
    } else {
        if (resursCallbacksTimeDiff < timeBeforeSlowCallbacks) {
            if (lastRecvContent == curRecvContent) {
                $RB('#lastCbRec').html('<div class="labelBoot labelBoot-danger">' + adminJs["callbacks_pending"] + '</div>');
            }
        } else {
            $RB('#lastCbRec').html('<div style="margin-bottom: 10px;"><span class="labelBoot labelBoot-danger">' + adminJs["callbacks_not_received"] + '</span></div>' +
                adminJs["callbacks_slow"]);
        }
    }
}

function updateResursCallbacksResult(resultResponse) {
    if (typeof resultResponse !== "undefined" && typeof resultResponse["response"] !== "undefined" && typeof resultResponse["response"] !== null && typeof resultResponse["success"] && resultResponse["success"] !== false) {
        var successCheck = resultResponse["response"]["setMyCallbacksResponse"];
        var callbackCount = "";
        if (typeof successCheck["testTriggerTimestamp"] !== "undefined") {
            $RB('#lastCbRun').html(successCheck["testTriggerTimestamp"]);
            setInterval('checkLastCallback()', 2000);
        }
        if (successCheck["errorstring"] != "") {
            callbackCount = successCheck["registeredCallbacks"];
        }
        $RB('#callbackContent').html(callbackCount + ' ' + adminJs["callbacks_registered"] + '<br><img src="' + adminJs.resursSpinner + '" border="0">');
        if ($RB('#callbacksRequireUpdate').length > 0) {
            $RB('#callbacksRequireUpdate').hide();
        }
        runResursAdminCallback("getMyCallbacks", "showResursCallbackArray");
    }
}


function checkLastCallback() {
    runResursAdminCallback("getLastCallbackTimestamp");
}

function wfcComboControl(checkboxObject) {
    var currentObject = checkboxObject.id;
    var wfc = $RB('#woocommerce_resurs-bank_waitForFraudControl');
    var aif = $RB('#woocommerce_resurs-bank_annulIfFrozen');
    var fib = $RB('#woocommerce_resurs-bank_finalizeIfBooked');
    if ($RB('#annulIfFrozenWarning').length == 0) {
        $RB('#columnRightannulIfFrozen').append('<br><div style="display:none;" id="annulIfFrozenWarning" class="labelBoot labelBoot-danger">' + adminJs.annulCantBeAlone + '</div>');
    }
    if (!wfc.prop("checked") && aif.prop("checked")) {
        wfc.attr("checked", true);
        $RB('#annulIfFrozenWarning').show("medium");
    } else if (!aif.prop("checked")) {
        $RB('#annulIfFrozenWarning').hide("medium");
    }
    if (!wfc.prop("checked") && !aif.prop("checked") && !fib.prop("checked")) {
        $RB('#shopFlowRecommendedSettings').show('medium');
    } else {
        $RB('#shopFlowRecommendedSettings').hide('medium');
    }
}
function changeResursFee(feeObject) {
    var feeId = feeObject.id.substr(4);
    var currentValue = feeObject.innerHTML;
    if (!isNaN(currentValue) || currentValue == "") {
        feeObject.innerHTML = '<input id="feeText_' + currentValue + '" type="text" size="8" value="' + currentValue + '" onblur="resetRbFeeValue(\'' + feeObject.id + '\', this)" onkeyup="feeValueTrigger(event, \'' + feeObject.id + '\', this)">';
        $RB('#feeText_' + currentValue).on("keypress", function (event) {
            return event.keyCode != 13;
        });
        var feeTextObject = $RB('#feeText_' + currentValue);
        feeTextObject.focus();
        feeTextObject[0].setSelectionRange(feeTextObject.val().length, feeTextObject.val().length);

    }
}

function feeValueTrigger(event, targetColumn, sourceField) {
    if (event.keyCode == 13) {
        resetRbFeeValue(targetColumn, sourceField);
    }
}
function resetRbFeeValue(targetColumn, sourceField) {
    $RB('#' + targetColumn).html(sourceField.value);
    var feeId = targetColumn.substr(4);

    if (!isNaN(sourceField.value) && sourceField.value >= 0 && sourceField.value !== "") {
        $RB('#process_' + feeId).html('<img src="' + adminJs.resursSpinner + '" border="0">');
        runResursAdminCallback("setNewPaymentFee", function (paymentFeeResult) {
            if (typeof paymentFeeResult["response"] !== "undefined" && typeof paymentFeeResult["response"]["setNewPaymentFeeResponse"]) {
                var feeResponse = paymentFeeResult["response"]["setNewPaymentFeeResponse"];
                var feeId = feeResponse["feeId"];
                var oldValue = feeResponse["oldValue"];
                var updated = feeResponse["update"];
                if (updated == 0) {
                    $RB('#fee_' + feeId).html(oldValue);
                    $RB('#process_' + feeId).html('<div class="labelBoot labelBoot-danger">' + adminJs.couldNotSetNewFee + '</div>');
                } else {
                    $RB('#process_' + feeId).html('<div class="labelBoot labelBoot-success">' + adminJs.newFeeHasBeenSet + '</div>');
                }
            }
        }, {"feeId": feeId, "feeValue": sourceField.value});
    }
}

function resursRemoveAnnuityElements(notThisElement) {
    var skipThis = "annuitySelector" + notThisElement;
    $RB("select[id*='annuitySelector']").each(function(i,e) {
        if (typeof e.id !== "undefined") {
            if (skipThis !=  e.id) {
                $RB('#' + e.id).remove();
                var curElementId = e.id.substr(e.id.indexOf('_')+1);
                $RB('#annuityClick_' + curElementId).removeClass("status-enabled");
                $RB('#annuityClick_' + curElementId).addClass("status-disabled");
            }
        }
    });
}