function getResursBankInputField(fieldName) {
    return $resurs_bank('<input>', {name: fieldName});
}

function getResursBankSelectField(fieldName) {
    var selectBox = $resurs_bank('<select>', {name: fieldName});
    $resurs_bank.each({'SE': 'Sverige', 'DK': 'Danmark', 'NO': 'Norge', 'FI': 'Finland'}, function (key, value) {
        selectBox.append(new Option(value, key, false, false));
    });
    return selectBox;
}

function resursBankCredentialField() {
    var cId = parseInt(Math.random() * 1000);
    var row = $resurs_bank('<tr>');
    row.append($resurs_bank('<td>').html(
        getResursBankInputField('resursbank_credentials[' + cId + '][username]', 'Username')).prepend('<b>Username</b><br>')
    );
    row.append($resurs_bank('<td>').html(
        getResursBankInputField('resursbank_credentials[' + cId + '][password]', 'Passsword')).prepend('<b>Password</b><br>')
    );
    row.append($resurs_bank('<td>').html(
        getResursBankSelectField('resursbank_credentials[' + cId + '][country]', 'Country')).prepend('<b>Country</b><br>')
    );
    $resurs_bank('#resurs_bank_credential_table').append(row);
}