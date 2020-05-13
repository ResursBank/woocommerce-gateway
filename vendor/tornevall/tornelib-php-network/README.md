# tornevall/tornelib-php-network

## A prior part of tornevall/tornelib-php-netcurl

Network 6.1.x is an extraction and refactored module that recently was deployed with the netcurl package. The breakout has been made, mainly to make it easier to maintain network functions without destroying functions in netcurl, when it's not necessary.

The module contains - mostly - action points that don't necessarily needs the communication parts from netcurl.

# Requirements

PHP 5.5 or higher.
It might work in lower versions, but since the test suites no longer accept 5.4 this is not officiall supported either.
