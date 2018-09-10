<?php

namespace TorneLIB;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once(__DIR__ . '/../vendor/autoload.php');
}
if (file_exists(__DIR__ . "/../tornelib.php")) {
    // Work with TorneLIBv5
    /** @noinspection PhpIncludeInspection */
    require_once(__DIR__ . '/../tornelib.php');
}

use PHPUnit\Framework\TestCase;

ini_set('memory_limit', -1);    // Free memory limit, some tests requires more memory (like ip-range handling)

/** @noinspection PhpUndefinedClassInspection */

class curlTest extends TestCase
{
    /**
     * @var MODULE_CURL $CURL
     */
    private $CURL;
    private $username = "ecomphpPipelineTest";
    private $password = "4Em4r5ZQ98x3891D6C19L96TQ72HsisD";
    private $wsdl = "https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService?wsdl";

    /**
     * @test
     * @testdox Testing an error type that comes from this specific service - testing if we can catch previous error
     *          instead of the current
     * @throws \Exception
     */
    public function soapFaultstring()
    {
        $this->disableSslVerifyByPhpVersions(true);
        try {
            $wsdl = $this->CURL->doGet($this->wsdl);
            /** @noinspection PhpUndefinedMethodInspection */
            $wsdl->getPaymentMethods();
        } catch (\Exception $e) {
            $previousException = $e->getPrevious();

            if ($e->getCode() < 3) {
                static::markTestSkipped(
                    'Getting exception codes below 3 here, might indicate that your cacerts is not installed properly or the connection to the server is not responding'
                );

                return;
            } elseif ($e->getCode() >= 500) {
                static::fail(
                    "Got errors (" . $e->getCode() . ") on URL call, can't complete request: " . $e->getMessage()
                );
            }

            static::assertTrue(
                isset($previousException->faultstring) &&
                !empty($previousException->faultstring) &&
                preg_match(
                    "/unauthorized/i",
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * In some versions of PHP SSL verification failes with routines:SSL3_GET_SERVER_CERTIFICATE:certificate.
     * For the tests, where the importance of result is not focused on SSL, we could disable the verification
     * checks if we want to do so. In Bitbucket Pipelines docker environments errors has been discovered on
     * some PHP releases, which we'd like to primary disable.
     * @param bool $always
     */
    private function disableSslVerifyByPhpVersions($always = false)
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<=')) {
            $this->CURL->setSslVerify(false, false);
        } elseif ($always) {
            $this->CURL->setSslVerify(false, false);
        }
    }

    /**
     * @test
     * @testdox Testing unauthorized request by regular request (should give the same repsonse as soapFaultString)
     * @throws \Exception
     */
    public function soapUnauthorizedSoapUnauthorized()
    {
        try {
            $this->disableSslVerifyByPhpVersions();
            $wsdl = $this->CURL->doGet($this->wsdl);
            /** @noinspection PhpUndefinedMethodInspection */
            $wsdl->getPaymentMethods();
        } catch (\Exception $e) {
            $exMessage = $e->getMessage();
            $exCode = $e->getCode();

            $isUnCode = $exCode == 401 ? true : false;
            $isUnText = preg_match("/unauthorized/i", $exMessage) ? true : false;

            if ((intval($exCode) <= 3 && intval($exCode) > 0) || preg_match("/14090086/", $exMessage)) {
                static::markTestSkipped(
                    'Getting exception codes below 3 here, might indicate that your cacerts is not installed properly or the connection to the server is not responding'
                );

                return;
            } elseif ($exCode >= 500) {
                static::markTestSkipped(
                    "Got errors (" . $e->getCode() . ") on URL call, can't complete request: " . $e->getMessage()
                );
            }

            static::assertTrue(($isUnCode || $isUnText) ? true : false);
        }
    }

    /**
     * @test
     * @testdox Test soapfaults when authentication are set up (as this generates other errors than without auth set)
     * @throws \Exception
     */
    public function soapAuthErrorInitialSoapFaultsWsdl()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $this->CURL->setChain(false);
            $this->CURL->setFlag('SOAPCHAIN', false);
        }
        $this->CURL->setAuthentication("fail", "fail");
        $this->disableSslVerifyByPhpVersions();
        // SOAPWARNINGS is set true by default on authentication activation
        try {
            $wsdl = $this->CURL->doGet($this->wsdl);
            /** @noinspection PhpUndefinedMethodInspection */
            $wsdl->getPaymentMethods();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();

            if (preg_match('/this when not in object context/i', $errorMessage)) {
                static::markTestSkipped('This test might not support chaining: ' . $errorMessage);

                return;
            }

            $assertThis = false;
            if (intval($errorCode) == 401) {
                $assertThis = true;
            }
            if (intval($errorCode) == 2) {
                static::markTestSkipped(
                    "Possible SSL3_GET_SERVER_CERTIFICATE - If you run this test, make sure the certificate verification works"
                );

                return;
            } elseif ($errorCode >= 500) {
                static::fail(
                    "Got errors (" . $e->getCode() . ") on URL call, can't complete request: " . $e->getMessage()
                );
            }
            static::assertTrue($assertThis, $errorMessage . " (" . $errorCode . ")");
        }
    }

