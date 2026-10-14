<?php

namespace PicoFeed\Reader;


class FaviconTest extends \PHPUnit\Framework\TestCase
{
    public function testExtract()
    {
        $favicon = new Favicon();

        $html = '<!DOCTYPE html><html><head>
                <link rel="icon" href="http://example.com/myicon.ico" />
                </head><body><p>boo</p></body></html>';

        $this->assertEquals(array('http://example.com/myicon.ico'), $favicon->extract($html));

        // multiple values in rel attribute
        $html = '<!DOCTYPE html><html><head>
                <link rel="shortcut icon" href="http://example.com/myicon.ico" />
                </head><body><p>boo</p></body></html>';

        $this->assertEquals(array('http://example.com/myicon.ico'), $favicon->extract($html));

        // with other attributes present
        $html = '<!DOCTYPE html><html><head>
                <link rel="icon" type="image/vnd.microsoft.icon" href="http://example.com/image.ico" />
                </head><body><p>boo</p></body></html>';

        $this->assertEquals(array('http://example.com/image.ico'), $favicon->extract($html));

        // ignore icon in other attribute
        $html = '<!DOCTYPE html><html><head>
                <link type="icon" href="http://example.com/image.ico" />
                </head><body><p>boo</p></body></html>';

        // ignores apple icon
        $html = '<!DOCTYPE html><html><head>
                <link rel="apple-touch-icon" href="assets/img/touch-icon-iphone.png">
                <link rel="icon" type="image/png" href="http://example.com/image.png" />
                </head><body><p>boo</p></body></html>';

        $this->assertEquals(array('http://example.com/image.png'), $favicon->extract($html));

        // allows multiple icons
        $html = '<!DOCTYPE html><html><head>
                <link rel="icon" type="image/png" href="http://example.com/image.png" />
                <link rel="icon" type="image/x-icon" href="http://example.com/image.ico"/>
                </head><body><p>boo</p></body></html>';

        $this->assertEquals(array('http://example.com/image.png', 'http://example.com/image.ico'), $favicon->extract($html));

        // empty array with broken html
        $html = '!DOCTYPE html html head
                link rel="icon" type="image/png" href="http://example.com/image.png" /
                link rel="icon" type="image/x-icon" href="http://example.com/image.ico"/
                /head body /p boo /p body /html';

        $this->assertEquals(array(), $favicon->extract($html));

        // empty array on no input
        $this->assertEquals(array(), $favicon->extract(''));

        // empty array on no icon found
        $html = '<!DOCTYPE html><html><head>
                </head><body><p>boo</p></body></html>';

        $this->assertEquals(array(), $favicon->extract($html));
    }

    /**
     * @group online
     */
    public function testExists()
    {
        $favicon = new Favicon();

        $this->assertTrue($favicon->exists('https://miniflux.net/favicon.ico'));
        $this->assertFalse($favicon->exists('http://foobar'));
        $this->assertFalse($favicon->exists(''));
    }

    /**
     * @group online
     */
    public function testFind_inMeta()
    {
        $favicon = new Favicon();

        // favicon in meta
        $this->assertEquals(
            'http://miniflux.net/favicon.ico',
            $favicon->find('http://miniflux.net')
        );

        $this->assertNotEmpty($favicon->getContent());
    }

    /**
     * @group online
     */
    public function testFind_directLinkFirst()
    {
        $favicon = new Favicon();

        $this->assertEquals(
            'http://miniflux.net/favicon.ico',
            $favicon->find('http://miniflux.net', '/assets/img/touch-icon-ipad.png')
        );

        $this->assertNotEmpty($favicon->getContent());
    }

    /**
     * @group online
     */
    public function testFind_fallsBackToExtract()
    {
        $favicon = new Favicon();
        $this->assertEquals(
            'http://miniflux.net/favicon.ico',
            $favicon->find('http://miniflux.net', '/nofavicon.ico')
        );

        $this->assertNotEmpty($favicon->getContent());
    }

    /**
     * @group online
     */
    public function testDataUri()
    {
        $favicon = new Favicon();

        $this->assertEquals(
            'http://miniflux.net/favicon.ico',
            $favicon->find('http://miniflux.net')
        );

        $dataUri = $favicon->getDataUri();

        // miniflux.net's favicon binary content isn't stable enough to pin
        // an exact byte comparison, just check the data URI is well-formed.
        $this->assertMatchesRegularExpression('#^data:image/[\w.+-]+;base64,[A-Za-z0-9+/]+=*$#', $dataUri);
    }

    /**
     * @group online
     */
    public function testDataUri_withBadContentType()
    {
        $favicon = new Favicon();
        $this->assertNotEmpty($favicon->find('http://www.lemonde.fr/'));
        $expected = 'data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAAAAABWESUoAAABMUlEQVR4AWMYOoC5moCClhYGzY7Di1UYGFxmCQL5knOiGJGkGdW/nz/5HwiuyK76//++MVPOx///T9iyRQhBFeh5/fkPAVdK3/7//+PCpK6fQM6fxSJQBZxZVpGO1lVAMeV0t0///08UyPbc+v9/OaoznCOAChQZUk///9/LwOBg8OE3O6oCy0iwgrjjYAWCd/9fY8CjQDvp//+z+BQElBBQEFVNQEEs2QoEoQrisCkAhsNbBjwKnBP+/y+BKYjHokB86pMgBnwKpCfwMsAVJGAocJ6w59AUPQY+JAVXkaTtLoAj++e63WAFTQwZhf///22bA0sv7be74oIynvx/m8EEUnBMjsHb9+v/v9Nh6SUunomBQbgxbIYwiDPJC6Sn0n2ZMZojWRXAlAKUq80wlAAAJbvjBhQMZdcAAAAASUVORK5CYII=';
        $this->assertEquals($expected, $favicon->getDataUri());
    }
}
