<?php

/**
 * @file plugins/themes/openscholar/OpenscholarThemePlugin.php
 *
 * Open Scholar theme for Open Journal Systems.
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenscholarThemePlugin
 *
 * @brief A child of the default OJS theme. It keeps every OJS page, menu, form and
 * setting, and changes only the header, the footer and the look (colours, type, spacing)
 * so that the journal matches Open Scholar. OJS itself is not modified.
 */

namespace APP\plugins\themes\openscholar;

use PKP\plugins\ThemePlugin;

class OpenscholarThemePlugin extends ThemePlugin
{
    /**
     * Initialise the theme. The default theme supplies the layout, scripts and
     * behaviour; this theme's templates and stylesheet are applied on top of it.
     */
    public function init()
    {
        $this->setParent('default');

        $this->addOption('accentColour', 'FieldColor', [
            'label' => 'Accent colour',
            'description' => 'The main green used for the header, buttons and footer. Pick a dark colour so white text stays easy to read.',
            'default' => '#14532d',
        ]);

        // The Open Scholar look, loaded after the default theme's styles.
        $this->addStyle('openscholar', 'styles/openscholar.css');

        $accent = $this->getOption('accentColour');
        if (is_string($accent) && preg_match('/^#[0-9a-fA-F]{6}$/', $accent) && strtolower($accent) !== '#14532d') {
            $this->addStyle('openscholarAccent', ':root{--os-green:' . $accent . ';--os-green-2:' . $accent . ';}', ['inline' => true]);
        }

        // Menu accessibility: Escape closes it, aria-expanded stays correct.
        $this->addScript('openscholar', 'js/openscholar.js');
    }

    /**
     * @copydoc ThemePlugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return 'Open Journal System';
    }

    /**
     * @copydoc ThemePlugin::getDescription()
     */
    public function getDescription()
    {
        return 'The Open Scholar header, navigation, footer and look for journals. Built on the default theme; no OJS code is changed.';
    }
}
