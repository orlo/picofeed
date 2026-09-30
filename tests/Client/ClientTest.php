<?php

namespace PicoFeed\Client;

use DateTime;

class ClientTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @group online
     */
    public function testDownload()
    {
        $client = Client::getInstance();
        $client->setUrl('http://php.net/robots.txt');
        $client->execute();

        $this->assertTrue($client->isModified());
        $this->assertNotEmpty($client->getContent());
        $this->assertNotEmpty($client->getEtag());
        $this->assertNotEmpty($client->getLastModified());
    }

    /**
     * @runInSeparateProcess
     * @group online
     */
    public function testPassthrough()
    {

        $client = Client::getInstance();
        $client->setUrl('https://miniflux.app/favicon.ico');
        $client->enablePassthroughMode();
        ob_start();

        $client->execute();
        $str = ob_get_clean();

        $this->assertNotEmpty($str, "favicon.ico should be non-empty");
        $this->assertEquals($str, file_get_contents(__DIR__ . '/../fixtures/miniflux_favicon.ico'));
    }

    /**
     * A resource only counts as unmodified when every cache validator the
     * server returns (Etag and Last-Modified) matches what we already have -
     * a single matching header is not enough.
     */
    public function testCacheBothHaveToMatch()
    {
        $client = new class extends Client {
            public function doRequest()
            {
                return array(
                    'status' => 200,
                    'body' => 'content',
                    'headers' => new HttpHeaders(array(
                        'ETag' => 'abc123',
                        'Last-Modified' => 'Wed, 21 Oct 2020 07:28:00 GMT',
                    )),
                );
            }
        };

        // Only the Etag is known to match, Last-Modified is unset, so the
        // resource must still be reported as modified.
        $client->setEtag('abc123');
        $client->execute();

        $this->assertTrue($client->isModified());
    }

    /**
     * @group online
     */
    public function testMultipleDownload()
    {
        $client = Client::getInstance();
        $client->setUrl('http://miniflux.net/');
        $result = $client->doRequest();

        $body = $result['body'];
        $result = $client->doRequest();

        $this->assertEquals($body, $result['body']);
    }

    /**
     * @group online
     */
    public function testCacheEtag()
    {
        $client = Client::getInstance();
        $client->setUrl('http://php.net/robots.txt');
        $client->execute();
        $etag = $client->getEtag();
        $lastModified = $client->getLastModified();

        $client = Client::getInstance();
        $client->setUrl('http://php.net/robots.txt');
        $client->setEtag($etag);
        $client->setLastModified($lastModified);
        $client->execute();

        $this->assertFalse($client->isModified());
    }

    /**
     * @group online
     */
    public function testCacheLastModified()
    {
        $client = Client::getInstance();
        $client->setUrl('http://miniflux.net/robots.txt');
        $client->execute();
        $lastmod = $client->getLastModified();

        $client = Client::getInstance();
        $client->setUrl('http://miniflux.net/robots.txt');
        $client->setLastModified($lastmod);
        $client->execute();

        $this->assertFalse($client->isModified());
    }

    /**
     * @group online
     */
    public function testCacheBoth()
    {
        $client = Client::getInstance();
        $client->setUrl('http://miniflux.net/robots.txt');
        $client->execute();
        $lastmod = $client->getLastModified();
        $etag = $client->getEtag();

        $client = Client::getInstance();
        $client->setUrl('http://miniflux.net/robots.txt');
        $client->setLastModified($lastmod);
        $client->setEtag($etag);
        $client->execute();

        $this->assertFalse($client->isModified());
    }

    /**
     * @group online
     */
    public function testCharset()
    {
        $client = Client::getInstance();
        $client->setUrl('http://php.net/');
        $client->execute();
        $this->assertEquals('utf-8', $client->getEncoding());

        $client = Client::getInstance();
        $client->setUrl('http://php.net/robots.txt');
        $client->execute();
        $this->assertEquals('utf-8', $client->getEncoding());
    }

    /**
     * @group online
     */
    public function testContentType()
    {
        $client = Client::getInstance();
        $client->setUrl('https://upload.wikimedia.org/wikipedia/commons/7/70/Example.png');
        $client->execute();
        $this->assertEquals('image/png', $client->getContentType());

        $client = Client::getInstance();
        $client->setUrl('http://miniflux.net/');
        $client->execute();
        $this->assertEquals('text/html; charset=utf-8', $client->getContentType());
    }

    public function testExpirationWithExpiresHeader()
    {
        $client = Client::getInstance();
        $headers = new HttpHeaders(array('Expires' => 'Fri, 30 Dec 2016 22:58:52 GMT'));
        $this->assertEquals(new DateTime('Fri, 30 Dec 2016 22:58:52 GMT'), $client->parseExpiration($headers));
    }

    public function testExpirationWithExpiresHeaderAtZero()
    {
        $client = Client::getInstance();
        $headers = new HttpHeaders(array('Expires' => '0'));
        $this->assertEquals((new DateTime())->format('Y-m-d H:i:s'), $client->parseExpiration($headers)->format('Y-m-d H:i:s'));
    }

    public function testExpirationWithCacheControlHeaderAndZeroMaxAge()
    {
        $client = Client::getInstance();
        $headers = new HttpHeaders(array('cache-control' => 'private, max-age=0, no-cache'));
        $this->assertEquals((new DateTime())->format('Y-m-d H:i:s'), $client->parseExpiration($headers)->format('Y-m-d H:i:s'));
    }

    public function testExpirationWithCacheControlHeaderAndNotEmptyMaxAge()
    {
        $client = Client::getInstance();
        $headers = new HttpHeaders(array('cache-control' => 'private, max-age=600'));
        $this->assertEquals((new DateTime('+600 seconds'))->format('Y-m-d H:i:s'), $client->parseExpiration($headers)->format('Y-m-d H:i:s'));
    }

    public function testExpirationWithCacheControlHeaderAndOnlyMaxAge()
    {
        $client = Client::getInstance();
        $headers = new HttpHeaders(array('cache-control' => 'max-age=300'));
        $this->assertEquals((new DateTime('+300 seconds'))->format('Y-m-d H:i:s'), $client->parseExpiration($headers)->format('Y-m-d H:i:s'));
    }

    public function testExpirationWithCacheControlHeaderAndNotEmptySMaxAge()
    {
        $client = Client::getInstance();
        $headers = new HttpHeaders(array('cache-control' => 'no-transform,public,max-age=300,s-maxage=900'));
        $this->assertEquals((new DateTime('+900 seconds'))->format('Y-m-d H:i:s'), $client->parseExpiration($headers)->format('Y-m-d H:i:s'));
    }
}
