$resurs_bank(document).ready(function ($) {
    resursBankAdminRun();
});

function getResursBankInputField(fieldName) {
    return $resurs_bank('<input>', {name: fieldName});
}

var resursBankAvailableCountries = {'SE': 'Sverige', 'DK': 'Danmark', 'NO': 'Norge', 'FI': 'Finland'};

function getResursBankTakenCountry(country) {
    var hasTheTakenCountry = false;

    $resurs_bank('select').each(function (selIndex, selField) {
        if (selField.name.indexOf('[country]')) {
            if (selField.value === country) {
                hasTheTakenCountry = true;
            }
        }
    });

    return hasTheTakenCountry;
}

function getResursBankSelectField(fieldName) {
    var selectBox = $resurs_bank('<select>', {name: fieldName});
    $resurs_bank.each(resursBankAvailableCountries, function (key, value) {
        if (!getResursBankTakenCountry(key)) {
            selectBox.append(new Option(value, key, false, false));
        }
    });
    return selectBox;
}

function getResursBankDeleteImage(fieldRow) {
    return $resurs_bank('<img>', {
        style: 'cursor:pointer',
        src: resurs_bank_payment_gateway['graphics']['delete']
    }).on('click', function () {
        $resurs_bank('#resursbank_credential_row_' + fieldRow).remove();
    });
}

function resursBankCredentialField() {
    var cId = parseInt(Math.random() * 1000);
    var row = $resurs_bank('<tr>', {id: 'resursbank_credential_row_' + cId});
    row.append($resurs_bank('<td>').html(
        getResursBankInputField('resursbank_credentials[' + cId + '][username]', 'Username')).prepend('<b>Username</b><br>')
    );
    row.append($resurs_bank('<td>').html(
        getResursBankInputField('resursbank_credentials[' + cId + '][password]', 'Passsword')).prepend('<b>Password</b><br>')
    );
    row.append($resurs_bank('<td>').html(
        getResursBankSelectField('resursbank_credentials[' + cId + '][country]', 'Country')).prepend('<b>Country</b><br>')
    );
    row.append($resurs_bank('<td>').html(
        getResursBankDeleteImage(cId)
    ));
    $resurs_bank('#resurs_bank_credential_table').append(row);
}

function getResursBankMethods(country) {

}

function resursBankCheckCredentials() {
    var credentialElement = $resurs_bank('#resurs_bank_credential_table');
    if (credentialElement.length > 0) {
        credentialElement.find('td').each(function (idx, field) {
            console.dir(field.id);
        });
    }
}

function resursBankAdminRun() {
    resursBankCheckCredentials();
}