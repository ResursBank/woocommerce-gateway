# NETCURL

[Full documents are located here](https://docs.tornevall.net/x/KwCy)


## Compatibility

This library should be compatible with at least PHP 5.3 up to PHP 7.2.

Observe that SOAP-calls requires SoapClient and PHP streams to be enabled.


## Auto detection of communicators

Using this call before running calls will try to prepare for a proper communications driver. If curl is available, the internal functions will be prioritized before others as this used to be best practice. However, if curl is missing, this might help you find a proper driver automatically.

    $LIB->setDriverAuto();


# NetCurl is and is not (about rebuilding the wheel)

* Licensed under the Apache License, Version 2.0
* NetCurl was not an attempt to rebuild the wheel.
* NetCurl was back in time a regular (yet another) curl-wrapper, with the ability to test simpler input- and output data that was returned from proxies (socks, TOR, etc). The primary goal of the module was to be able to both auto scan different kind of websites for open proxies and then test them. As soon as there was proxies detected, they was added to DNS blacklists..
* NetCurl is a preconfigured wrapper of standard libraries, created to get started as quick as possible without any further knowledge than the phrase "I'd like to get the content of XXX site, in the format of YYY, regardless of what they run and return". Instead of setting up classes, calls and methods, NetCurl activates a state of high verbosity, so that the developer can pick the data freely.
* NetCurl can, if WordPress is present, switch over to the WP_HTTP-class, instead of using internal functions for the calls.
* NetCurl can, if GuzzleHttp is present, switch over to GuzzleHttp to utilize and automatically set up a communication link via this external library. If curl is available, Guzzle will use curl as the first option (which makes this a bit useless). However, if curl is not present, NetCurl can instead utilize the streams driver provided in Guzzle. NetCurl will become a GuzzleWrapper, so to speak.
* NetCurl follows the PHP and curl development and handles certificates with a best practice. It can however, override this and lower security by completely ignore SSL validations.
* NetCurl parses and splits up data in sections of header, response code, body and a pre-parsed data container. The header can normally be viewed as an array, the body contains the received data content as is (raw, untouched), while the parsed data tries to automatically detect content and convert it to readable arrays/objects.
* NetCurl supports SoapClient. It's done by the class Tornevall_simpleSoap. This module has dependencies to xml and SoapClient. SoapCalls are handled automatically from the base class: Adding ?wsdl to the URL fetched by NetCurl, will automatically initiate a session for soap. The call can also be sent without ?wsdl, but in that case you have to tell the module to go SOAP.
* NetCurl supports native basic authentication (curl) and enables similar support when going through a GuzzleStream. For curl, authentication comes for free, while you're pretty much on your own when going stream. NetCurl also makes sure that the SoapClient adopts authentication data set by the setAuthentication()-method.
* NetCurl was back in time more focused to be a "network attack protection tool", rather than a functional network communications tool. Many things has happened since then.


# HOWTOs

## Utilizing external libraries

Want to test this library with an external library like Guzzle? Add this row to composer:

    "guzzlehttp/guzzle": "^6.3"

Then call for this method on initiation:

     $LIB->setDriver( TORNELIB_CURL_DRIVERS::DRIVER_GUZZLEHTTP );
     
or
   
     $LIB->setDriver( TORNELIB_CURL_DRIVERS::DRIVER_GUZZLEHTTP_STREAM );

