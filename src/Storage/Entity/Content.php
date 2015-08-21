<?php
namespace Bolt\Storage\Entity;

use Bolt\Storage\ContentLegacyService;

/**
 * Entity for Content.
 *
 * @method integer getId()
 * @method string  getSlug()
 * @method integer getOwnerid()
 * @method string  getStatus()
 * @method array   getTemplatefields()
 * @method setId(integer $id)
 * @method setSlug(string  $slug)
 * @method setOwnerid(integer $ownerid)
 * @method setStatus(string  $status)
 * @method getTemplatefields(array $templatefields)
 */
class Content extends Entity
{
    protected $_contenttype;
    protected $_legacy;
    protected $id;
    protected $slug;
    protected $datecreated;
    protected $datechanged;
    protected $datepublish = null;
    protected $datedepublish = null;
    protected $ownerid;
    protected $status;
    protected $templatefields;

    public function getDatecreated()
    {
        if (!$this->datecreated) {
            return new \DateTime();
        }

        return $this->datecreated;
    }

    public function getDatechanged()
    {
        if (!$this->datechanged) {
            return new \DateTime();
        }

        return $this->datechanged;
    }

    public function getContenttype()
    {
        return $this->_contenttype;
    }

    public function setContenttype($value)
    {
        $this->_contenttype = $value;
    }
    
    public function setLegacyService(ContentLegacyService $service)
    {
        $this->_legacy = $service;
        $this->_legacy->initialize($this);
    }
    
    public function get($key)
    {
        $val = $this->$key;
        if ($val instanceof \DateTime) {
            return $val->format("Y-m-d H:i:s");
        }
        
        return $val;
    }
}
