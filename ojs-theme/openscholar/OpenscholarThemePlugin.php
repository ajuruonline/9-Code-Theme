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

        // Loaded after the parent's styles, so these rules win where they overlap.
        $this->addStyle('openscholar', 'styles/openscholar.css');
    }

    /**
     * @copydoc ThemePlugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return 'Open Scholar';
    }

    /**
     * @copydoc ThemePlugin::getDescription()
     */
    public function getDescription()
    {
        return 'The Open Scholar header, footer and look for journals. Built on the default theme; no OJS code is changed.';
    }
}
