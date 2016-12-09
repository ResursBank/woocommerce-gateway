/*!
 * Resurs Bank Payment Gateway for WooCommerce - Scripthandler
 */

var $RB = jQuery.noConflict();

$RB(document).ready(function( $ ) {
    /*
    var currentOmniFrame = document.getElementsByTagName("iframe")[0];
    var omniHostPosition = currentOmniFrame.src.substr(currentOmniFrame.src.indexOf("//")+2);
    var omnicheckoutDomain = omniHostPosition.indexOf("/") > -1 ? "https://" + omniHostPosition.substr(0, omniHostPosition.indexOf("/")) : currentOmniFrame.src;
    */

        if ($RB('#ssnCustomerType').length && $RB('#ssnCustomerType:checked').length > 0) {
            getMethodType($RB('#ssnCustomerType:checked').val());
        }
        $RB('input[id*="payment_method_"]').each(function () {
            if (typeof $RB().live === "function") {
                $RB('#' + this.id).live("click", function () {
                    methodChangers(this);
                });
            } else {
                $RB('#' + this.id).on("click", function () {
                    methodChangers(this);
                });
            }
        });

        woocommerce_resurs_bank = {
            init: function () {
                var that = this;
                that.register_payment_update();
                that.register_ssn_address_fetch();
                that.shipping_address = $('.shipping_address');
                that.sign_notice = $('<p></p>').addClass('sign_notice').text('Ändring av leveransadress kräver e-signering.');

                if ($('.resurs-bank-payment-method').length !== 0) {
                    that.shipping_address.prepend(that.sign_notice);
                } else {
                    if ($(that.shipping_address).find(that.sign_notice).length) {
                        this.sign_notice.remove();
                    }
                }
                $(document).ajaxStop(function () {
                    that.register_payment_update();
                    if ($('.resurs-bank-payment-method').length !== 0) {
                        $('#applicant-government-id').val($('#ssn_field').val());
                        $('#applicant-full-name').val($("#billing_first_name").val() + ' ' + $("#billing_last_name").val());
                        //$('#applicant-telephone-number');
                        $('#applicant-mobile-number').val($("#billing_phone").val());
                        $('#applicant-telephone-number').val($("#billing_phone").val());
                        $('#applicant-email-address').val($("#billing_email").val());
                        that.shipping_address.prepend(that.sign_notice);
                    } else {
                        if ($(that.shipping_address).find(that.sign_notice).length) {
                            that.sign_notice.remove();
                        }
                    }
                });

                $('#billing_email').on('keyup', function () {
                    if ($('#applicant-email-address').length > 0) {
                        $('#applicant-email-address').val($(this).val());
                    }
                });
                $('#billing_phone').on('keyup', function () {
                    if ($('#applicant-telephone-number').length > 0) {
                        $('#applicant-telephone-number').val($(this).val());
                    }
                    if ($('#applicant-mobile-number').length > 0) {
                        $('#applicant-mobile-number').val($(this).val());
                    }
                });

                $('#ssn_field').on('keyup', function () {
                    $('#applicant-government-id').val($(this).val());
                });
            },

            register_payment_update: function () {
                var checked = $('#order_review input[name=payment_method]:checked');
                var parent = $(checked).parent();
                var that = this;
                if (parent.has('.resurs-bank-payment-method').length !== 0) {
                    var methodId = parent.find('.resurs-bank-payment-method').val();
                }

                $('input[name="payment_method"]').on('change', function () {
                    var parent = $(this).parent();
                    var temp = $('.resurs-bank-payment-method');

                    if (parent.has('.resurs-bank-payment-method').length !== 0) {
                        var methodId = parent.find('.resurs-bank-payment-method').val();
                    }

                    $('body').trigger('update_checkout');
                });
            },

            register_ssn_address_fetch: function () {
                var form = $('form.checkout');
                var ssnField = $('#ssn_field');
                var fetchAddressButton = $('#fetch_address');
                var that = this;

                ssnField.keypress(function (e) {
                    var charCode = e.keyCoe || e.which;
                    var input = ssnField.val().trim();

                    if (input.length !== 0) {
                        if (charCode === 13) {
                            fetchAddressButton.click();
                            e.preventDefault();
                            return false;
                        }
                    }
                });

                $(fetchAddressButton).click(function (e) {
                    var input = ssnField.val().trim();
                    var customerType = $(' #ssnCustomerType:checked ').val();
                    if (that.validate_ssn_address_field(input)) {
                        that.fetch_address(input, customerType);
                    }
                    e.preventDefault();
                    return false;
                });
            },

            validate_ssn_address_field: function (ssn) {
                if (ssn.trim().length === 0) {
                    return false;
                }

                return true;
            },

            fetch_address: function (ssn, customerType) {
                /* Make sure the loader url exists, or do not run this part -151202 */
                if (typeof wc_checkout_params.ajax_loader_url !== "undefined") {
                    $('form[name="checkout"]').block({
                        message: null,
                        overlayCSS: {
                            background: '#fff url(' + wc_checkout_params.ajax_loader_url + ') no-repeat center',
                            backgroundSize: '16px 16px',
                            opacity: 0.6
                        }
                    });
                }

                $.ajax({
                    type: 'GET',
                    url: ajax_object.ajax_url,
                    data: {
                        'action': 'get_address_ajax',
                        'ssn': ssn,
                        'customerType': customerType
                    }
                }).done(function (data) {
                    data = $.parseJSON(data);
                    var info = data;

                    this.ssnP = $('p[class="resurs_ssn_field"]');
                    // If the above class finding fails
                    if (this.ssnP.length === 0) {
                        this.ssnP = $('#ssn_field_field');
                    }
                    this.ssnInput = this.ssnP.find('input');
                    if (typeof this.ssnP === "undefined") {
                        console.log("I can not show errors since the element is missing");
                    }

                    if (typeof data.error !== 'undefined') {
                        var form = $('form[name="checkout"]');
                        var tempSpan = $('<span></span>', {
                            'class': 'ssn-error-message',
                            'text': data.error
                        });
                        //"form-row form-row-first validate-required woocommerce-invalid woocommerce-invalid-required-field"
                        this.ssnP.append(tempSpan);
                        this.ssnInput.css('border-color', '#fb7f88');
                        this.ssnErrorTimeout = window.setTimeout(function () {
                            $('.ssn-error-message').fadeOut('slow', function () {
                                $(this.parent).find('input').css('border-color', '');
                                $(this).remove();
                            });
                            $('p[class="form-row ssn form-row-wide woocommerce-validated"]').find('input').css('border-color', '');
                        }, 5000);
                    } else {
                        $('.ssn-error-message').remove();
                        this.ssnInput.css('border-color', '');
                        var customerType = "";
                        if ($RB('#ssnCustomerType').length > 0 && $RB('input[id^="payment_method_resurs_bank"]').length > 0) {
                            var selectedType = $RB('#ssnCustomerType:checked');
                            customerType = selectedType.val();
                            /* Put up as much as possible as default */
                            $("#billing_first_name").val(typeof info.firstName !== "undefined" ? info.firstName : "");
                            $("#billing_last_name").val(typeof info.lastName !== "undefined" ? info.lastName : "");
                            $("#billing_address_1").val(typeof info.addressRow1 !== "undefined" ? info.addressRow1 : "");
                            $("#billing_address_2").val(typeof info.addressRow1 !== "undefined" ? info.addressRow2 : "");
                            $("#billing_postcode").val(typeof info.postalCode !== "undefined" ? info.postalCode : "");
                            $("#billing_city").val(typeof info.postalArea !== "undefined" ? info.postalArea : "");

                            $("#applicant-government-id").val($('#ssn_field').val());

                            if (customerType !== "NATURAL") {
                                /* If this is a company, add available content to right location field */
                                if (typeof info.lastName === "undefined") {
                                    $("#billing_company").val(info.firstName);
                                } else {
                                    $("#billing_company").val(info.firstName + " " + info.lastName);
                                }
                            } else {
                                /* Naturals has lastnames, normally - add the rest here */
                                $("#billing_last_name").val(typeof info.lastName !== "undefined" ? info.lastName : "");
                            }
                        }

                        $('form[name="checkout"]').unblock();
                    }
                })
            },
            remove_first_checkout_button: function () {

                $('.checkout-button').each(function (index, value) {

                    var $value = $(value);

                    if (!$value.hasClass('second-checkout-button'))
                        $value.hide();
                });
            }
        };
        woocommerce_resurs_bank.init();

    }
);

