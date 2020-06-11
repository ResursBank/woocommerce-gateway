/*!
 * Resurs Bank Payment Gateway for WooCommerce - Scripthandler
 */
var $RB = jQuery.noConflict();
var resursReloadRequired = false;

// Things below wants to be loaded first and not wait for readiness
if (null !== omnivars) {
    var RESURSCHECKOUT_IFRAME_URL = omnivars.RESURSCHECKOUT_IFRAME_URL;
    var ACCEPT_CHECKOUT_PREFIXES = omnivars.ACCEPT_CHECKOUT_PREFIXES;
}
//RESURSCHECKOUT_IFRAME_URL = "*";
var customerTypes = null;
var currentCustomerType = 'NATURAL'; // Default

// WooCommerce trigger for whats happening after payment method updates
$RB(document).on('updated_checkout', function () {
    preSetResursMethods(currentCustomerType.toUpperCase(), resursvars["customerTypes"]);
    if (resursvars['resursCheckoutMultipleMethods'] && resursReloadRequired) {
        $RB('.omniActionsWrapper').show();
        document.location.reload();
    }
});

var rbRefUpdated = false;

$RB(document).ready(function ($) {
    var rb_simpl_checkout_form = $RB('form.checkout');
    if (typeof rb_simpl_checkout_form !== 'undefined' &&
        typeof resursvars !== 'undefined' &&
        resursvars['showCheckoutOverlay'] === "1"
    ) {
        // Bind overlay on demand.
        rb_simpl_checkout_form.on('checkout_place_order', function () {
            $RB('.purchaseActionsWrapper').show();
        });
    }
    $(document.body).on('checkout_error', function () {
        $RB('.purchaseActionsWrapper').hide('medium');
    });

    preSetResursMethods(currentCustomerType.toUpperCase(), resursvars["customerTypes"]);

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
            that.sign_notice = $('<p></p>').addClass('sign_notice').text(getResursPhrase('deliveryRequiresSigning'));

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
            $('#billing_first_name').on('keyup', function () {
                if ($('#applicant-full-name').length > 0) {
                    $('#applicant-full-name').val($("#billing_first_name").val() + " " + $("#billing_last_name").val());
                }
            });
            $('#billing_last_name').on('keyup', function () {
                if ($('#applicant-full-name').length > 0) {
                    $('#applicant-full-name').val($("#billing_first_name").val() + " " + $("#billing_last_name").val());
                }
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
                var charCode = e.keyCode || e.which;
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
            $('#fetch_address_status').show();
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
                    'customerType': currentCustomerType.toUpperCase()
                }
            }).done(function (info) {
                $('#fetch_address_status').hide();

                this.ssnP = $RB('p[class="resurs_ssn_field"]');
                // If the above class finding fails
                if (this.ssnP.length === 0) {
                    this.ssnP = $('#ssn_field_field');
                }
                this.ssnInput = this.ssnP.find('input');
                if (typeof this.ssnP === "undefined") {
                    console.log(getResursPhrase("ssnElementMissing"));
                }

                if (typeof info.error !== 'undefined') {
                    var form = $('form[name="checkout"]');
                    var tempSpan = $('<div></div>', {
                        'class': 'ssn-error-message',
                        'text': info.error
                    });
                    //"form-row form-row-first validate-required woocommerce-invalid woocommerce-invalid-required-field"
                    this.ssnP.append(tempSpan);
                    this.ssnInput.css('border-color', '#fb7f88');
                    $RB('.ssn-error-message').delay('4000').fadeOut('medium');
                } else {
                    $RB('.ssn-error-message').remove();
                    this.ssnInput.css('border-color', '');
                    var customerType = "";
                    /**
                     * Conditional: If there is a customer type to look for, we also look for payment methods, before
                     * continuing. However, if there is neither NATURAL nor LEGAL customer types present on the webpage
                     * it is most certain that the merchant is limited to NATURAL methods only and therefore, the
                     * radio buttons is naturally removed from the site. In THAT case, we should check if both radion
                     * buttons is missing and proceed with getAddress if that is the case.
                     */
                    if (
                        (
                            $RB('#ssnCustomerType' + currentCustomerType.toUpperCase()).length > 0 &&
                            $RB('input[id^="payment_method_resurs_bank"]').length > 0
                        ) ||
                        (
                            $RB('#ssnCustomerTypeNATURAL').length === 0 &&
                            $RB('#ssnCustomerTypeLEGAL').length === 0 &&
                            $RB('input[id^="payment_method_resurs_bank"]').length > 0
                        )
                    ) {
                        var selectedType = $RB('#ssnCustomerType' + currentCustomerType + ':checked');
                        if (selectedType.length > 0) {
                            customerType = selectedType.val();
                        } else {
                            customerType = 'NATURAL';
                        }
                        // Put up as much as possible as default
                        if (typeof info.firstName !== "undefined") {
                            $("#billing_first_name").val(info.firstName);
                        }
                        if (typeof info.lastName !== "undefined") {
                            $("#billing_last_name").val(info.lastName);
                        }
                        if ($('#applicant-full-name').length > 0) {
                            $('#applicant-full-name').val($("#billing_first_name").val() + " " + $("#billing_last_name").val());
                        }
                        $("#billing_address_1").val(typeof info.addressRow1 !== "undefined" ? info.addressRow1 : "");
                        $("#billing_address_2").val(typeof info.addressRow1 !== "undefined" ? info.addressRow2 : "");
                        $("#billing_postcode").val(typeof info.postalCode !== "undefined" ? info.postalCode : "");
                        $("#billing_city").val(typeof info.postalArea !== "undefined" ? info.postalArea : "");

                        $("#applicant-government-id").val($('#ssn_field').val());

                        if (customerType !== "NATURAL") {
                            $("#billing_company").val(info["fullName"]);
                        } else {
                            // Naturals has lastnames, normally - add the rest here
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
});

/**
 * Handle translation tables from WordPress localizer
 *
 * @param phraseName
 * @param countryId
 * @returns {*}
 */
function getResursPhrase(phraseName, countryId) {
    if (typeof rb_getaddress_fields[phraseName] !== "undefined") {
        return rb_getaddress_fields[phraseName];
    } else if (typeof rb_general_translations[phraseName] !== "undefined") {
        return rb_general_translations[phraseName];
    } else {
        // Returning a string instead of the phrase may only be a dumb act.
        return "Lost in translation on phrase '" + phraseName + "'";
    }
}

function getMethodType(customerType) {
    if (typeof customerType == 'undefined') {
        // If customerType is not defined, something is wrong and we will probably not be
        // able to process any data here.
        return;
    }
    var checkedPaymentMethod = null;
    var hasResursMethods = false;

    $RB('body').trigger('update_checkout');

    var currentResursCountry = "";
    var enterNumberPhrase = "";
    var labelNumberPhrase = "";

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
    if ($RB('#ssnCustomerType' + customerType.toUpperCase()).length > 0 && $RB('input[id^="payment_method_resurs_bank"]').length > 0) {
        var selectedType = $RB('#ssnCustomerType' + customerType.toUpperCase() + ':checked');
        if ($RB('#billing_company').length > 0 && $RB('#billing_company').val() !== "") {
            customerType = "legal";
        } else {
            if (selectedType.length > 0) {
                customerType = selectedType.val();
            }
        }
        $RB('input[id^="payment_method_resurs_bank"]').each(
            function (id, obj) {
                hasResursMethods = true;
                if ($RB('#' + obj.id).is(':checked')) {
                    checkedPaymentMethod = obj.value;
                }
            }
        );
        if (customerType != "" && hasResursMethods) {
            preSetResursMethods(customerType, resursvars["customerTypes"]);
        }
    }
}

function preSetResursMethods(customerType, returnedObjects) {
    var hasLegal = false;
    var hasNatural = false;
    var disableGetAddressOptions = "";
    var keepGetAddressOption = "";

    if (typeof returnedObjects["legal"] !== "undefined") {
        if (returnedObjects["legal"].length > 0) {
            hasLegal = true;
        }
    }
    if (typeof returnedObjects["natural"] !== "undefined") {
        if (returnedObjects["natural"].length > 0) {
            hasNatural = true;
        }
    }
    if (!hasLegal) {
        disableGetAddressOptions = "LEGAL";
        keepGetAddressOption = "NATURAL";
    }
    if (!hasNatural) {
        disableGetAddressOptions = "NATURAL";
        keepGetAddressOption = "LEGAL";
    }

    if (disableGetAddressOptions != "") {
        // Make sure the options are removed if there is just one bulk of payment methods
        $RB('[id="ssnCustomerType' + customerType.toUpperCase() + '"]').each(
            function (i, d) {
                if (d.value == disableGetAddressOptions) {
                    if ($RB('#ssnCustomerRadio' + disableGetAddressOptions).length > 0) {
                        $RB('#ssnCustomerRadio' + disableGetAddressOptions).remove();
                        $RB('#ssnCustomerRadio' + keepGetAddressOption).hide();
                    }
                }
            }
        );
    }

    // Only invoke if there are multiple customer types
    if (customerType.toLowerCase() == "natural") {
        var hideCustomerType = "legal";
    } else {
        var hideCustomerType = "natural";
    }
    if (typeof customerType === "undefined") {
        return;
    }
    customerType = customerType.toLowerCase();
    if (hideCustomerType === "legal" && !getResursMethodList(returnedObjects, "natural", true)) {
        // Hiding legal methods when there are not legal methods available, makes the radio buttons
        // not necessary to be shown at all.
        $RB('#ssnCustomerRadioNATURAL').remove();
        $RB('#ssnCustomerRadioLEGAL').remove();
    }
    if (!getResursMethodList(returnedObjects, hideCustomerType, false)) {
        // Switch back to a method list that is still allowed to display themselves.
        if (!getResursMethodList(returnedObjects, customerType.toLowerCase(), false)) {
            console.log('Someone chose to not display any payment method at all. This might be caused by a filter hook.');
        }
    }

    if ($RB('#billing_company').length > 0) {
        var currentCustomerType = $RB('#ssnCustomerType' + customerType.toUpperCase() + ':checked').val();
        if (currentCustomerType === "NATURAL") {
            $RB('#billing_company').val("");
            $RB('#billing_company').prop('readonly', true);
        } else {
            $RB('#billing_company').prop('readonly', false);
        }
    }
}

/**
 * Get a proper list of methods and display them on checkout screen depending on NATURAL or LEGAL.
 *
 * @param returnedObjects
 * @param hideCustomerType
 * @param skipDisplay
 * @returns {boolean}
 */
function getResursMethodList(returnedObjects, hideCustomerType, skipDisplay) {
    var shown = 0;
    var hasShown = false;

    $RB('li[class*=payment_method_resurs]').each(function () {
        var showElm = document.getElementsByClassName(this.className);
        if (showElm.length > 0) {
            for (var showElmCount = 0; showElmCount < showElm.length; showElmCount++) {
                if (showElm[showElmCount].tagName.toLowerCase() === "li") {
                    if (resursMethodIsIn(returnedObjects, showElm[showElmCount], hideCustomerType)) {
                        if (!skipDisplay) {
                            showElm[showElmCount].style.display = "";
                        }
                        shown++;
                    } else {
                        if (!skipDisplay) {
                            showElm[showElmCount].style.display = "none";
                        }
                    }
                }
            }
        }
    });
    if (shown > 0) {
        hasShown = true;
    }
    return hasShown;
}

/**
 * Return true if the "requested" payment method resides in the array that should be tested.
 *
 * @param methods
 * @param currentElm
 * @returns {boolean}
 */
function resursMethodIsIn(methods, currentElm, hideCustomerType) {
    var returnValue = true;
    var foundMethod = false;
    if (currentElm.className.indexOf('_nr_') > -1) {
        var getName = 'resurs_bank_nr_' + currentElm.className.substr(currentElm.className.indexOf('_nr_') + 4);
        if (typeof methods["legal"] !== "undefined") {
            if (hideCustomerType !== 'legal' && $RB.inArray(getName, methods["legal"]) > -1) {
                foundMethod = true;
            }
        }
        if (typeof methods["natural"] !== "undefined") {
            if (hideCustomerType !== 'natural' && $RB.inArray(getName, methods["natural"]) > -1) {
                foundMethod = true;
            }
        }
        if (!foundMethod) {
            returnValue = false;
        }
    }
    return returnValue;
}

function methodChangers(currentSelectionObject) {
    getMethodType($RB('#ssnCustomerType:checked').val());
}

function handleResursCheckoutError(resursErrorMessage) {
    var checkoutForm = $RB('form.checkout');
    if (checkoutForm.length > 0) {
        $RB('.woocommerce-error, .woocommerce-message').remove();
        checkoutForm.prepend('<div class="woocommerce-error">' + resursErrorMessage + '</div>');
        $RB('html, body').animate({
            scrollTop: ($RB('form.checkout').offset().top - 100)
        }, 1000);
    } else {
        /*
         * Fall back on an alert if something went wrong with the page
         */
        alert(resursErrorMessage);
    }
}

function rbFormChange(formFieldName, o) {
    if (formFieldName == "applicant-full-name") {
        // ReInheritage of contact data
        var customerFieldData = o.value.split(" ");
        if (customerFieldData.length > 0) {
            var lastName = customerFieldData[customerFieldData.length - 1];
            customerFieldData.splice(customerFieldData.length - 1, 1);
            var firstName = customerFieldData.join(" ");
            if (firstName != "" && lastName != "") {
                $RB("#billing_first_name").val(firstName);
                $RB("#billing_last_name").val(lastName);
            }
        }
    }
}
