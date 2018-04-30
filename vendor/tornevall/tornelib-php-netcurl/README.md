# Tornevall Networks NETCURL library

[Full documents are located here](https://docs.tornevall.net/x/KwCy)


## Are we rebuilding the wheel here?

Well. Yes. Almost. The other libraries out there, are probably doing the exact same job as we do here. The **problem** with other libraries (that I've found) is amongst others that they are way too big. Taking for example GuzzleHTTP, is for example a huge project if you're aiming to use a smaller project that not requires tons of files to run. They probably covers a bit more solutions than this project, however, we are aiming to make curl usable on as many places as possible in a smaller format. What we are doing here is turning the curl PHP libraries into a very verbose state and with that, returning completed and parsed data to your PHP applications in a way where you don't have to think of this yourself. 


## Compatibility

This library should be compatible with at least PHP 5.3 up to PHP 7.2 (RC1)

## Utilizing external libraries

Want to test this library with an external library like Guzzle? Add this row to composer:

    "guzzlehttp/guzzle": "6.3.0"

Then call for this method on initiation:

     $LIB->setDriver( TORNELIB_CURL_DRIVERS::DRIVER_GUZZLEHTTP );
     
or
   
     $LIB->setDriver( TORNELIB_CURL_DRIVERS::DRIVER_GUZZLEHTTP_STREAM );

Observe that you still need curl if you are running SOAP-calls.


## Auto detection of communicators

Using this call before running calls will try to prepare for a proper communications driver. If curl is available, the internal functions will be prioritized before others as this used to be best practice. However, if curl is missing, this might help you find a proper driver automatically.

    $LIB->setDriverAuto();