function getResursPhrase(phraseName, countryId) {
    if (typeof rb_getaddress_fields[phraseName] !== "undefined") {
        return rb_getaddress_fields[phraseName];
    } else {
        return "Lost in translation";
    }
}

function getMethodType(customerType) {
    var checkedPaymentMethod = null;
    var hasResursMethods = false;

    var currentResursCountry = "";
    var currentCustomerType = "";
    var enterNumberPhrase = "";
    var labelNumberPhrase = "";

    /*
     var methodCountry = $RB('#resursSelectedCountry');
     if (methodCountry.val() != "SE") {
     $RB('#ssn_field_field').hide();
     } else {
     $RB('#ssn_field_field').show();
     }
     */

    if ($RB('.nm-checkout-login-coupon') > 0) {
        console.log("here");
    }

    if ($RB('#resursSelectedCountry').length > 0 && $RB('#ssn_field').length > 0) {
        currentResursCountry = $RB('#resursSelectedCountry').val();
        currentCustomerType = customerType.toLowerCase();
        if (currentCustomerType === "natural") {
            enterNumberPhrase = getResursPhrase("getAddressEnterGovernmentId", currentResursCountry);
            labelNumberPhrase = getResursPhrase("labelGovernmentId", currentResursCountry);
        } else {
            enterNumberPhrase = getResursPhrase("getAddressEnterCompany", currentResursCountry);
            labelNumberPhrase = getResursPhrase("labelCompanyId", currentResursCountry);
        }
        $RB('#ssn_field').attr("placeholder", enterNumberPhrase);
        $RB("label[for*='ssn_field']").html(labelNumberPhrase);
    }

    if ($RB('#ssnCustomerType').length > 0 && $RB('input[id^="payment_method_resurs_bank"]').length > 0) {

        var selectedType = $RB('#ssnCustomerType:checked');
        if ($RB('#billing_company').length > 0 && $RB('#billing_company').val() !== "") {
            customerType = "legal";
        } else {
            if (selectedType.length > 0) {
                customerType = selectedType.val();
            }
        }
        $RB('input[id^="payment_method_resurs_bank"]').each(
            function(id, obj) {
                hasResursMethods = true;
                if ($RB('#' + obj.id).is(':checked')) {
                    checkedPaymentMethod = obj.value;
                }
            }
        );
        if (customerType != "" && hasResursMethods) {
            $RB.ajax({
                type: 'GET',
                url: ajax_object.ajax_url,
                data: {
                    'action': 'get_address_customertype',
                    'customerType': customerType,
                    'paymentMethod': checkedPaymentMethod
                }
            }).done(function (data) {
                preSetResursMethods(customerType, data);
            });
        }
    }
}

