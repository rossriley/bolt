<?php
namespace Bolt\Tests\Cron;

use Bolt\Cron;
use Bolt\Tests\BoltUnitTest;

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
        $cron = new Cron($app);
    }
}
