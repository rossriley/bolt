<?php
namespace Bolt\Storage\Field\Type;

use Bolt\Storage\EntityManager;
use Bolt\Storage\Hydrator;
use Bolt\Storage\Persister;
use Bolt\Storage\QuerySet;
use Bolt\Storage\Mapping\ClassMetadata;
use Bolt\Storage\Mapping\ContentType;
use Bolt\Storage\Mapping\MetadataDriver;
use Bolt\TemplateChooser;
use Doctrine\DBAL\Types\Type;

/**
 * This is one of a suite of basic Bolt field transformers that handles
 * the lifecycle of a field from pre-query to persist.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class TemplateFieldsType extends FieldTypeBase
{
    public $mapping;
    public $em;
    public $chooser;
    
    public function __construct(array $mapping = [], EntityManager $em, TemplateChooser $chooser = null)
    {
        $this->mapping = $mapping;
        $this->chooser = $chooser;
        $this->em = $em;
        if ($em) {
            $this->setPlatform($em->createQueryBuilder()->getConnection()->getDatabasePlatform());
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function hydrate($data, $entity)
    {
        $key = $this->mapping['fieldname'];
        $type = $this->getStorageType();
        $value = $type->convertToPHPValue($data[$key], $this->getPlatform());
        
        if ($value) {
            $this->set($entity, $value);
        }
    }
    
    public function set($entity, $value)
    {
        $key = $this->mapping['fieldname'];
        $metadata = $this->buildMetadata($entity);
        
        $type = (string)$entity->getContenttype();
        $builder = $this->em->getEntityBuilder();
        $templatefieldsEntity = $builder->createFromDatabaseValues($type, $value, $metadata);
        
        $ct = new ContentType('templatefields', ['fields' => $metadata->getFieldMappings()]);
        $templatefieldsEntity->setContenttype($ct);
        
        $entity->$key = $templatefieldsEntity;
    }
    
    public function persist(QuerySet $queries, $entity)
    {
        $key = $this->mapping['fieldname'];
        $qb = &$queries[0];
        $valueMethod = 'serialize'.ucfirst($key);
        $value = $entity->$valueMethod();

        $type = $this->getStorageType();

        if (null !== $value) {
            
            $metadata = $this->buildMetadata($entity);
            $persister = new Persister($metadata);
            $newValue = $persister->persist($queries, $entity, $this->em);
            
            $value = $type->convertToDatabaseValue($newValue, $this->getPlatform());
        } else {
            $value = $this->mapping['default'];
        }
        $qb->setValue($key, ":".$key);
        $qb->set($key, ":".$key);
        $qb->setParameter($key, $value);
    }
    
    protected function buildMetadata($entity)
    {
        $template = $this->chooser->record($entity);
        $metadata = new ClassMetadata(get_class($entity));
        
        if (isset($this->mapping['config'][$template])) {
            $mappings = $this->em->getMapper()->loadMetadataForFields($this->mapping['config'][$template]['fields']);
            $metadata->setFieldMappings($mappings);
        }
        
        return $metadata;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'templatefields';
    }

    /**
     * {@inheritdoc}
     */
    public function getStorageType()
    {
        return Type::getType('json_array');
    }
}
