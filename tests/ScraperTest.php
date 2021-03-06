<?php

namespace Raulr\GooglePlayScraper\Tests;

use Mockery as m;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Raulr\GooglePlayScraper\Scraper;

/**
 * @author Raul Rodriguez <raul@raulr.net>
 */
class ScraperTest extends \PHPUnit_Framework_TestCase
{
    private $scraper;

    public function tearDown()
    {
        m::close();
    }

    public function getScraper(HandlerStack $handler = null)
    {
        $guzzleOptions = array(
            'defaults' => array('allow_redirects' => false, 'cookies' => true),
        );
        if ($handler) {
            $guzzleOptions['handler'] = $handler;
        }
        $guzzleClient = new Client($guzzleOptions);
        $scraper = m::mock('Raulr\GooglePlayScraper\Scraper', array($guzzleClient))->makePartial();

        return $scraper;
    }

    public function testSetDelay()
    {
        $scraper = $this->getScraper();
        $scraper->setDelay(2000);
        $this->assertEquals(2000, $scraper->getDelay());
    }

    public function testSetDefaultLang()
    {
        $scraper = $this->getScraper();
        $scraper->setDefaultLang('es');
        $this->assertEquals('es', $scraper->getDefaultLang());
    }

    public function testSetDefaultCountry()
    {
        $scraper = $this->getScraper();
        $scraper->setDefaultCountry('fr');
        $this->assertEquals('fr', $scraper->getDefaultCountry());
    }

    public function testGetCategories()
    {
        $transactions = array();
        $history = Middleware::history($transactions);
        $mock = new MockHandler(array(
            new Response(200, array('content-type' => 'text/html; charset=utf-8'), file_get_contents(__DIR__.'/resources/categories.html')),
        ));
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scraper = $this->getScraper($handler);
        $app = $scraper->getCategories();
        $expected = json_decode(file_get_contents(__DIR__.'/resources/categories.json'), true);
        $this->assertEquals($expected, $app);
        $this->assertEquals('https://play.google.com/store/apps?hl=en&gl=us', $transactions[0]['request']->getUri());
    }

    public function testGetApp()
    {
        $transactions = array();
        $history = Middleware::history($transactions);
        $mock = new MockHandler(array(
            new Response(200, array('content-type' => 'text/html; charset=utf-8'), file_get_contents(__DIR__.'/resources/app1.html')),
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app1_getreviews_1.json')),
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app1_getreviews_2.json')),
        ));
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scraper = $this->getScraper($handler);
        $app = $scraper->getApp('com.mojang.minecraftpe', 'en', 'us');
        $expected = json_decode(file_get_contents(__DIR__.'/resources/app1.json'), true);
        $this->assertEquals($expected, $app);
        $this->assertEquals('https://play.google.com/store/apps/details?id=com.mojang.minecraftpe&hl=en&gl=us', $transactions[0]['request']->getUri());
    }

    public function testGetAppIsFree()
    {
        $transactions = array();
        $history = Middleware::history($transactions);
        $mock = new MockHandler(array(
            new Response(200, array('content-type' => 'text/html; charset=utf-8'), file_get_contents(__DIR__.'/resources/app2.html')),
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app2_getreviews_1.json')),
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app2_getreviews_2.json')),
        ));
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scraper = $this->getScraper($handler);
        $app = $scraper->getApp('com.app_h2solutions.layout', 'zh', 'cn');
        $expected = json_decode(file_get_contents(__DIR__.'/resources/app2.json'), true);
        $this->assertEquals($expected, $app);
        $this->assertEquals('https://play.google.com/store/apps/details?id=com.app_h2solutions.layout&hl=zh&gl=cn', $transactions[0]['request']->getUri());
    }

    public function testGetAppNotFound()
    {
        $mock = new MockHandler(array(
            new Response(404),
        ));
        $handler = HandlerStack::create($mock);
        $scraper = $this->getScraper($handler);
        $this->setExpectedException('Raulr\GooglePlayScraper\Exception\NotFoundException');
        $app = $scraper->getApp('non.existing.app');
    }

    public function testGetAppWithNullByte()
    {
        $transactions = array();
        $history = Middleware::history($transactions);
        $mock = new MockHandler(array(
            new Response(200, array('content-type' => 'text/html; charset=utf-8'), file_get_contents(__DIR__.'/resources/app3.html')),
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app2_getreviews_1.json')),
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app2_getreviews_2.json')),
        ));
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scraper = $this->getScraper($handler);
        $app = $scraper->getApp('org.zooper.zwpro', 'de', 'de');
        $expected = json_decode(file_get_contents(__DIR__.'/resources/app3.json'), true);
        $this->assertEquals($expected, $app);
        $this->assertEquals('https://play.google.com/store/apps/details?id=org.zooper.zwpro&hl=de&gl=de', $transactions[0]['request']->getUri());
    }

