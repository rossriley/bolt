<?php

namespace Bolt\Extension;

use Bolt\Filesystem\Handler\DirectoryInterface;
use Bolt\Helpers\Str;
use Pimple as Container;

/**
 * Defined some base functionality for extensions.
 *
 * @author Carson Full <carsonfull@gmail.com>
 */
abstract class AbstractExtension implements ExtensionInterface
{
    /** @var Container */
    protected $container;
    /** @var DirectoryInterface */
    protected $baseDirectory;
    /** @var string */
    protected $relativeUrl;
    /** @var string */
    private $name;
    /** @var string */
    private $vendor;
    /** @var string */
    private $namespace;

    /**
     * {@inheritdoc}
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseDirectory(DirectoryInterface $directory)
    {
        $this->baseDirectory = $directory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseDirectory()
    {
        return $this->baseDirectory;
    }

    /**
     * {@inheritdoc}
     */
    public function setRelativeUrl($relativeUrl)
    {
        $this->relativeUrl = $relativeUrl;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRelativeUrl()
    {
        return $this->relativeUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getVendor() . '/' . $this->getName();
    }

    /**
     * {@inheritdoc}
     */
    final public function getName()
    {
        if ($this->name === null) {
            // Get name from class name without Extension suffix
            $parts = explode('\\', get_class($this));
            $name = array_pop($parts);
            $pos = strrpos($name, 'Extension');
            if ($pos !== false) {
                $name = substr($name, 0, $pos);
            }
            // If class name is "Extension" use last part of namespace.
            if ($name === '') {
                $name = array_pop($parts);
            }

            $this->name = $name;
        }

        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    final public function getVendor()
    {
        if ($this->vendor === null) {
            $namespace = $this->getNamespace();
            $name = Str::replaceFirst('Bolt\\Extension\\', '', $namespace);
            $pos = strpos($name, '\\');
            $this->vendor = $pos === false ? $name : substr($name, 0, $pos);
        }

        return $this->vendor;
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        if ($this->namespace === null) {
            $class = get_class($this);
            $this->namespace = substr($class, 0, strrpos($class, '\\'));
        }

        return $this->namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName()
    {
        return $this->getName();
    }

    /**
     * Return the container.
     *
     * Note: This is allows traits to access app without losing coding completion
     *
     * @return Container
     */
    protected function getContainer()
    {
        return $this->container;
    }
}
