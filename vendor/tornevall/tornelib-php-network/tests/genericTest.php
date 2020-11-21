<?php

require_once(__DIR__ . '/../vendor/autoload.php');

use PHPUnit\Framework\TestCase;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Module\Network;
use TorneLIB\Module\Network\Domain;
use TorneLIB\Module\Network\Statics;
use TorneLIB\MODULE_NETWORK;

class genericTest extends TestCase
{
    /**
     * @test Get Proxy data from network client.
     */
    public function getProxyData()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_VIA'] = '127.0.0.1';
        static::assertCount(1, (new Network())->getProxyData());
    }

    /**
     * @test Get set proxy headers.
     */
    public function getProxyHeaders()
    {
        static::assertCount(15, (new Network())->getProxyHeaders());
    }

    /**
     * @test
     * What happens when obsolete or removed methods are accessed.
     */
    public function getObsoleteMethod()
    {
        try {
            (new Network())->getWhatDoesNotExist();
        } catch (ExceptionHandler $e) {
            static::assertTrue(
                $e->getCode() === Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
            );
        }
    }

    /**
     * @test
     */
    public function getDeprecatedMethod()
    {
        static::assertTrue((new Network())->getCurrentServerProtocol(true) === 'http');
    }

    /**
     * @test Test backward compatibility.
     */
    public function deprecatedModuleFunc()
    {
        static::assertCount(15, (new MODULE_NETWORK())->getProxyHeaders());
    }

    /**
     * @test Test backward compatibility.
     */
    public function deprecatedModuleVar()
    {
        static::assertTrue((new MODULE_NETWORK())->isDeprecated);
    }

    /**
     * @test Test unexistent variables and backward compatiblity.
     */
    public function deprecatedUnexistentModuleVar()
    {
        try {
            (new MODULE_NETWORK())->thisDoesNotExist;
        } catch (\Exception $e) {
            static::assertTrue(
                $e->getCode() === Constants::LIB_METHOD_OR_LIBRARY_UNAVAILABLE
            );
        }
    }

    /**
     * @test
     */
    public function getDeprecatedHttps()
    {
        $_SERVER['HTTPS'] = true;
        static::assertTrue((new Network())->isSecureHttp());
        unset($_SERVER['HTTPS']);
    }

    /**
     * @test
     */
    public function getIsSecureHttp()
    {
        $_SERVER['HTTPS'] = true;
        static::assertTrue((new Network())->getIsSecureHttp());
        unset($_SERVER['HTTPS']);
    }

    /**
     * @test
     */
    public function getProtocol()
    {
        static::assertTrue((new Network())->getProtocol() === 'http');
    }

    /**
     * @test
     */
    public function getHttpHost()
    {
        static::assertTrue((new Network())->getHttpHost() === 'localhost');
    }

    /**
     * Convert snakecases to camelcase.
     *
     * @test
     */
    public function getCamelCased()
    {
        static::assertTrue(Strings::returnCamelCase('base64url_encode') === "base64urlEncode");
    }

    /**
     * @test
     */
    public function testDeprecatedSnakes()
    {
        $encodedString = (new MODULE_NETWORK())->base64url_encode('TEST');
        static::assertTrue($encodedString === 'VEVTVA');
    }

    /**
     * @test
     * @throws \TorneLIB\Exception\ExceptionHandler
     */
    public function getGitTagsNetcurl()
    {
        if (!class_exists('TorneLIB\Helpers\NetUtils')) {
            static::markTestSkipped('TorneLIB\Helpers\NetUtils unavailable');
            return;
        }

        static::assertGreaterThan(
            2,
            (new Network())->getGitTagsByUrl("https://bitbucket.tornevall.net/scm/lib/tornelib-php-netcurl.git")
        );
    }

    /**
     * @test
     */
    public function getGitTagsNetcurlBucket()
    {
        if (!class_exists('TorneLIB\Helpers\NetUtils')) {
            static::markTestSkipped('TorneLIB\Helpers\NetUtils unavailable');
            return;
        }
        static::assertGreaterThan(
            2,
            (new TorneLIB\Helpers\NetUtils())->getGitTagsByUrl("https://bitbucket.org/resursbankplugins/resurs-ecomphp/src/master/")
        );
    }

    /**
     * @test
     * @throws ExceptionHandler
     */
    public function getGitOldUtil()
    {
        if (!class_exists('TorneLIB\Helpers\NetUtils')) {
            static::markTestSkipped('TorneLIB\Helpers\NetUtils unavailable');
            return;
        }

        static::assertGreaterThan(
            2,
            (new Network())->getGitTagsByUrl("https://bitbucket.org/resursbankplugins/resurs-ecomphp/src/master/")
        );
    }

    /**
     * @test
     * @throws ExceptionHandler
     */
    public function getUrlDomain()
    {
        $response = (new Domain())->getUrlDomain(
            'https://www.aftonbladet.se'
        );

        static::assertTrue($response[0] === 'www.aftonbladet.se');
    }

    /**
     * @test
     */
    public function getUrlsFromHtml()
    {
        $html = '<html>
			<a href="http://test.com/url1">URL 1</a>
			<a href=\'http://test.com/url2\'>URL 2</a>
			<a href= "http://test.com/url3" >URL 3</a>
			<a href= \'http://test.com/url4\' >URL 4</a>
			<img src="http://test.com/img1">IMG 1</a>
			<img src=\'http://test.com/img2\'>IMG 2</a>
			<img src= "http://test.com/img3" >IMG 3</a>
			<img src= \'http://test.com/img4\' >IMG 4</a>

			<a href="http://test.com/durl1">Duplicate URL 1</a>
			<a href=\'http://test.com/durl2\'>Duplicate URL 2</a>
			<a href= "http://test.com/durl3" >Duplicate URL 3</a>
			<a href= \'http://test.com/durl4\' >Duplicate URL 4</a>
			<img src="http://test.com/dimg1">Duplicate IMG 1</a>
			<img src=\'http://test.com/dimg2\'>Duplicate IMG 2</a>
			<img src= "http://test.com/dimg3" >Duplicate IMG 3</a>
			<img src= \'http://test.com/dimg4\' >Duplicate IMG 4</a>
			</html>
		';

        static::assertCount(16, (new Domain())->getUrlsFromHtml($html));
    }

    /**
     * @test
     * @throws Exception
     */
    public function getDomainName()
    {
        static::assertTrue(
            (new Domain())->getDomainName('https://www.aftonbladet.se/artikel/hej/hopp') === 'aftonbladet.se'
        );
    }

    /**
     * @test
     * Test if some of the internal requests are reachable from the static caller. Backward compatible.
     */
    public function staticInternal()
    {
        static::assertTrue(
            Statics::getArpaFromIpv4('127.0.0.1') === '1.0.0.127'
        );
    }

    /**
     * @test
     * Same as statics above but from Network directly.
     */
    public function secondWayRequest()
    {
        static::assertTrue(
            (new Network())->getArpaFromIpv4('127.0.0.1') === '1.0.0.127'
        );
    }

    /**
     * @test
     * Same as statics and secondWay, but from deprecated module.
     */
    public function thirdWayRequest()
    {
        static::assertTrue(
            (new MODULE_NETWORK())->getArpaFromIpv4('127.0.0.1') === '1.0.0.127'
        );
    }

    /**
     * @test
     */
    public function arpaRequest4()
    {
        static::assertTrue(
            (new Network())->getArpa('212.63.208.1') === '1.208.63.212'
        );
    }

    /**
     * @test
     */
    public function arpaRequest6()
    {
        static::assertTrue(
            (new Network())->getArpa('2a01:299:a0:ff:ff:ff:ff:ff') ===
            'f.f.0.0.f.f.0.0.f.f.0.0.f.f.0.0.f.f.0.0.0.a.0.0.9.9.2.0.1.0.a.2'
        );
    }

    /**
     * @test
     */
    public function ipType() {
        $t4 = Statics::getIpType('127.0.0.1');
        $t6 = Statics::getIpType('::ff');
        
        static::assertTrue(
            $t4 === 4 &&
            $t6 === 6
        );
    }
}
