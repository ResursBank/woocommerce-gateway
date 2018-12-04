function resursBankCredentialField() {
    var htmlCredentialFields = '<table width="100%">' +
        '<tr>' +
        '<td><input name="resursbank_credentials[]" value=""></td>' +
        '<td><input name="resursbank_credentials[]" value=""></td>' +
        '</tr>' +
        '</table>';
    $resurs_bank('#resurs_bank_credential_set').prepend(htmlCredentialFields);
}