function ResursRegexMatch(objectBound, regEx) {}

function preSetResursMethods(customerType, returnedObjects) {
    var hideElm;
    var showElm;

    // Only invoke if there are multiple customer types
    if (customerType.toLowerCase() == "natural") {var hideCustomerType = "legal";} else {var hideCustomerType = "natural";}
    if (typeof customerType === "undefined") {return;}
    customerType = customerType.toLowerCase();

    if ($RB('#ssnCustomerType:checked').length === 0 && ($RB('#billing_company').length > 0 && $RB('#billing_company').val() == "")) {
        /* The moment when we cannot predict the method of choice, we'll show both methods */
        $RB('li[class*=payment_method_resurs]').each(function() {
            showElm = document.getElementsByClassName(this.className);
            if (showElm.length > 0) {
                for (var showElmCount = 0; showElmCount < showElm.length; showElmCount++) {
                    if (showElm[showElmCount].tagName.toLowerCase() === "li") {
                        showElm[showElmCount].style.display = "";
                    }
                }
            }
        });
    }
    else
    {
        if (typeof returnedObjects['natural'] !== "undefined" && typeof returnedObjects['legal'] !== "undefined" && typeof returnedObjects[hideCustomerType] !== "undefined") {
            for (var cType = 0; cType < returnedObjects[hideCustomerType].length; cType++) {
                hideElm = document.getElementsByClassName('payment_method_' + returnedObjects[hideCustomerType][cType]);
                if (hideElm.length > 0) {
                    for (var hideElmCount = 0; hideElmCount < hideElm.length; hideElmCount++) {
                        if (hideElm[hideElmCount].tagName.toLowerCase() === "li") {
                            for (var getChild = 0; getChild < hideElm[hideElmCount].childNodes.length ; getChild ++) {
                                if (typeof hideElm[hideElmCount].childNodes[getChild].type !== "undefined" && hideElm[hideElmCount].childNodes[getChild].type === "radio") {
                                    // Unselect this radio buttons if found, just to make sure no method are chosen in a moment like this
                                    hideElm[hideElmCount].childNodes[getChild].checked = false;
                                }
                            }
                            hideElm[hideElmCount].style.display = "none";
                        }
                    }
                }
            }
            for (var cType = 0; cType < returnedObjects[customerType].length; cType++) {
                showElm = document.getElementsByClassName('payment_method_' + returnedObjects[customerType][cType]);
                if (showElm.length > 0) {
                    for (var showElmCount = 0; showElmCount < showElm.length; showElmCount++) {
                        if (showElm[showElmCount].tagName.toLowerCase() === "li") {
                            showElm[showElmCount].style.display = "";
                        }
                    }
                }
            }
        }
    }
    if ($RB('#billing_company').length > 0) {
        var currentCustomerType = $RB('#ssnCustomerType:checked').val();
        if (currentCustomerType === "NATURAL") {
            $RB('#billing_company').val("");
            $RB('#billing_company').prop('readonly', true);
        } else {
            $RB('#billing_company').prop('readonly', false);
        }
    }
}

