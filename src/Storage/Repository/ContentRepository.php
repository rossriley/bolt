<?php
namespace Bolt\Storage\Repository;

use Bolt\Storage\Repository;

/**
 * A Repository class that handles dynamically created content tables.
 */
class ContentRepository extends Repository
{

    /**
     * @inheritdoc
     */
    public function createQueryBuilder($alias = null, $indexBy = null)
    {
        if (null === $alias) {
            $alias = $this->getAlias();
        }
        return $this->em->createQueryBuilder()
            ->select($alias.".*")
            ->from($this->getTableName(), $alias);
    }

    /**
     * Creates a new Content entity and passes the supplied data to the constructor.
     *
     * @param array $params
     *
     * @return Content
     */
    public function create($params = null)
    {
        $entityClass = $this->getClassName();
        return new $entityClass($params);
    }
}
