# 6.1.3

## Updates

* [NETCURL-319](https://tracker.tornevall.net/browse/NETCURL-319) - setSignature may crash setUserAgent if array is sent into merger, [NETCURL-320](https://tracker.tornevall.net/browse/NETCURL-320) opens for overwriting/write protection.
* [NETCURL-323](https://tracker.tornevall.net/browse/NETCURL-323) - Timeout defaults should be flaggable and support millisec, also should native support for timeouts, visually, be passed through the configuration
* [NETCURL-324](https://tracker.tornevall.net/browse/NETCURL-324) - Avoid using flaggables for timeouts in tests

# 6.1.2

## Updates

* [NETCURL-305](https://tracker.tornevall.net/browse/NETCURL-305) - Verify emptyness only
* [NETCURL-308](https://tracker.tornevall.net/browse/NETCURL-308) - Change the way netcurl returns package version information
* [NETCURL-311](https://tracker.tornevall.net/browse/NETCURL-311) - Simplify setCurlHeader again
* [NETCURL-312](https://tracker.tornevall.net/browse/NETCURL-312) - resetCurlRequest() should not reset custom headers on demand
* [NETCURL-313](https://tracker.tornevall.net/browse/NETCURL-313) - Bitbucket pipelines for PHP 8
* [NETCURL-314](https://tracker.tornevall.net/browse/NETCURL-314) - Default internal timeout must be higher than 8 as 4 for connection timeouts is too low

## Fixes

* [NETCURL-302](https://tracker.tornevall.net/browse/NETCURL-302) - Fixed: getParsedResponse is used in rare cases (t-auth)
* [NETCURL-309](https://tracker.tornevall.net/browse/NETCURL-309) - Fixed: Deprecated curldriver is missing proper PHP8 support
* [NETCURL-310](https://tracker.tornevall.net/browse/NETCURL-310) - Fixed: Invisible exit code with unit70 for wsdlcache test
* [NETCURL-316](https://tracker.tornevall.net/browse/NETCURL-316) - Fixed: Fetching version falsely returns wrong version
* [NETCURL-317](https://tracker.tornevall.net/browse/NETCURL-317) - Fixed: Version data falsely reports 6.1.0 when composer.json is missing

# 6.1.1

## Updates

* [NETCURL-301](https://tracker.tornevall.net/browse/NETCURL-301) - Use centralized version checker as most of the libraries are only compatible with 5.6 and above

## Fixes

* [NETCURL-304](https://tracker.tornevall.net/browse/NETCURL-304) - Fixed: memory exhaustion on lowest php memory limit (resources are no longer resources).

# 6.1.0

## Transformed

* [NETCURL-200](https://tracker.tornevall.net/browse/NETCURL-200) - Confirm (by driver) that a driver is really available (update interface with requirements)
* [NETCURL-209](https://tracker.tornevall.net/browse/NETCURL-209) - Support stream, when curl is not an option
* [NETCURL-227](https://tracker.tornevall.net/browse/NETCURL-227) - Migrate: getHttpHost()
* [NETCURL-231](https://tracker.tornevall.net/browse/NETCURL-231) - Move out MODULE_NETWORK to own repo
* [NETCURL-232](https://tracker.tornevall.net/browse/NETCURL-232) - Make exceptions global
* [NETCURL-234](https://tracker.tornevall.net/browse/NETCURL-334) - Support immediate inclusions of network libraries
* [NETCURL-238](https://tracker.tornevall.net/browse/NETCURL-238) - Make setTimeout support ms (curlopt_timeout_ms)
* [NETCURL-242](https://tracker.tornevall.net/browse/NETCURL-242) - Disengage from constructor usage
* [NETCURL-243](https://tracker.tornevall.net/browse/NETCURL-243) - Reimport SSL helper
* [NETCURL-244](https://tracker.tornevall.net/browse/NETCURL-244) - Reimport curl module
* [NETCURL-245](https://tracker.tornevall.net/browse/NETCURL-245) - Reimport soapclient
* [NETCURL-247](https://tracker.tornevall.net/browse/NETCURL-247) - The way we set user agent in the SSL module must be able to set in parents
* [NETCURL-249](https://tracker.tornevall.net/browse/NETCURL-249) - setAuth for curl
* [NETCURL-251](https://tracker.tornevall.net/browse/NETCURL-251) - setAuth for soap
* [NETCURL-255](https://tracker.tornevall.net/browse/NETCURL-255) - Add static list of browsers for user-agent 
* [NETCURL-258](https://tracker.tornevall.net/browse/NETCURL-258) - NetWrapper MultiRequest
* [NETCURL-259](https://tracker.tornevall.net/browse/NETCURL-259) - High focus on curl (rebuild from 6.0)
* [NETCURL-260](https://tracker.tornevall.net/browse/NETCURL-260) - Current curl implementation is only using GET and no advantage of Config
* [NETCURL-261](https://tracker.tornevall.net/browse/NETCURL-261) - Make sure setAuthentication is a required standard in the wrapper interface
* [NETCURL-263](https://tracker.tornevall.net/browse/NETCURL-263) - Add errorhandler for multicurl
* [NETCURL-264](https://tracker.tornevall.net/browse/NETCURL-264) - Avoid static constants inside core functions
* [NETCURL-265](https://tracker.tornevall.net/browse/NETCURL-265) - getCurlException($curlHandle, $httpCode) - httpCode is unused. Throw on >400
* [NETCURL-267](https://tracker.tornevall.net/browse/NETCURL-267) - On http head errors (>400) and non empty bodies
* [NETCURL-268](https://tracker.tornevall.net/browse/NETCURL-268) - Add Timeouts
* [NETCURL-269](https://tracker.tornevall.net/browse/NETCURL-269) - Proxy support for stream_context
* [NETCURL-271](https://tracker.tornevall.net/browse/NETCURL-271) - Synchronize with netcurl 6.0 test suites
* [NETCURL-273](https://tracker.tornevall.net/browse/NETCURL-273) - Support driverless environment
* [NETCURL-276](https://tracker.tornevall.net/browse/NETCURL-276) - Use a natural soapcall with call_user_func_array
* [NETCURL-277](https://tracker.tornevall.net/browse/NETCURL-277) - Netwrapper Compatibility Service
* [NETCURL-278](https://tracker.tornevall.net/browse/NETCURL-278) - setChain in 6.1 should throw errors when requested true
* [NETCURL-279](https://tracker.tornevall.net/browse/NETCURL-279) - Make sure setOption is useful in NetWrapper and MODULE_CURL or work has been useless
* [NETCURL-280](https://tracker.tornevall.net/browse/NETCURL-280) - proxy support for curlwrapper and wrappers that is not stream wrappers
* [NETCURL-283](https://tracker.tornevall.net/browse/NETCURL-283) - Use setSignature (?) to make requesting clients set internal clientname/version as userAgent automatically instead of Mozilla
* [NETCURL-285](https://tracker.tornevall.net/browse/NETCURL-285) - Reinstate Environment but in ConfigWrapper to make wsdl transfers go non-cache vs cache, etc
* [NETCURL-286](https://tracker.tornevall.net/browse/NETCURL-286) - Move driver handler into own class
* [NETCURL-287](https://tracker.tornevall.net/browse/NETCURL-287) - Initialize simplified streamSupport
* [NETCURL-288](https://tracker.tornevall.net/browse/NETCURL-288) - Output support for XML in simpler wrappers
* [NETCURL-289](https://tracker.tornevall.net/browse/NETCURL-289) - Open for third party identification rather than standard browser agent
* [NETCURL-291](https://tracker.tornevall.net/browse/NETCURL-291) - SoapClient must be reinitialized each time it is called
* [NETCURL-292](https://tracker.tornevall.net/browse/NETCURL-292) - Support basic rss+xml via GenericParser
* [NETCURL-293](https://tracker.tornevall.net/browse/NETCURL-293) - Try fix proper rss parsing without garbage
* [NETCURL-294](https://tracker.tornevall.net/browse/NETCURL-294) - Make it possible to initialize an empty curlwrapper (without url)
* [NETCURL-295](https://tracker.tornevall.net/browse/NETCURL-295) - MultiNetwrapper (+Soap)

## Fixes

* [NETCURL-226](https://tracker.tornevall.net/browse/NETCURL-226) - Fixed: PSR4 NetCURL+Network (Phase 1)
* [NETCURL-230](https://tracker.tornevall.net/browse/NETCURL-230) - Fixed: Wordpress driver in prior netcurl is lacking authentication mechanisms
* [NETCURL-246](https://tracker.tornevall.net/browse/NETCURL-246) - Fixed: Pipeline errors for PHP 7.3-7.4
* [NETCURL-272](https://tracker.tornevall.net/browse/NETCURL-272) - Fixed: getSoapEmbeddedRequest() - PHP 5.6+PHP 7.0
* [NETCURL-274](https://tracker.tornevall.net/browse/NETCURL-274) - Fixed: Cached wsdl requests and unauthorized exceptions