    /**
     * @test
     * @testdox Post as SOAP, without the wsdl prefix
     * @throws \Exception
     */
    public function soapAuthErrorInitialSoapFaultsNoWsdl()
    {
        $this->disableSslVerifyByPhpVersions();
        $this->CURL->setSoapTryOnce(false);
        $this->CURL->setAuthentication("fail", "fail");
        try {
            $wsdl = $this->CURL->doGet(
                'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService',
                NETCURL_POST_DATATYPES::DATATYPE_SOAP
            );
            /** @noinspection PhpUndefinedMethodInspection */
            $wsdl->getPaymentMethods();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();

            if (preg_match('/this when not in object context/i', $errorMessage)) {
                static::markTestSkipped('This test might not support chaining: ' . $errorMessage);

                return;
            }

            $assertThis = false;
            if ($errorCode == 401) {
                $assertThis = true;
            }
            if (intval($errorCode) == 2) {
                static::markTestSkipped(
                    "Possible SSL3_GET_SERVER_CERTIFICATE - If you run this test, make sure the certificate verification works"
                );

                return;
            } elseif ($errorCode >= 500) {
                static::fail(
                    "Got errors (" . $e->getCode() . ") on URL call, can't complete request: " . $e->getMessage()
                );
            }

            static::assertTrue($assertThis, $errorMessage . " (" . $errorCode . ")");
        }
    }

    /**
     * @test
     * @testdox Running "old style failing authentication mode" should generate blind errors like here
     * @throws \Exception
     */
    public function soapAuthErrorWithoutProtectiveFlag()
    {
        $this->CURL->setAuthentication("fail", "fail");
        $this->CURL->setFlag("NOSOAPWARNINGS");
        try {
            $this->CURL->doGet($this->wsdl);
        } catch (\Exception $e) {
            // As of 6.0.16, SOAPWARNINGS are always enabled. Setting NOSOAPWARNINGS in flags,
            // will render blind errors since the authentication errors are located in uncatchable warnings
            $errorCode = $e->getCode();
            if (version_compare(TORNELIB_NETCURL_RELEASE, "6.0.20", '>=')) {
                static::assertTrue($errorCode == 500 ? true : false);
            } else {
                // For older versions than 6.0.20, we can't turn SOAPWARNINGS off prorperly,
                // so in those versions our errorcode 401 remains
                static::assertTrue($errorCode == 401 ? true : false);
            }
        }
    }

    /**
     * @test
     * @testdox
     * @throws \Exception
     */
    public function soapAuthErrorNoInitialSoapFaultsNoWsdl()
    {
        $this->disableSslVerifyByPhpVersions();
        $this->CURL->setAuthentication("fail", "fail");
        try {
            $this->CURL->doGet(
                'https://test.resurs.com/ecommerce-test/ws/V4/SimplifiedShopFlowService',
                NETCURL_POST_DATATYPES::DATATYPE_SOAP
            );
        } catch (\Exception $e) {
            // As of 6.0.16, this is the default behaviour even when SOAPWARNINGS are not active by setFlag
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();

            if (preg_match('/this when not in object context/i', $errorMessage)) {
                static::markTestSkipped('This test might not support chaining: ' . $errorMessage);

                return;
            }

            $assertThis = false;
            if ($errorCode == 401) {
                $assertThis = true;
            }
            if (intval($errorCode) == 2) {
                static::markTestSkipped(
                    "Possible SSL3_GET_SERVER_CERTIFICATE - If you run this test, make sure the certificate verification works"
                );

                return;
            } elseif ($errorCode >= 500) {
                static::fail(
                    "Got errors (" . $e->getCode() . ") on URL call, can't complete request: " . $e->getMessage()
                );
            }

            static::assertTrue($assertThis, $errorMessage . " (" . $errorCode . ")");
        }
    }

    /**
     * @test
     * @testdox Test invalid function
     * @throws \Exception
     */
    public function rbFailSoapChain()
    {
        $this->CURL->setFlag("SOAPCHAIN");
        $this->CURL->setAuthentication($this->username, $this->password);
        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->CURL->doGet($this->wsdl)->getPaymentMethodz();
        } catch (\Exception $e) {
            static::assertTrue($e->getMessage() != "");
        }
    }

    // Deprecated: rbSoapBackToNoChain

    /**
     * @test
     * @throws \Exception
     * @since 6.0.20
     */
    public function rbSoapChain()
    {
        $this->disableSslVerifyByPhpVersions();
        $this->CURL->setFlag("SOAPCHAIN");
        $this->CURL->setAuthentication($this->username, $this->password);
        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $wsdlResponse = $this->CURL->doGet($this->wsdl)->getPaymentMethods();
            static::assertTrue(is_array($wsdlResponse) && count($wsdlResponse) > 1);
        } catch (\Exception $e) {
            static::markTestSkipped(__FUNCTION__ . ": " . $e->getMessage());
        }
    }

    public function rbSimpleXml()
    {
        try {
            $this->CURL->setAuthentication($this->username, $this->password);
            $this->CURL->setFlag('XMLSOAP', true);
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var MODULE_CURL $wsdlResponse */
            $wsdlResponse = $this->CURL->doGet(
                $this->wsdl,
                NETCURL_POST_DATATYPES::DATATYPE_SOAP_XML
            )->getPaymentMethods();
        } catch (\Exception $e) {
            static::fail($e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    protected function setUp()
    {
        error_reporting(E_ALL);
        $this->CURL = new MODULE_CURL();
    }

}
