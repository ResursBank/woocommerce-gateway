var resursBankCurrentEnvironment = '';

// What we work with after page load.
$resurs_bank(document).ready(function ($) {
    resursBankAdminRun();
});

function resursBankAdminRun() {
    resursBankCheckCredentials();
}

/**
 * Create INPUT field.
 * @param fieldName
 * @returns {*}
 */
function getResursBankInputField(fieldName) {
    return $resurs_bank('<input>', {name: fieldName, size: '24', 'class': 'resursCredentialsDataField'});
}

/**
 * Make sure that there will be no duplicates of a country.
 *
 * @param countryCode
 * @returns {boolean}
 */
function getResursBankTakenCountry(countryCode) {
    var hasTheTakenCountry = false;

    $resurs_bank('select').each(function (selIndex, selField) {
        if (selField.name.indexOf('[country]')) {
            if (selField.value === countryCode) {
                hasTheTakenCountry = true;
            }
        }
    });

    return hasTheTakenCountry;
}

/**
 * Create a SELECT box with selectable countries for a flow.
 *
 * @param fieldName
 * @returns {*}
 */
function getResursBankSelectField(fieldName, optionList) {
    var selectBox = $resurs_bank('<select>', {name: fieldName});
    $resurs_bank.each(optionList, function (key, value) {
        if ((fieldName.indexOf('[country]') > -1 && !getResursBankTakenCountry(key)) || fieldName.indexOf('[country]') === -1) {
            selectBox.append(new Option(value, key, false, false));
        }
    });
    return selectBox;
}

/**
 * Show spinner in a element
 *
 * @param fieldName
 * @returns {*}
 */
function getResursBankSpinner(fieldName) {
    return $resurs_bank('<img>', {id: fieldName + '_spin', src: resurs_bank_payment_gateway['graphics']['spinner']});
}

/**
 * Create checkbox
 *
 * @param fieldName
 * @returns {*}
 */
function getResursBankCheckboxField(fieldName) {
    return $resurs_bank('<input>', {name: fieldName, type: 'checkbox'});
}


/**
 * Create a clickable "delete" image.
 *
 * @param fieldRow
 * @returns {*}
 */
function getResursBankDeleteImage(fieldRow) {
    return $resurs_bank('<img>', {
        style: 'cursor:pointer',
        src: resurs_bank_payment_gateway['graphics']['delete']
    }).on('click', function () {
        $resurs_bank('#resursbank_credential_row_' + fieldRow).remove();
    });
}

/**
 * Create forms for credentials
 */
function resursBankCredentialField() {
    var countriesAvailable = 0;

    $resurs_bank.each(['SE', 'DK', 'NO', 'FI'], function (i, countryCode) {
        if (!getResursBankTakenCountry(countryCode)) {
            countriesAvailable++;
        }
    });

    if (countriesAvailable > 0) {
        var cId = parseInt(Math.random() * 1000);
        var row = $resurs_bank('<tr>', {id: 'resursbank_credential_row_' + cId});
        row.append($resurs_bank('<td>').html(getResursBankCheckboxField('resursbank_credentials[' + cId + '][test][active]')).prepend('<b>Active</b><br>'));
        row.append($resurs_bank('<td>').html(getResursBankInputField('resursbank_credentials[' + cId + '][test][username]')).prepend('<b>Username (Test)</b><br>'));
        row.append($resurs_bank('<td>').html(getResursBankInputField('resursbank_credentials[' + cId + '][test][password]')).prepend('<b>Password (test)</b><br>'));
        row.append($resurs_bank('<td>').html(getResursBankInputField('resursbank_credentials[' + cId + '][live][username]')).prepend('<b>Username (Live)</b><br>'));
        row.append($resurs_bank('<td>').html(getResursBankInputField('resursbank_credentials[' + cId + '][live][password]')).prepend('<b>Password (Live)</b><br>'));
        row.append($resurs_bank('<td>').html(
            getResursBankSelectField(
                'resursbank_credentials[' + cId + '][country]',
                {
                    'SE': 'Sverige',
                    'DK': 'Danmark',
                    'NO': 'Norge',
                    'FI': 'Finland'
                }
            )
        ).prepend('<b>Country</b><br>'));
        row.append($resurs_bank('<td>').html(
            getResursBankSelectField(
                'resursbank_credentials[' + cId + '][shopflow]',
                {
                    'checkout': 'Resurs Checkout',
                    'simplified': 'Simplified ShopFlow',
                    'hosted': 'Hosted ShopFlow'
                }
            )
        ).prepend('<b>Chosen shopflow</b><br>'));
        row.append($resurs_bank('<td>').html(
            getResursBankDeleteImage(cId)
        ));
        $resurs_bank('#resurs_bank_credential_table').append(row);
    } else {
        alert('No more countries avaialble');
    }
}

