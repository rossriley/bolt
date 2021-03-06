<?php

namespace Bolt\Tests\Extension\Mock;

use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;

/**
 * Mock extension that extends SimpleExtension for testing the MenuTrait.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class MenuExtension extends SimpleExtension
{
    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->addMenuEntry('Drop Bear', 'look-up-live', 'fa-thumbs-o-down', 'dangerous');
    }

    /**
     * {@inheritdoc}
     */
    protected function registerMenuEntries()
    {
        return [
            (new MenuEntry('koala', 'koalas-are-us'))
                ->setLabel('Koalas')
                ->setIcon('fa-thumbs-o-up')
                ->setPermission('config')
        ];
    }
}
