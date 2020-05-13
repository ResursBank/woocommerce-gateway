# tornevall/tornelib-php-crypto v6.1

Encryption helper library that helps to standardize encrypted data over (amongst others) http links.

Most of the encrypted data used in this library will be encoded into strings that can be handled over http networks. There are also compression handled through this library that could be used in the same way.

## Where is MODULE_IO?

The module that handled unencrypted strings and data has been moved to tornevall/tornelib-php-io - this module have a dependency to it, since "crypto" was the first version that contained both modules. To avoid problems, this module keeps importing it via composer.