    public function testGetApps()
    {
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getApp')
            ->with('app1_id', null, null)
            ->once()
            ->andReturn(array('app1_data'));
        $scraper
            ->shouldReceive('getApp')
            ->with('app2_id', null, null)
            ->once()
            ->andReturn(array('app2_data'));

        $apps = $scraper->getApps(array('app1_id', 'app2_id'));
        $expected = array(
            'app1_id' => array('app1_data'),
            'app2_id' => array('app2_data'),
        );
        $this->assertEquals($expected, $apps);
    }

    public function testGetListChunk()
    {
        $transactions = array();
        $history = Middleware::history($transactions);
        $mock = new MockHandler(array(
            new Response(200, array('content-type' => 'text/html; charset=utf-8'), file_get_contents(__DIR__.'/resources/list.html')),
            new Response(200, array('content-type' => 'text/html; charset=utf-8'), file_get_contents(__DIR__.'/resources/list_2.html')),
        ));
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scraper = $this->getScraper($handler);
        $list = $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 0, 2, 'en', 'us');
        $list += $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 2, 2, 'en', 'us');
        $expected = json_decode(file_get_contents(__DIR__.'/resources/list.json'), true);
        $expected += json_decode(file_get_contents(__DIR__.'/resources/list_2.json'), true);
        $this->assertEquals($expected, $list);
        $this->assertEquals('https://play.google.com/store/apps/category/GAME_ARCADE/collection/topselling_paid?hl=en&gl=us&start=0&num=2', $transactions[0]['request']->getUri());
        $this->assertEquals('https://play.google.com/store/apps/category/GAME_ARCADE/collection/topselling_paid?hl=en&gl=us&start=2&num=2', $transactions[1]['request']->getUri());
    }

    public function testGetListChunkStartNotInt()
    {
        $scraper = $this->getScraper();
        $this->setExpectedException('InvalidArgumentException');
        $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 'zero');
    }

    public function testGetListChunkStartTooBig()
    {
        $scraper = $this->getScraper();
        $this->setExpectedException('RangeException');
        $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 501);
    }

    public function testGetListChunkNumNotInt()
    {
        $scraper = $this->getScraper();
        $this->setExpectedException('InvalidArgumentException');
        $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 0, 'ten');
    }

    public function testGetListChunkNumTooBig()
    {
        $scraper = $this->getScraper();
        $this->setExpectedException('RangeException');
        $scraper->getListChunk('topselling_paid', 'GAME_ARCADE', 0, 121);
    }

    public function testGetList()
    {
        $expected = range(0, 100);
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getListChunk')
            ->with('topselling_paid', 'GAME_ARCADE', 0, 60, 'en', 'us')
            ->once()
            ->andReturn(array_slice($expected, 0, 60));
        $scraper
            ->shouldReceive('getListChunk')
            ->with('topselling_paid', 'GAME_ARCADE', 60, 60, 'en', 'us')
            ->once()
            ->andReturn(array_slice($expected, 60));

        $apps = $scraper->getList('topselling_paid', 'GAME_ARCADE', 'en', 'us');
        $this->assertEquals($expected, $apps);
    }

    public function testGetDetailListChunk()
    {
        $expected = array(
            'app1_id' => array('app1_data'),
            'app2_id' => array('app2_data'),
        );
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getListChunk')
            ->with('topselling_paid', 'GAME_ARCADE', 0, 2, 'en', 'us')
            ->once()
            ->andReturn(array(array('id' => 'app1_id'), array('id' => 'app2_id')));
        $scraper
            ->shouldReceive('getApps')
            ->with(array('app1_id', 'app2_id'))
            ->once()
            ->andReturn($expected);

        $apps = $scraper->getDetailListChunk('topselling_paid', 'GAME_ARCADE', 0, 2, 'en', 'us');
        $this->assertEquals($expected, $apps);
    }

    public function testGetDetailList()
    {
        $expected = array(
            'app1_id' => array('app1_data'),
            'app2_id' => array('app2_data'),
        );
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getList')
            ->with('topselling_paid', 'GAME_ARCADE', 'en', 'us')
            ->once()
            ->andReturn(array(array('id' => 'app1_id'), array('id' => 'app2_id')));
        $scraper
            ->shouldReceive('getApps')
            ->with(array('app1_id', 'app2_id'))
            ->once()
            ->andReturn($expected);

        $apps = $scraper->getDetailList('topselling_paid', 'GAME_ARCADE', 'en', 'us');
        $this->assertEquals($expected, $apps);
    }

    public function testGetSearch()
    {
        $transactions = array();
        $history = Middleware::history($transactions);
        $mock = new MockHandler(array(
            new Response(200, array('content-type' => 'text/html; charset=utf-8'), file_get_contents(__DIR__.'/resources/search1.html')),
            new Response(200, array('content-type' => 'text/html; charset=utf-8'), file_get_contents(__DIR__.'/resources/search2.html')),
        ));
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scraper = $this->getScraper($handler);
        $search = $scraper->getSearch('unicorns', 'free', '4+', 'en', 'us');
        $expected = json_decode(file_get_contents(__DIR__.'/resources/search.json'), true);
        $this->assertEquals($expected, $search);
        $this->assertEquals('https://play.google.com/store/search?q=unicorns&c=apps&hl=en&gl=us&price=1&rating=1', $transactions[0]['request']->getUri());
        $this->assertEquals('https://play.google.com/store/search?q=unicorns&c=apps&hl=en&gl=us&price=1&rating=1&pagTok=GAEiAggU%3AS%3AANO1ljLtUJw', $transactions[1]['request']->getUri());
    }

    public function testGetSearchQueryNotString()
    {
        $scraper = $this->getScraper();
        $this->setExpectedException('InvalidArgumentException');
        $scraper->getSearch(1.23);
    }

    public function testGetSearchPriceInvalid()
    {
        $scraper = $this->getScraper();
        $this->setExpectedException('InvalidArgumentException');
        $scraper->getSearch('unicorns', 0);
    }

    public function testGetSearchRatingInvalid()
    {
        $scraper = $this->getScraper();
        $this->setExpectedException('InvalidArgumentException');
        $scraper->getSearch('unicorns', 'all', 0);
    }

    public function testGetDetailSearch()
    {
        $expected = array(
            'app1_id' => array('app1_data'),
            'app2_id' => array('app2_data'),
        );
        $scraper = $this->getScraper();
        $scraper
            ->shouldReceive('getSearch')
            ->with('unicorns', 'free', '4+', 'en', 'us')
            ->once()
            ->andReturn(array(array('id' => 'app1_id'), array('id' => 'app2_id')));
        $scraper
            ->shouldReceive('getApps')
            ->with(array('app1_id', 'app2_id'))
            ->once()
            ->andReturn($expected);

        $apps = $scraper->getDetailSearch('unicorns', 'free', '4+', 'en', 'us');
        $this->assertEquals($expected, $apps);
    }

    public function testGetCommentsIds() {
        $transactions = array();
        $history = Middleware::history($transactions);
        $mock = new MockHandler(array(
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app1_getreviews_1.json')),
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app1_getreviews_2.json')),
        ));
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scraper = $this->getScraper($handler);
        $actual = $scraper->getCommentIds('com.mojang.minecraftpe', 'en');
        $expected = json_decode(file_get_contents(__DIR__.'/resources/app1_reviews_ids.json'), true);
        $this->assertEquals($expected, $actual);
        $this->assertEquals('https://play.google.com/store/getreviews', $transactions[0]['request']->getUri());
        $this->assertEquals('https://play.google.com/store/getreviews', $transactions[1]['request']->getUri());
    }

    public function testGetCommentsCount() {
        $transactions = array();
        $history = Middleware::history($transactions);
        $mock = new MockHandler(array(
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app1_getreviews_1.json')),
            new Response(200, array('content-type' => 'application/json; charset=utf-8'), file_get_contents(__DIR__.'/resources/app1_getreviews_2.json')),
        ));
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scraper = $this->getScraper($handler);
        $actual = $scraper->getCommentCount('com.mojang.minecraftpe', 'en');
        $expected = count(json_decode(file_get_contents(__DIR__.'/resources/app1_reviews_ids.json'), true));
        $this->assertEquals($expected, $actual);
        $this->assertEquals('https://play.google.com/store/getreviews', $transactions[0]['request']->getUri());
        $this->assertEquals('https://play.google.com/store/getreviews', $transactions[1]['request']->getUri());

    }

    public function testSimilar() {
        $transactions = [];
        $history = Middleware::history($transactions);
        $mock = new MockHandler([
            new Response(200, ['content-type' => 'application/json; charset=utf-8'], file_get_contents(__DIR__ . '/resources/similar.html'))
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $scrapper = $this->getScraper($handler);
        $similar = $scrapper->getSimilar('com.ea.games.simsfreeplay_row');
        $expected = json_decode(file_get_contents(__DIR__ . '/resources/similar.json'), true);
        $this->assertEquals($expected, $similar);
        $this->assertEquals('https://play.google.com/store/apps/similar?id=com.ea.games.simsfreeplay_row', $transactions[0]['request']->getUri());
    }
}
