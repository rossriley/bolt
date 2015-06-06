<?php
namespace Bolt\Tests\Cron;

use Bolt\Cron;
use Bolt\Tests\BoltUnitTest;
use Symfony\Component\Console\Output\BufferedOutput;


/**
 * Class to test src/Field/Base.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 */
class CronTest extends BoltUnitTest
{
    public function testConstruct()
    {
        $app = $this->getApp();        
        $output = new BufferedOutput();
        
        $cron = new Cron($app, $output);        
        $result = explode("\n", $output->fetch());
        
        $this->assertEquals("Running Cron Hourly Jobs", $result[0]);
        $this->assertEquals("Running Cron Daily Jobs", $result[1]);
        $this->assertEquals("Running Cron Weekly Jobs", $result[2]);
        $this->assertEquals("Clearing cache", trim($result[3]));
        $this->assertEquals("Trimming logs", trim($result[4]));
        $this->assertEquals("Running Cron Monthly Jobs", $result[5]);
        $this->assertEquals("Running Cron Yearly Jobs", $result[6]);
        
        // Now test that the interval times are in the DB
        $repo = $app['storage']->getRepository('Bolt\Entity\Cron');
        $results = $repo->getNextRunTimes('cron.Hourly');
        print_r($results);
        
        print_r($cron->jobs);
        
    }
    
    public function testSecondRun()
    {
        $app = $this->getApp();        
        $output = new BufferedOutput();
        $cron = new Cron($app, $output);
    }
}
