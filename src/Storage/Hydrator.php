<?php
namespace Bolt\Storage;

use Bolt\Storage\Field\Type\FieldTypeInterface;
use Bolt\Storage\Mapping\ClassMetadata;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Maps raw sql query data to Bolt Entities
 */
class Hydrator
{
    /** @var string */
    protected $handler;
    /** @var ClassMetadata */
    protected $metadata;
    /** @var FieldFactory */
    protected $fieldFactory;
    

    /**
     * Constructor.
     *
     * @param ClassMetadata $metadata
     */
    public function __construct(ClassMetadata $metadata, FieldFactory $fieldFactory = null)
    {
        $classHandler = $metadata->getName();
        if (!class_exists($classHandler)) {
            throw new \InvalidArgumentException("Value supplied $classHandler is not a valid class name", 1);
        }
        $this->handler = $classHandler;
        $this->metadata = $metadata;
        $this->fieldFactory = $fieldFactory;
    }
    
    /**
     * Creates an entity ready for hydration.
     * 
     * @param  array  $source [description]
     * @return [type]         [description]
     */
    public function create(array $source)
    {
        $classname = $this->handler;
        $entity = new $classname;
        $entity->setContenttype($this->metadata->getBoltName());
        
        return $entity;
    }

    /**
     * @param array         $source data
     * @param QueryBuilder  $qb
     * @param EntityManager $em
     *
     * @return mixed Entity
     */
    public function hydrate($entity, QueryBuilder $qb = null, EntityManager $em = null)
    {

        foreach ($this->metadata->getFieldMappings() as $key => $mapping) {
            // First step is to allow each Bolt field to transform the data.
            /** @var FieldTypeInterface $field */
            if ($this->fieldFactory !== null) {
                $field = $this->fieldFactory->get($mapping['fieldtype'], $mapping);
            } else {
                $field = new $mapping['fieldtype']($mapping);
            }
            
            $field->hydrate($source, $entity, $em);
        }

        return $entity;
    }
}
