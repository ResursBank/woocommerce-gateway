/*!
 * Resurs Bank AdminPanel
 */

var $RB = jQuery.noConflict();
var startResursCallbacks = 0;
var runningCbTestFirst = null;
var runningCbTestSecond = null;
var lastCallbackCheckInterval = null;
var lastRecvContent = "";

$RB(document).ready(function ($) {
    if (typeof adminJs["resursMethod"] !== 'undefined' && adminJs["resursMethod"] === "1") {
        var resursPaymentId = adminJs["resursPaymentId"];
        runResursAdminCallback(
            "getRefundCapability",
            "getResursRefundCapability",
            {"paymentId": resursPaymentId}
        );
    }

    // Only run this when the elements are correct
    if (jQuery('#callbackContent').length > 0) {
        if (typeof adminJs["requestForCallbacks"] !== "undefined" && (adminJs["requestForCallbacks"] === false || adminJs["requestForCallbacks"] == "" || null === adminJs["requestForCallbacks"])) {
            runResursAdminCallback("getMyCallbacks", "showResursCallbackArray");
        } else {
            doUpdateResursCallbacks();
        }
    }
    if (jQuery('#woocommerce_resurs-bank_flowtype').length > 0) {
        if (jQuery('#woocommerce_resurs-bank_country').val() == "DK") {
            adminResursChangeFlowByCountry(document.getElementById('woocommerce_resurs-bank_country'));
        }
    }
    if (jQuery('.nav-tab').length > 0) {
        jQuery('.nav-tab').each(function (i, elm) {
            if (typeof elm.href !== 'undefined') {
                if (elm.innerHTML === 'Resurs Bank' && elm.href.indexOf('_resursbank') > -1) {
                    if (typeof adminJs["resursBankTabLogo"] !== "undefined") {
                        elm.innerHTML = '<img src="' + adminJs["resursBankTabLogo"] + '">';
                    }
                }
            }
        });
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
var sessionWarnCount = 0;
var flowRules = {
    "se": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
    "dk": ["simplifiedshopflow", "resurs_bank_hosted"],
    "no": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
    "fi": ["simplifiedshopflow", "resurs_bank_hosted", "resurs_bank_omnicheckout"],
};

function noRefund() {
    console.log("Not fundable order notice replaced refund-button.");
    var refundButtonReplacement = jQuery(
        '<div>', {
            "id": "refundButtonReplacement",
            "style": "font-weight: bold; color: #000099;"
        }
    ).text(adminJs["methodDoesNotSupportRefunding"]
    );
    jQuery('.refund-items').replaceWith(refundButtonReplacement);
}

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

/**
 * If the password box is not visible, click it.
 * @returns {boolean}
 */
function resursClickUsername() {
    var r = false;
    if (!jQuery('#woocommerce_resurs-bank_password_hidden').is(':visible')) {
        jQuery('#woocommerce_resurs-bank_password').click();
        r = true;
    }
    return r;
}

function resursSaveProtectedField(currentFieldId, ns, cb) {
    var processId = $RB('#process_' + currentFieldId);
    if (processId.length > 0) {
        processId.html('<img src="' + adminJs.resursSpinner + '" border="0">');
    }
    var setVal = $RB('#' + currentFieldId + "_value").val();
    var subVal;
    var envVal;

    if (currentFieldId === "woocommerce_resurs-bank_password") {
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
        $RB('#' + currentField + "_spinner").hide("medium");
        $RB('#' + currentField).toggle("medium");
        $RB('#' + currentField + "_hidden").toggle("medium");
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

        var errorCode = typeof data["errorMessage"] !== 'undefined' ? data["errorMessage"] : null;
        if (typeof callbackName !== "undefined" && typeof data["response"] === "object" && data["response"] != null && typeof data["response"][callbackName + "Response"] !== "undefined") {
            var response = data["response"][callbackName + "Response"];
            if (typeof response["element"] !== "undefined" && typeof response["html"] !== "undefined") {
                $RB('#' + response["element"]).html(response["html"]);
            }
            if (typeof data["success"] !== "undefined" && typeof data["errorMessage"] !== "undefined") {
                if (data["success"] === false && data["errorMessage"] !== "") {
                    if (typeof testProcElement === "object") {
                        testProcElement.html('<div id="cbError' + callbackName + '" class="labelBoot labelBoot-danger" style="font-color: #990000;">' + '(' + errorCode + ') ' + data["errorMessage"] + '</div>');
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
                    $RB('#' + data["element"]).html("(" + errorCode + ") " + data["errorMessage"]);
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

function setCbString(th, str) {
    th.innerHTML = str;
}

function removeResursCallback(callbackName) {
    // Fix this with nonces
}

function showResursCallbackArray(cbArrayResponse) {
    var useCacheNote = false;   // For future use
    //var maxCbUrlLength = 80;
    var maxCbUrlLength = 1024;
    if (typeof cbArrayResponse !== "undefined" && typeof cbArrayResponse["response"] !== "undefined" && typeof cbArrayResponse["response"] !== null && typeof cbArrayResponse["success"] && cbArrayResponse["success"] !== false) {
        if (typeof cbArrayResponse["response"]["getMyCallbacksResponse"] !== "undefined") {
            var callbackListSize = 0;
            var callbackResponse = cbArrayResponse["response"]["getMyCallbacksResponse"];

            var isCached = callbackResponse['cached'];
            $RB.each(callbackResponse['callbacks'], function (cbName, cbObj) {
                callbackListSize++;
            });
            if (callbackListSize > 0) {
                var callbackContent = '<table class="wc_gateways widefat rbCallbackTable" cellspacing="0" cellpadding="0" width="100%">';
                callbackContent += '<tr>' +
                    '<th class="rbCallbackTableStatic">Callback</th>' +
                    '</tr>';
                if (useCacheNote && isCached) {
                    callbackContent += '<tr>' +
                        '<td colspan="2" style="padding: 2px !important;font-style: italic;">' + adminJs["callbackUrisCache"] + (adminJs["callbackUrisCacheTime"] != "" ? " (" + adminJs["callbackUrisCacheTime"] + ")" : "") + '</td>' +
                        '</tr>';
                }
                $RB.each(callbackResponse["callbacks"], function (cbName, cbObj) {
                    var cbObjString = "";
                    var cbStatus = "";
                    // uriTemplates must not be null
                    if (cbName !== "" && typeof cbObj !== "undefined") {
                        // Shorten string to make the table fit more data (besides, word wrapping does not work on long strings)
                        if (cbObj.length > maxCbUrlLength) {
                            cbObjString = cbObj.substr(0, maxCbUrlLength) + " [...]";
                        } else {
                            cbObjString = cbObj;
                        }
                        callbackContent += '<tr>' +
                            '<td class="rbCallbackTableStatic rbCallbackStaticLeft" width="10%">';
                        callbackContent += '<div style="font-weight: bold;">' + cbName + '</div>';
                        callbackContent += '<div>' + cbStatus + '</div>';
                        callbackContent += '</td>';

                        callbackContent += '<td width="80%" class="rbCallbackTableStatic rbCallbackStaticRight rbCallbackTableFont" ' + (isCached ? ' style="font-style:italic !important;"' : "") + ' width="75%">';
                        callbackContent += '<div style="cursor:pointer;" id="tc_cb_' + cbName + '" onclick="setCbString(this, \'' + cbObj + '\')">' + cbObjString + '</div>';
                        callbackContent += '</td>';

                        // Requires at least nonces and a referer check, so this is skipped for now
                        //callbackContent += '<td onclick="removeResursCallback(\''+cbName+'\')">X</td>'
                        callbackContent += '</tr>';
                    }
                });
                callbackContent += '</table><br>';
                callbackContent += '<input type="button" onclick="doUpdateResursCallbacks()" value="' + adminJs["update_callbacks"] + '">';
                callbackContent += '<input type="button" onclick="doUpdateResursTest()" value="' + adminJs["update_test"] + '">';
                callbackContent += '<br>';

                if (typeof adminJs['afterUpdateResursCallbacks'] !== 'undefined') {
                    callbackContent += adminJs['afterUpdateResursCallbacks'];
                }

                $RB('#callbackContent').html(callbackContent);
            } else {
                callbackContent = '<input type="button" onclick="doUpdateResursCallbacks()" value="' + adminJs["update_callbacks"] + '"><br>';
                if (typeof adminJs['afterUpdateResursCallbacks'] !== 'undefined') {
                    callbackContent += adminJs['afterUpdateResursCallbacks'];
                }

                $RB('#callbackContent').html(callbackContent);

            }
        } else {
            $RB('#callbackContent').html("");
        }
        if (typeof cbArrayResponse["errorMessage"] !== "undefined" && cbArrayResponse["errorMessage"] !== "") {
            $RB('#callbackContent').html(cbArrayResponse["errorMessage"]);
        }
    } else {
        var errorCode = typeof cbArrayResponse["errorCode"] !== 'undefined' ? cbArrayResponse["errorCode"] : null;
        if (typeof cbArrayResponse["errorMessage"] !== "undefined" && cbArrayResponse["errorMessage"] !== "") {
            $RB('#callbackContent').html('(' + errorCode + ') ' + cbArrayResponse["errorMessage"]);
        }
    }
}

function clearAllCbIntervals() {
    if (null !== runningCbTestFirst) {
        console.log("Clear cbtest-1");
        window.clearInterval(runningCbTestFirst);
    }
    if (null !== runningCbTestSecond) {
        console.log("Clear cbtest-2");
        window.clearInterval(runningCbTestSecond);
    }
    if (null !== lastCallbackCheckInterval) {
        console.log("Clear lastCbTest");
        window.clearInterval(lastCallbackCheckInterval);
    }
}

/**
 * Separate button click for testcallbacks.
 */
function doUpdateResursTest() {
    clearAllCbIntervals();
    $RB('#lastCbRec').html('<div class="labelBoot labelBoot-danger" style="font-size: 14px !important;">' + adminJs["callbacks_pending"] + '</div>');
    runResursAdminCallback('resursTriggerTest', 'resursTriggerTestResponse');
}

/**
 * Handle responses from separate trigger tests.
 * @param triggerTestData
 */
function resursTriggerTestResponse(triggerTestData) {
    if (typeof triggerTestData['response'] !== "undefined") {
        var inData = triggerTestData['response']['resursTriggerTestResponse'];
        if (inData['testTriggerActive']) {
            $RB('#lastCbRec').html(inData['html']);
            startResursCallbacks = Math.round(new Date() / 1000);
            noCallbacksReceived();
            runningCbTestSecond = window.setInterval("noCallbacksReceived()", 1000);
            lastCallbackCheckInterval = window.setInterval('checkLastCallback()', 3000);
        }
    }
}

function doUpdateResursCallbacks() {
    startResursCallbacks = Math.round(new Date() / 1000);
    if ($RB('#receivedCallbackConfirm').length > 0) {
        $RB('#lastCbRec').html('<div class="labelBoot labelBoot-danger" style="font-size: 14px !important;">' + adminJs["callbacks_pending"] + '</div>');
        $RB('#receivedCallbackConfirm').remove();
    }
    noCallbacksReceived();
    runningCbTestFirst = window.setInterval("noCallbacksReceived()", 1000);
    lastRecvContent = $RB('#lastCbRec').html();
    $RB('#callbackContent').html('<img src="' + adminJs.resursSpinner + '" border="0">');
    runResursAdminCallback("setMyCallbacks", "updateResursCallbacksResult");
}

function rbGetIpInfo() {
    $RB('#externalIpInfo').html('<img src="' + adminJs.resursSpinner + '" border="0">');
    runResursAdminCallback('getRbIpInfo', 'getRbIpInfoResponse');
}

/**
 * External info helper.
 * @param info
 */
function getRbIpInfoResponse(info) {
    var errorString = '';
    if (typeof info.errorMessage !== 'undefined') {
        errorString = info.errorMessage;
    }
    if (typeof info.response !== 'undefined' &&
        typeof info.response.getRbIpInfoResponse &&
        typeof info.response.getRbIpInfoResponse.errormessage) {
        errorString = info.response.getRbIpInfoResponse.errormessage;
        if (errorString !== '') {
            $RB('#externalIpInfo').html(errorString);
        } else {
            $RB('#externalIpInfo').html(info.response.getRbIpInfoResponse.externalinfo);
        }
    }
}

function doGetRWcurlTags() {
    $RB('#rwocurltag').html('<img src="' + adminJs.resursSpinner + '">');
    $RB('#rwocurltag').show();
    runResursAdminCallback("getNetCurlTag", "getNetCurlTag");
}

function doGetRWecomTags() {
    $RB('#rwoecomtag').html('<img src="' + adminJs.resursSpinner + '">');
    $RB('#rwoecomtag').show();
    runResursAdminCallback("getEcomTag", "getEcomTag");
}

function getNetCurlTag(info) {
    if (typeof info["response"] !== "undefined" && info["response"]["getNetCurlTagResponse"] != "undefined") {
        var curlTagData = info["response"]["getNetCurlTagResponse"];
        $RB('#rwocurltag').html("<b>netcurl response:</b> " + curlTagData["netCurlTag"]);
    }
}

function getEcomTag(info) {
    if (typeof info["response"] !== "undefined" && info["response"]["getEcomTagResponse"] != "undefined") {
        var ecomTagData = info["response"]["getEcomTagResponse"];
        $RB('#rwoecomtag').html("<b>ecomphp response:</b> " + ecomTagData["ecomTag"]);
    }
}

var timeBeforeSlowCallbacks = 15;

function noCallbacksReceived() {
    var stopResursCallbacks = Math.round(new Date() / 1000);
    var resursCallbacksTimeDiff = stopResursCallbacks - startResursCallbacks;
    var curRecvContent = $RB('#lastCbRec').html();
    if ($RB('#receivedCallbackConfirm').length > 0) {
        clearAllCbIntervals();
    } else {
        if (resursCallbacksTimeDiff < timeBeforeSlowCallbacks) {
            if (lastRecvContent == curRecvContent) {
                $RB('#lastCbRec').html('<div class="labelBoot labelBoot-danger" style="font-size: 14px !important;">' + adminJs["callbacks_pending"] + '</div>');
            }
        } else {
            $RB('#lastCbRec').html('<div style="margin-bottom: 10px;">' +
                '<div class="labelBoot labelBoot-danger" style="font-size: 14px !important;">' + adminJs["callbacks_not_received"] + '</div>' +
                '</div>' +
                '<div class="labelBoot labelBoot-warning" style="font-size: 14px  !important;;">' + adminJs["callbacks_slow"] + '</div>'
            );
        }
    }
}

function updateResursCallbacksResult(resultResponse) {
    if (typeof resultResponse !== "undefined" && typeof resultResponse["response"] !== "undefined" && typeof resultResponse["response"] !== null && typeof resultResponse["success"] && resultResponse["success"] !== false) {
        var successCheck = resultResponse["response"]["setMyCallbacksResponse"];
        var callbackCount = "";
        if (typeof successCheck["testTriggerTimestamp"] !== "undefined") {
            $RB('#lastCbRun').html(successCheck["testTriggerTimestamp"]);
            lastCallbackCheckInterval = setInterval('checkLastCallback()', 2000);
        }
        if (successCheck["errorstring"] != "") {
            callbackCount = successCheck["registeredCallbacks"];
        }
        $RB('#callbackContent').html(callbackCount + ' ' + adminJs["callbacks_registered"] + '<br><img src="' + adminJs.resursSpinner + '" border="0">');
        if ($RB('#callbacksRequireUpdate').length > 0) {
            $RB('#callbacksRequireUpdate').hide();
        }
        runResursAdminCallback("getMyCallbacks", "showResursCallbackArray");
    } else {
        if (typeof resultResponse["response"] !== "undefined") {
            var successCheck = typeof resultResponse["response"]["setMyCallbacksResponse"] !== "undefined" ? resultResponse["response"]["setMyCallbacksResponse"] : null;
            // Running test trigger separately may cause unexpected responses here.
            if (null !== successCheck && typeof successCheck !== 'undefined' && typeof successCheck["errorstring"] !== "undefined" && successCheck["errorstring"] != "") {
                $RB('#callbackContent').html("<pre style='color: #990000;'>" + successCheck["errorstring"] + "</pre>");
            }
        } else {
            $RB('#callbackContent').html("<pre style='color: #990000;'>Something went terribly wrong during the updateResursCallbacksResult() and no errors could be fetched</pre>");
        }
    }
}


function checkLastCallback() {
    runResursAdminCallback("getLastCallbackTimestamp");
}

function devFlagsControl(o) {
    if (o.value == '?') {
        o.title = 'Examples: DISABLE_SSL_VALIDATION, ALLOW_PSP, DEBUG, AUTO_DEBIT=METHODTYPE, XDEBUG_SESSION_START=IDE, FEE_EDITOR';
    }
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

function feeValueTrigger(event, targetColumn, sourceField, oldValue) {
    if (event.keyCode == 13) {
        resetRbFeeValue(targetColumn, sourceField, oldValue);
    }
}

function changeResursFee(chosenFeeObject) {
    var feeId = chosenFeeObject.id.substr(4);
    feeObject = document.getElementById('fee_' + feeId);
    var currentValue = feeObject.innerHTML;

    // If the editing pen image is there, this is after 2.2.10. In that case, make sure the html is clear from this image.
    if (currentValue.indexOf('pen16x') > -1) {
        currentValue = '';
    }

    if (!isNaN(currentValue) || currentValue == "") {
        feeObject.innerHTML = '<input id="feeText_' + feeId + '" type="text" size="8" value="' + currentValue.trim() + '" onblur="resetRbFeeValue(\'' + feeObject.id + '\', this, \'' + currentValue.trim() + '\')" onkeyup="feeValueTrigger(event, \'' + feeObject.id + '\', this, \'' + currentValue.trim() + '\')">';
        $RB('#feeText_' + feeId).on("keypress", function (event) {
            return event.keyCode != 13;
        });
        var feeTextObject = $RB('#feeText_' + feeId);
        feeTextObject.focus();
        if (typeof feeTextObject[0] !== "undefined") {
            feeTextObject[0].setSelectionRange(feeTextObject.val().length, feeTextObject.val().length);
        }

    }
}

function resetRbFeeValue(targetColumn, sourceField, oldValue) {
    $RB('#' + targetColumn).html(sourceField.value);
    var feeId = targetColumn.substr(4);
    var convertValue = parseFloat(sourceField.value.replace(',', '.'));

    if (!isNaN(convertValue) && convertValue > 0) {
        $RB('#fee_' + feeId).html(convertValue);
    }

    if (sourceField.value == '' || isNaN(sourceField.value)) {
        $RB('#' + targetColumn).html('<img src="' + adminJs.resursFeePen + '">');
        convertValue = 0;
    }

    if (sourceField.value === oldValue) {
        return;
    }

    if (!isNaN(convertValue)) {
        sourceField.value = convertValue;
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
    } else {
        if (sourceField.value.trim() === '') {
            $RB('#process_' + feeId).html('<div class="labelBoot labelBoot-danger">' + adminJs.useZeroToReset + '</div>');
            $RB('#' + targetColumn).html(oldValue);
        } else {
            $RB('#process_' + feeId).html('<div class="labelBoot labelBoot-danger">' + adminJs.notAllowedValue + '</div>');
            $RB('#' + targetColumn).html(oldValue);
        }
    }
}

function resursRemoveAnnuityElements(notThisElement) {
    var skipThis = "annuitySelector" + notThisElement;
    $RB("select[id*='annuitySelector']").each(function (i, e) {
        if (typeof e.id !== "undefined") {
            if (skipThis != e.id) {
                $RB('#' + e.id).remove();
                var curElementId = e.id.substr(e.id.indexOf('_') + 1);
                $RB('#annuityClick_' + curElementId).removeClass("status-enabled");
                $RB('#annuityClick_' + curElementId).addClass("status-disabled");
            }
        }
    });
}

/**
 * @param response
 */
function getResursRefundCapability(response) {
    if (typeof response["response"]["getRefundCapabilityResponse"] !== "undefined") {
        if (response["response"]["getRefundCapabilityResponse"]["refundable"] === "no") {
            console.log("Current order is not refundable due to method.");
            noRefund();
        }
    }
}
