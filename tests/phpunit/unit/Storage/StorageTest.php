<?php
namespace Bolt\Tests\Storage;

use Bolt\Content;
use Bolt\Events\StorageEvents;
use Bolt\Storage;
use Bolt\Tests\BoltUnitTest;
use Bolt\Tests\Mocks\LoripsumMock;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class to test src/Storage.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 */
class StorageTest extends BoltUnitTest
{
    public function testSetup()
    {
        $this->resetDb();
    }

    public function testGetContentObject()
    {
        $app = $this->getApp();
        $storage = new Storage($app);
        $content = $storage->getContentObject('pages');
        $this->assertInstanceOf('Bolt\Content', $content);

        $fields = $app['config']->get('contenttypes/pages/fields');

        $mock = $this->getMock('Bolt\Content', null, [$app], 'Pages');
        $content = $storage->getContentObject(['class' => 'Pages', 'fields' => $fields]);
        $this->assertInstanceOf('Pages', $content);
        $this->assertInstanceOf('Bolt\Content', $content);

        // Test that a class not instanceof Bolt\Content fails
        $mock = $this->getMock('stdClass', null, [], 'Failing');
        $this->setExpectedException('Exception', 'Failing does not extend \Bolt\Content.');
        $content = $storage->getContentObject(['class' => 'Failing', 'fields' => $fields]);
    }

    public function testPreFill()
    {
        $app = $this->getApp();
        $app['users']->users = [1 => $this->addDefaultUser($app)];
        $prefillMock = new LoripsumMock();
        $app['prefill'] = $prefillMock;

        $app['config']->set('general/changelog/enabled', true);
        $storage = new Storage($app);
        $output = $storage->prefill(['showcases']);
        $this->assertRegExp('#Added#', $output);
        $this->assertRegExp('#Done#', $output);

        $output = $storage->prefill();
        $this->assertRegExp('#Skipped#', $output);
    }

    public function testSaveContent()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        // Test missing contenttype handled
        $content = new Content($app);
        $this->setExpectedException('Bolt\Exception\StorageException', 'Contenttype is required for saveContent');
        $this->assertFalse($storage->saveContent($content));

        // Test dispatcher is called pre-save and post-save
        $content = $storage->getContent('showcases/1');

