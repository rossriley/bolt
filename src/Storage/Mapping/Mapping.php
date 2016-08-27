<?php
namespace Bolt\Storage\Mapping;

/**
 * Class to store mapping information.
 * Mapping describes the meta information that converts a PHP object to
 * data that can be stored in a database.
 *
 * In Bolt the source of mapping information comes either from introspection of the database,
 * or by user definitions inside the contenttypes.yml configuration file.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 */
class Mapping
{

    protected $fieldname;
    protected $attribute;
    protected $type;
    protected $fieldtype;
    protected $length;
    protected $nullable;
    protected $platformOptions;
    protected $precision;
    protected $scale;
    protected $default;
    protected $columnDefinition;
    protected $autoincrement;

    /**
     * @var Mapping
     */
    protected $parent;

    public function __construct()
    {

    }

    /**
     * @return mixed
     */
    public function getFieldname()
    {
        return $this->fieldname;
    }

    /**
     * @return mixed
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getFieldtype()
    {
        return $this->fieldtype;
    }

    /**
     * @return mixed
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @return mixed
     */
    public function getNullable()
    {
        return $this->nullable;
    }

    /**
     * @return mixed
     */
    public function getPlatformOptions()
    {
        return $this->platformOptions;
    }

    /**
     * @return mixed
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return mixed
     */
    public function getColumnDefinition()
    {
        return $this->columnDefinition;
    }

    /**
     * @return mixed
     */
    public function getAutoincrement()
    {
        return $this->autoincrement;
    }

    /**
     * @param Mapping $parent
     */
    public function setParent(Mapping $parent)
    {
        $this->parent = $parent;
    }

    /**
     * @return Mapping
     */
    public function getParent()
    {
        return $this->parent;
    }


}