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

        // Test delete fails on missing params
        $this->setExpectedException('Bolt\Exception\StorageException', 'Contenttype is required for deleteContent');
        $this->assertFalse($storage->deleteContent('', 999));

        $content = $storage->getContent('showcases/1');

        // Test the delete events are triggered
        $delete = 0;
        $listener = function () use (&$delete) {
            $delete++;
        };
        $app['dispatcher']->addListener(StorageEvents::PRE_DELETE, $listener);
        $app['dispatcher']->addListener(StorageEvents::POST_DELETE, $listener);

        $storage->deleteContent(['slug' => 'showcases'], 1);

        $this->assertFalse($storage->getContent('showcases/1'));
        $this->assertEquals(2, $delete);
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
        $results = $storage->searchAllContentTypes(['title' => 'lorem']);
    }

    public function testSearchContentType()
    {
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

    public function testGetContent()
    {
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
    }

    public function testGetContentTypeTaxonomy()
    {
    }

    public function testGetLatestId()
    {
    }

    public function testGetUri()
    {
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
