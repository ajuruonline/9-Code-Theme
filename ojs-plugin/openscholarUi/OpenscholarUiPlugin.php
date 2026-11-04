<?php

/**
 * @file plugins/generic/openscholarUi/OpenscholarUiPlugin.php
 *
 * Open Scholar UI. A general plugin: it works with whichever theme is selected.
 * For public pages it adds the Open Scholar stylesheet, a journal brand strip at the top and
 * a brand line in the footer. OJS itself is not changed.
 * Distributed under the GNU GPL v3.
 *
 * @class OpenscholarUiPlugin
 */

namespace APP\plugins\generic\openscholarUi;

use APP\core\Application;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class OpenscholarUiPlugin extends GenericPlugin
{
    /**
     * @copydoc GenericPlugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('TemplateManager::display', $this->applyOpenScholarUi(...));
        }
        return $success;
    }

    /**
     * Adds the Open Scholar look to public (frontend) pages.
     * Pages that already use the Open Scholar theme are left alone.
     */
    public function applyOpenScholarUi(string $hookName, array $args): bool
    {
        $templateManager = $args[0];
        $template = $args[1];
        $output =& $args[2];

        if (!is_string($template) || !str_starts_with($template, 'frontend/')) {
            return false;
        }
        if (!is_string($output) || strpos($output, 'pkp_structure_page') === false) {
            return false;
        }

        // Public pages show the journal, not the software: remove the OJS/PKP brand and its "about" links.
        $output = preg_replace('~<div class="pkp_brand_footer">.*?</div>~s', '', $output);
        $output = preg_replace('~<a[^>]*aboutThisPublishingSystem[^>]*>.*?</a>~s', '', $output);

        if (strpos($output, 'os-theme') !== false) {
            return false; // The Open Journal System theme already provides this look.
        }

        $request = Application::get()->getRequest();
        $base = $request->getBaseUrl() . '/' . $this->getPluginPath();
        $home = $request->url(null, 'index');
        $context = $templateManager->getTemplateVars('currentContext');
        $name = $context ? $context->getLocalizedName() : $request->getSite()->getLocalizedTitle();
        $name = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
        $cap = htmlspecialchars($base . '/images/open-scholar-cap.jpg', ENT_QUOTES, 'UTF-8');
        $home = htmlspecialchars($home, ENT_QUOTES, 'UTF-8');

        // Stylesheet: added last in the head so it can restyle the default layout.
        $css = '<link rel="stylesheet" href="' . htmlspecialchars($base . '/css/openscholar-ui.css', ENT_QUOTES, 'UTF-8') . '">';
        $output = preg_replace('~</head>~i', $css . "\n</head>", $output, 1);

        // Journal brand strip at the top of the page.
        $brand = '<div class="osui-brand"><a href="' . $home . '"><img src="' . $cap . '" alt="" width="40" height="40"><span>' . $name . '</span></a></div>';
        $output = preg_replace('~(<div class="pkp_structure_page[^"]*">)~', '$1' . $brand, $output, 1);

        // Brand line in the footer.
        $footer = '<div class="osui-footer-brand"><img src="' . $cap . '" alt="" width="32" height="32"><span>' . $name . ' &middot; Open Scholar</span></div>';
        $output = preg_replace('~(<div class="pkp_structure_footer[^"]*">)~', '$1' . $footer, $output, 1);

        return false;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return 'Open Scholar UI';
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return 'Gives the public pages the Open Scholar look: white pages, green accents, a journal brand strip at the top and a brand line in the footer. Works with any theme.';
    }
}