        $presave = 0;
        $postsave = 0;
        $listener = function () use (&$presave) {
            $presave++;
        };
        $listener2 = function () use (&$postsave) {
            $postsave++;
        };
        $app['dispatcher']->addListener(StorageEvents::PRE_SAVE, $listener);
        $app['dispatcher']->addListener(StorageEvents::POST_SAVE, $listener2);
        $storage->saveContent($content);
        $this->assertEquals(1, $presave);
        $this->assertEquals(1, $postsave);
    }

    public function testDeleteContent()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        $content = $app['storage']->getContent('showcases/1');

        // Test the delete events are triggered
        $delete = 0;
        $listener = function () use (&$delete) {
            $delete++;
        };
        $app['dispatcher']->addListener(StorageEvents::PRE_DELETE, $listener);
        $app['dispatcher']->addListener(StorageEvents::POST_DELETE, $listener);

        $app['storage']->deleteContent(['slug' => 'showcases'], 1);

        $this->assertFalse($storage->getContent('showcases/1'));
        $this->assertEquals(2, $delete);
    }
    
    public function testDeleteContentException()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        // Test delete fails on missing params
        $this->setExpectedException('Bolt\Exception\StorageException', 'Contenttype is required for deleteContent');
        $this->assertFalse($storage->deleteContent('', 999));
    }

    public function testUpdateSingleValue()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        $fetch1 = $storage->getContent('showcases/2');
        $this->assertEquals(1, $fetch1->get('ownerid'));
        $result = $storage->updateSingleValue('showcases', 2, 'ownerid', '10');
        $this->assertEquals(2, $result);

        $fetch2 = $storage->getContent('showcases/2');
        $this->assertEquals('10', $fetch2->get('ownerid'));

        // Test invalid column fails
        $shouldError = $storage->updateSingleValue('showcases', 2, 'nonexistent', '10');
        $this->assertFalse($shouldError);
    }

    public function testGetEmptyContent()
    {
        $app = $this->getApp();
        $storage = new Storage($app);
        $showcase = $storage->getEmptyContent('showcase');
        $this->assertInstanceOf('Bolt\Content', $showcase);
        $this->assertEquals('showcases', $showcase->contenttype['slug']);
    }

    public function testSearchContent()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);
        $result = $storage->searchContent('lorem');
        $this->assertGreaterThan(0, count($result));
        $this->assertTrue($result['query']['valid']);

        // Test invalid query fails
        $result = $storage->searchContent('x');
        $this->assertFalse($result);

        // Test filters
        $result = $storage->searchContent('lorem', ['showcases'], ['showcases' => ['title' => 'nonexistent']]);
        $this->assertTrue($result['query']['valid']);
        $this->assertEquals(0, $result['no_of_results']);
    }

    public function testSearchAllContentTypes()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);
        $results = $app['storage']->searchAllContentTypes(['body' => 'lorem']);
        $this->assertTrue(is_array($results));
    }

    
    public function testSearchContentType()
    {
        $app = $this->getApp();
        $pager = [];
        $results = $app['storage']->searchContentType('pages', ['body' => 'lorem']);
        $this->assertTrue(is_array($results));
        $this->assertEmpty($results);
        
        $this->markTestSkipped('Storage searchContentType fails with string filter');
        $results = $app['storage']->searchContentType('pages', ['filter' => 'lorem', 'limit'=>1]);
        $this->assertEquals(1, count($results));
        
    }

    public function testGetContentByTaxonomy()
    {
    }

    public function testPublishTimedRecords()
    {
    }

    public function testDepublishExpiredRecords()
    {
    }

    /**
     *
     * @dataProvider validContentQueryProvider
     */
    public function testGetContent($query, $params, $expected, $expectedParams = [])
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $params['getquery'] = function($query, $params) use ($expected) {
            $this->assertEquals($expected, $query);
        };

        $app['storage']->getContent($query, $params);
    }
    
    public static function validContentQueryProvider()
    {
        return [
            [
                'pages', 
                [], 
                "SELECT bolt_pages.* FROM bolt_pages  ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages/1', 
                [], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"id\" = '1') ORDER BY datepublish DESC LIMIT 1"
            ],
            [
                'pages/latest/2', 
                [], 
                'SELECT bolt_pages.* FROM bolt_pages  ORDER BY "datepublish" DESC LIMIT 2'
            ],
            [
                'pages/first/2', 
                [], 
                'SELECT bolt_pages.* FROM bolt_pages  ORDER BY "datepublish" LIMIT 2'
            ],
            [
                'pages/ecce-aliud-simile-dissimile',
                [],
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"slug\" = 'ecce-aliud-simile-dissimile') ORDER BY datepublish DESC LIMIT 1"
            ],
            [
                'pages', 
                ['ownerid'=>2], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"ownerid\" = '2') ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['datepublish'=>'2015-01-01'], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"datepublish\" = '2015-01-01 00:00:00') ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['ownerid'=>'!2'], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"ownerid\" != '2') ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['title'=>'!'], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"title\" != '') ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['datepublish'=>'<2015-01-01'], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"datepublish\" < '2015-01-01 00:00:00') ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['status'=>'published','datepublish'=> '< last monday'], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"status\" = 'published' AND \"bolt_pages\".\"datepublish\" < '".date('Y-m-d', strtotime('last monday'))." 00:00:00') ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['ownerid'=>'>1'], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"ownerid\" > '1') ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['title'=>'%Ecce%'], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE (\"bolt_pages\".\"title\" LIKE '%Ecce%') ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'entries', 
                ['categories'=>'news'], 
                "SELECT bolt_entries.* FROM bolt_entries WHERE (\"id\"  IN (SELECT content_id AS id FROM bolt_taxonomy where \"bolt_taxonomy\".\"taxonomytype\" = 'categories' AND ( \"bolt_taxonomy\".\"slug\" = 'news' OR \"bolt_taxonomy\".\"name\" = 'news' ) AND \"bolt_taxonomy\".\"contenttype\" = 'entries')) ORDER BY \"datepublish\" DESC LIMIT 9999"
            ],
            [
                'entries', 
                ['categories'=>'news || events'], 
                "SELECT bolt_entries.* FROM bolt_entries WHERE (\"id\"  IN (SELECT content_id AS id FROM bolt_taxonomy where \"bolt_taxonomy\".\"taxonomytype\" = 'categories' AND ( ( \"bolt_taxonomy\".\"slug\" = 'news' OR \"bolt_taxonomy\".\"slug\" = 'events' ) OR ( \"bolt_taxonomy\".\"name\" = 'news' OR \"bolt_taxonomy\".\"name\" = 'events' ) ) AND \"bolt_taxonomy\".\"contenttype\" = 'entries')) ORDER BY \"datepublish\" DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['title ||| teaser'=>'%Ecce% ||| %lorem%'], 
                "SELECT bolt_pages.* FROM bolt_pages WHERE ((  (\"bolt_pages\".\"title\" LIKE '%Ecce%') OR  (\"bolt_pages\".\"teaser\" LIKE '%lorem%')) ) ORDER BY datepublish DESC LIMIT 9999"
            ],
            [
                'pages', 
                ['limit'=>'5'], 
                "SELECT bolt_pages.* FROM bolt_pages  ORDER BY datepublish DESC LIMIT 5"
            ],
            
        ];
    }

    public function testGetContentSortOrderFromContentType()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $db = $this->getDbMockBuilder($app['db'])
            ->setMethods(['fetchAll'])
            ->getMock();
        $app['db'] = $db;
        $db->expects($this->any())
            ->method('fetchAll')
            ->willReturn([]);
        $storage = new StorageMock($app);

        // Test sorting is pulled from contenttype when not specified
        $app['config']->set('contenttypes/entries/sort', '-id');
        $storage->getContent('entries');
        $this->assertSame('ORDER BY "id" DESC', $storage->queries[0]['queries'][0]['order']);
    }

    public function testGetContentReturnSingleLimits1()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $db = $this->getDbMockBuilder($app['db'])
            ->setMethods(['fetchAll'])
            ->getMock();
        $app['db'] = $db;
        $db->expects($this->any())
            ->method('fetchAll')
            ->willReturn([]);
        $storage = new StorageMock($app);

        // Test returnsingle will set limit to 1
        $storage->getContent('entries', ['returnsingle' => true]);
        $this->assertSame(1, $storage->queries[0]['parameters']['limit']);
    }

    public function testGetSortOrder()
    {
        $app = $this->getApp();
        $storage = $app['storage'];
        
        // First test that non-string returns false
        $this->assertFalse($storage->getSortOrder([]));

        // Test that default returns datepublish
        $this->assertEquals(['datepublish', false], $storage->getSortOrder());
        $this->assertEquals(['datepublish', true], $storage->getSortOrder('datepublish'));
        $this->assertEquals(['datepublish', true], $storage->getSortOrder('datepublish ASC'));
        $this->assertEquals(['datepublish', false], $storage->getSortOrder('datepublish DESC'));
        
    }

    public function testGetContentType()
    {
        $app = $this->getApp();
        $storage = $app['storage'];
        
        $this->assertEquals($app['config']->get('contenttypes/showcases'), $storage->getContentType('showcases'));
        
        // Change the name and make sure this gets picked up too..
        $app['config']->set('contenttypes/showcases/name', 'Different');
        $this->assertEquals($app['config']->get('contenttypes/showcases'), $storage->getContentType('Different'));
        $this->assertFalse($storage->getContentType(''));
        $this->assertFalse($storage->getContentType('nonexistent'));
    }

    public function testGetTaxonomyType()
    {
        $app = $this->getApp();
        $this->assertFalse($app['storage']->getTaxonomyType(''));
        $this->assertFalse($app['storage']->getTaxonomyType('nonexistent'));
        $this->assertEquals($app['config']->get('taxonomy/categories'), $app['storage']->getTaxonomyType('categories'));
        $this->assertEquals($app['config']->get('taxonomy/categories'), $app['storage']->getTaxonomyType('category'));
    }

    public function testGetContentTypes()
    {
        $app = $this->getApp();
        $this->assertTrue(is_array($app['storage']->getContentTypes()));
    }

    public function testGetContentTypeFields()
    {
        $app = $this->getApp();
        $this->assertTrue(is_array($app['storage']->getContentTypeFields('showcases')));
        $app['config']->set('contenttypes/showcases/fields', '');
        $this->assertEquals([], $app['storage']->getContentTypeFields('showcases'));
    }

    public function testGetContentTypeFieldType()
    {
        $app = $this->getApp();
        $type = $app['storage']->getContentTypeFieldType('showcases', 'title');
        $this->assertEquals('text', $app['storage']->getContentTypeFieldType('showcases', 'title'));
        $this->assertEquals('datetime', $app['storage']->getContentTypeFieldType('showcases', 'datepublish'));
    }

    public function testGetContentTypeGrouping()
    {
        $app = $this->getApp();
        $this->assertEquals('chapters', $app['storage']->getContentTypeGrouping('pages'));
    }

    public function testGetContentTypeTaxonomy()
    {
        $app = $this->getApp();
        $expected = ['chapters'=>$app['config']->get('taxonomy/chapters')];
        $this->assertEquals($expected, $app['storage']->getContentTypeTaxonomy('pages'));
        $this->assertEquals([], $app['storage']->getContentTypeTaxonomy('nonexistent'));
    }

    public function testGetLatestId()
    {
        $app = $this->getApp();
        $this->assertEquals('5', $app['storage']->getLatestId('pages'));
    }

    public function testGetUri()
    {
        $app = $this->getApp();
        $this->assertEquals('/page/this-is-a-test', $app['storage']->getUri('This is a test', 1, 'pages'));
        $this->assertEquals('/page/page-100', $app['storage']->getUri('100', 1, 'pages'));
        $this->assertEquals('/page/page-100', $app['storage']->getUri('100', 1, 'pages', true, true, 'title'));
        $this->assertEquals('/page/page-100', $app['storage']->getUri('100', 1, 'pages', true, true, 'nonexistent'));
    }

    public function testSetPager()
    {
    }

    public function testGetPager()
    {
    }

    private function getDbMockBuilder(Connection $db)
    {
        return $this->getMockBuilder('\Doctrine\DBAL\Connection')
            ->setConstructorArgs([$db->getParams(), $db->getDriver(), $db->getConfiguration(), $db->getEventManager()])
            ->enableOriginalConstructor()
        ;
    }
}

class StorageMock extends Storage
{
    public $queries = [];

    protected function executeGetContentQueries($decoded)
    {
        $this->queries[] = $decoded;
        return parent::executeGetContentQueries($decoded);
    }
}
