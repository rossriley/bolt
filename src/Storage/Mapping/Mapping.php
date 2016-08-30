<?php
namespace Bolt\Storage\Mapping;

use ArrayAccess;

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
class Mapping implements ArrayAccess
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

    /**
     * Mapping constructor.
     * @param array $defaults
     */
    public function __construct(array $defaults = [])
    {
        foreach ($defaults as $key => $default) {
            if(property_exists($this, $key)) {
                $this->{$key} = $default;
            }
        }
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


    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }
}