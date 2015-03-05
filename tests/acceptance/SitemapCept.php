<?php 
$I = new AcceptanceTester($scenario);
$I->am('Google Bot');
$I->wantTo('Test XML Sitemaps');

$I->expectTo('see the sitemap in robots.txt');
$I->sendGET('/robots.txt');
$I->seeResponseContains('Sitemap:');
$I->seeResponseContains('/sitemap.xml');
$I->seeResponseContains('/sitemapindex.xml');

$I->expectTo('see an XML sitemap index');
$I->sendGET('/sitemapindex.xml');
$I->seeResponseCodeIs(200);
$I->haveHttpHeader('Content-Type','application/xml; charset=utf-8');
$I->seeResponseIsXml();
$I->dontSee('<b>Notice</b>'); 
$I->dontSee('error');

$I->expectTo('see a brief XML sitemap');
$I->sendGET('/sitemap.xml');
$I->seeResponseCodeIs(200);
$I->seeResponseIsXml();
$I->haveHttpHeader('Content-Type','application/xml; charset=utf-8');
$I->seeResponseContains('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"');
$I->dontSee('<b>Notice</b>'); // error sometimes thrown if Wordpress timezone not set
$I->dontSee('error');

$I->expectTo('see a paginated XML sitemap');
$I->sendGET('/sitemap.xml?page=1');
$I->seeResponseCodeIs(200);
$I->seeResponseIsXml();
$I->haveHttpHeader('Content-Type','application/xml; charset=utf-8');
$I->seeResponseContains('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"');
$I->dontSee('<b>Notice</b>');
$I->dontSee('error');
$page_1 = $I->grabResponse();

$I->expect('that page 2 will be different than page 1');
$I->sendGET('/sitemap.xml?page=2');
$I->assertNotEquals( $page_1, $I->grabResponse() );

$I->expectTo('see a full XML sitemap');
$I->sendGET('/sitemap-all.xml');
$I->seeResponseCodeIs(200);
$I->seeResponseIsXml();
$I->haveHttpHeader('Content-Type','application/xml; charset=utf-8');
$I->seeResponseContains('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"');
$I->dontSee('<b>Notice</b>'); // error sometimes thrown if Wordpress timezone not set
$I->dontSee('error');

$I->expect('sitemap link tag in HTML HEAD');
$I->amOnPage('/');
$I->seeElement('link', array('rel' => 'sitemap'));