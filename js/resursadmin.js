/**
 * What we work with after page load.
 */
$resurs_bank(document).ready(function ($) {
    resursBankAdminRun();
});

/**
 * Create INPUT field.
 * @param fieldName
 * @returns {*}
 */
function getResursBankInputField(fieldName) {
    return $resurs_bank('<input>', {name: fieldName});
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
        row.append($resurs_bank('<td>').html(getResursBankCheckboxField('resursbank_credentials[' + cId + '][active]')).prepend('<b>Active</b><br>'));
        row.append($resurs_bank('<td>').html(getResursBankInputField('resursbank_credentials[' + cId + '][username]')).prepend('<b>Username</b><br>'));
        row.append($resurs_bank('<td>').html(getResursBankInputField('resursbank_credentials[' + cId + '][password]')).prepend('<b>Password</b><br>'));
        row.append($resurs_bank('<td>').html(
            getResursBankSelectField(
                'resursbank_credentials[' + cId + '][country]', {
                    'SE': 'Sverige',
                    'DK': 'Danmark',
                    'NO': 'Norge',
                    'FI': 'Finland'
                })
            ).prepend('<b>Country</b><br>')
        );
        row.append($resurs_bank('<td>').html(
            getResursBankSelectField(
                'resursbank_credentials[' + cId + '][shopflow]', {
                    'checkout': 'Resurs Checkout',
                    'simplified': 'Simplified ShopFlow',
                    'hosted': 'Hosted ShopFlow'
                })
            ).prepend('<b>Chosen shopflow</b><br>')
        );
        row.append($resurs_bank('<td>').html(
            getResursBankDeleteImage(cId)
        ));
        $resurs_bank('#resurs_bank_credential_table').append(row);
    } else {
        alert('No more countries avaialble');
    }
}

function getResursBankCountryArray(getType, partElement, resursRunFunc) {
    resurs_bank_ajaxify(getType, [], function (res) {
        if (res['code'] >= 205 && res['faultstring'] !== '') {
            $resurs_bank.each(['SE', 'DK', 'NO', 'FI'], function (i, countryCode) {
                $resurs_bank(partElement + countryCode).html(res['faultstring']);
            });
        } else {
            resursRunFunc(res);
        }
    });
}

function resursBankCheckCredentials() {
    var credentialElement = $resurs_bank('#resurs_bank_credential_table');
    if (credentialElement.length > 0) {
        $resurs_bank.each(['SE', 'DK', 'NO', 'FI'], function (i, countryCode) {
            $resurs_bank('#method_list_' + countryCode).append(getResursBankSpinner('method_list_' + countryCode));
            $resurs_bank('#callback_list_' + countryCode).append(getResursBankSpinner('callback_list_' + countryCode));
        });
        getResursBankCountryArray('get_payment_methods', '#method_list_', function (data) {
            if (typeof data['responseAdmin'] === 'object') {
                for (var countryCode in data['responseAdmin']) {
                    console.log(countryCode);
                }
            }
        });
        getResursBankCountryArray('get_registered_callbacks', '#callback_list_', function (data) {
            console.log(data['responseAdmin']);
        });
    }
}

function resursBankAdminRun() {
    resursBankCheckCredentials();
}