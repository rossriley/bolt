<?php
namespace Bolt\Storage\Field\Type;

use Doctrine\DBAL\Types\Type;

/**
 * This is one of a suite of basic Bolt field transformers that handles
 * the lifecycle of a field from pre-query to persist.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class TemplateFieldsType extends FieldTypeBase
{
    
    /**
     * {@inheritdoc}
     */
    public function hydrate($data, $entity, EntityManager $em = null)
    {
        
        $key = $this->mapping['fieldname'];
        $type = $this->getStorageType();
        $value = $type->convertToPHPValue($data[$key], $em->createQueryBuilder()->getConnection()->getDatabasePlatform());
        
        $repo = $em->getRepository(get_class($entity));
        $templateEntity = $repo->hydrate($value);

        $entity->templatefields = $templateEntity;
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
