<?php
namespace Bolt\Storage\Repository;

use Bolt\Storage\Repository;

/**
 * A Repository class that handles storage operations for the Cron table.
 */
class CronRepository extends Repository
{

 
    public function getNextRunTimes($interimName)
    {
        $query = $this->queryNextRunTimes($interimName);
        return $this->findWith($query);
    }
    
    public function queryNextRunTimes($interimName)
    {
        $oldname = strtolower(str_replace('cron.', '', $interimName));
        $qb = $this->createQueryBuilder();
        $qb->select('id, lastrun, interim')
            ->where('(interim = :interim OR interim = :oldname)')
            ->orderBy('lastrun', 'DESC')
            ->setParameter('interim', $interimName)
            ->setParameter('oldname', $oldname);
        return $qb;
    }
    
    public function createQueryBuilder($alias = NULL, $indexBy = NULL)
    {
        return $this->em->createQueryBuilder()
            ->from($this->getTableName());
    }


}
