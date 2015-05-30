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
        $cron->execute();
        
        $result = $output->fetch();
        
        var_dump($output);

    }
}