function getResursBankCurrentEnvironment() {
    resursBankCurrentEnvironment = $resurs_bank('#resursbank_environment').val();
    return resursBankCurrentEnvironment
}

function getResursBankCountryArray(getType, partElement, resursRunFunc) {
    resurs_bank_ajaxify(getType, {'environment': getResursBankCurrentEnvironment}, function (res) {
        if (res['code'] >= 205 && res['faultstring'] !== '') {
            $resurs_bank.each(['SE', 'DK', 'NO', 'FI'], function (i, countryCode) {
                $resurs_bank(partElement + countryCode).html(res['faultstring']);
            });
        } else {
            resursRunFunc(res);
        }
    });
}

function resursBankHideAndShowElements(toShow, toHide) {
    $resurs_bank(toShow).show();
    $resurs_bank(toHide).hide();
}

function resursBankDisplayCredentials() {
    var currentEnvironment = getResursBankCurrentEnvironment();

    var notCurrentEnvironment = 'test';
    if (currentEnvironment === 'test') {
        notCurrentEnvironment = 'live';
    }
    $resurs_bank.each(['SE', 'DK', 'NO', 'FI'], function (i, countryCode) {
        resursBankHideAndShowElements('#credentials_' + currentEnvironment + '_username_' + countryCode, '#credentials_' + notCurrentEnvironment + '_username_' + countryCode);
        resursBankHideAndShowElements('#credentials_' + currentEnvironment + '_password_' + countryCode, '#credentials_' + notCurrentEnvironment + '_password_' + countryCode);
    });
}

function resursBankCredentialsUpdate() {
    resursBankDisplayCredentials();
    resursBankCheckCredentials();
}

function resursBankGetMethodContent(methodObject, tag) {
    return $resurs_bank('<td>', {style: 'padding: 0px;'}).html(methodObject[tag]);
}

function resursBankGetMethodData(methodObject) {
    var paymentMethodRow = $resurs_bank('<tr>');
    $resurs_bank.each(['id', 'title', 'description'], function (idx, tag) {
        paymentMethodRow.append(resursBankGetMethodContent(methodObject, tag));
    });
    return paymentMethodRow;
}

function resursBankCheckCredentials() {
    var credentialElement = $resurs_bank('#resurs_bank_credential_table');
    var currentEnvironment = getResursBankCurrentEnvironment();

    if (credentialElement.length > 0) {
        resursBankDisplayCredentials();
        $resurs_bank.each(['SE', 'DK', 'NO', 'FI'], function (i, countryCode) {
            $resurs_bank('#method_list_' + countryCode).html(getResursBankSpinner('method_list_' + countryCode));
            //$resurs_bank('#callback_list_' + countryCode).html(getResursBankSpinner('callback_list_' + countryCode));
        });
        getResursBankCountryArray('get_payment_methods', '#method_list_', function (data) {
            if (typeof data['responseAdmin'] === 'object') {
                for (var countryCode in data['responseAdmin']) {
                    if (typeof data['responseAdmin'][countryCode][currentEnvironment] === 'object') {
                        var methodTable = $resurs_bank('<table>', {
                            style: 'padding: 0px; border: 1px dashed gray',
                            width: '100%'
                        });
                        var resursMethodObject = data['responseAdmin'][countryCode][currentEnvironment];
                        for (var methodIndex = 0; methodIndex < resursMethodObject.length; methodIndex++) {
                            methodTable.append(resursBankGetMethodData(resursMethodObject[methodIndex]));
                        }
                        $resurs_bank('#method_list_' + countryCode).html(methodTable);
                    }
                }
            }
            $resurs_bank.each(['SE', 'DK', 'NO', 'FI'], function (i, countryCode) {
                if ($resurs_bank('#method_list_' + countryCode + '_spin').length === 1) {
                    $resurs_bank('#method_list_' + countryCode).html('');
                }
            });
        });
        getResursBankCountryArray('get_registered_callbacks', '#callback_list_', function (data) {
            if (typeof data['responseAdmin'] === 'object') {
            }
        });
    }
}
