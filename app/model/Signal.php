<?php
namespace rosasurfer\xtrade\model;

use rosasurfer\db\orm\PersistableObject;
use rosasurfer\util\Date;


/**
 * Signal
 */
class Signal extends PersistableObject {


    /** @var int - primary key */
    protected $id;

    /** @var string - time of creation */
    protected $created;

    /** @var string - time of last modification */
    protected $version;

    /** @var string */
    protected $provider;

    /** @var string */
    protected $providerId;

    /** @var string */
    protected $name;

    /** @var string */
    protected $alias;

    /** @var string */
    protected $currency;


    // Simple getters
    public function getId()         { return $this->id;         }
    public function getProvider()   { return $this->provider;   }
    public function getProviderId() { return $this->providerId; }
    public function getName()       { return $this->name;       }
    public function getAlias()      { return $this->alias;      }
    public function getCurrency()   { return $this->currency;   }


    /**
     * Return the creation time of the instance.
     *
     * @param  string $format - format as used by date($format, $timestamp)
     *
     * @return string - creation time
     */
    public function getCreated($format = 'Y-m-d H:i:s')  {
        if ($format == 'Y-m-d H:i:s')
            return $this->created;
        return Date::format($this->created, $format);
    }


    /**
     * Return the version string of the instance.
     *
     * @param  string $format - format as used by date($format, $timestamp)
     *
     * @return string - version (last modification time)
     */
    public function getVersion($format = 'Y-m-d H:i:s')  {
        if ($format == 'Y-m-d H:i:s')
            return $this->version;
        return Date::format($this->version, $format);
    }


    /**
     * Creation post-processing hook (application-side ORM trigger).
     */
    protected function afterCreate() {
        $this->created = $this->version = date('Y-m-d H:i:s');
    }


    /**
     * Update pre-processing hook (application-side ORM trigger).
     *
     * {@inheritdoc}
     */
    protected function beforeUpdate() {
        $this->version = date('Y-m-d H:i:s');
        return true;
    }
}