function methodChangers(currentSelectionObject) {
    getMethodType($RB('#ssnCustomerType:checked').val());
}

/*
 * Things below wants to be loaded first and not wait for readiness
 */
if (null !== omnivars) {
    var RESURSCHECKOUT_IFRAME_URL = omnivars.RESURSCHECKOUT_IFRAME_URL;
}
if (typeof ResursCheckout !== "undefined") {
    var resursCheckout = ResursCheckout('#resurs-checkout-container');
    resursCheckout.setDebug(1);
    resursCheckout.init();
    resursCheckout.setPurchaseFailCallback(function() {
        handleResursCheckoutError("The purchase from Resurs Bank was by some reason not accepted. Please contact customer services, or try again with another payment method");
    });
    resursCheckout.setBookingCallback(function(omniJsObject) {
        var omniRef = omnivars.OmniRef;
        var currentResursCheckoutFrame = document.getElementsByTagName("iframe")[0];

        var postData = {};
        /*
         * Merge the rest
         */
        $RB('[name*="checkout"] input').each(
            function (i, e) {
                if (typeof e.name !== "undefined") {
                    if (e.type == "checkbox") {
                        if (e.checked === true) {
                            omniJsObject[e.name] = e.value;
                        } else {
                            omniJsObject[e.name] = 0;
                        }
                    } else if (e.type == "radio") {
                        omniJsObject[e.name] = $RB('[name="'+e.name+'"]:checked').val();
                    } else {
                        omniJsObject[e.name] = e.value;
                    }
                }
            }
        );
        if (typeof currentResursCheckoutFrame !== "undefined" && typeof currentResursCheckoutFrame.src !== "undefined") {
            if (omniRef != "" && typeof omnivars.OmniPreBookUrl !== "undefined") {
                var preBookUrl = omnivars.OmniPreBookUrl + "&orderRef=" + omniRef;
                $RB.ajax(
                    {
                        url: preBookUrl,
                        type: "POST",
                        data: omniJsObject
                    }
                ).success(
                    function (successData) {
                        var errorString = "";
                        var isSuccess = false;
                        if (typeof successData.success !== "undefined") {
                            if (successData.success === true) {
                                isSuccess = true;
                                resursCheckout.confirmOrder(true);
                                return true;
                            }
                        }
                        errorString = (typeof successData.errorString !== "undefined" ? successData.errorString : "");
                        if (!isSuccess) {
                            if (errorString === "" || errorString === null) {
                                errorString = "Something went wrong when we tried to book your order. Please contact customer support for more information.";
                            }
                            handleResursCheckoutError(errorString);
                        }
                        resursCheckout.confirmOrder(false);
                        return false;
                    }
                ).fail(
                    function (x, y) {
                        resursCheckout.confirmOrder(false);
                        var errorString = "Something went wrong. Please contact customer support for more information.";
                        if (typeof x.statusText !== "undefined") {
                            errorString = x.statusText;
                        }
                        handleResursCheckoutError("BookByAJAXError: " + errorString);
                        return false;
                    }
                );
            }
        }
    });
}

function handleResursCheckoutError(resursErrorMessage) {
    var checkoutForm = $RB('form.checkout');
    if (checkoutForm.length > 0) {
        $RB('.woocommerce-error, .woocommerce-message').remove();
        checkoutForm.prepend('<div class="woocommerce-error">' + resursErrorMessage + '</div>');
        $RB('html, body').animate({
            scrollTop: ( $RB('form.checkout').offset().top - 100 )
        }, 1000);
    } else {
        /*
         * Fall back on an alert if something went wrong with the page
         */
        alert(resursErrorMessage);
    }
}


