<?php

/**
 * @file plugins/generic/articleJournalDisplay/ArticleJournalDisplayPlugin.php
 *
 * Article and journal display. A general plugin: it works with whichever theme is selected.
 * It shows article lists and article pages in the ePublication article style: white cards,
 * a genre rail, bold titles and green outline buttons. OJS itself is not changed.
 * Distributed under the GNU GPL v3.
 *
 * @class ArticleJournalDisplayPlugin
 */

namespace APP\plugins\generic\articleJournalDisplay;

use APP\core\Application;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class ArticleJournalDisplayPlugin extends GenericPlugin
{
    /**
     * @copydoc GenericPlugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('TemplateManager::display', $this->applyArticleDisplay(...));
        }
        return $success;
    }

    /**
     * Adds the article display stylesheet to public (frontend) pages.
     */
    public function applyArticleDisplay(string $hookName, array $args): bool
    {
        $template = $args[1];
        $output =& $args[2];

        if (!is_string($template) || !str_starts_with($template, 'frontend/')) {
            return false;
        }
        if (!is_string($output) || strpos($output, 'pkp_structure_page') === false) {
            return false;
        }

        $request = Application::get()->getRequest();
        $href = htmlspecialchars($request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/article-journal-display.css', ENT_QUOTES, 'UTF-8');

        // Added last in the head so it overrides the theme's article styles.
        $output = preg_replace('~</head>~i', '<link rel="stylesheet" href="' . $href . '">' . "\n</head>", $output, 1);

        return false;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return 'Article and Journal Display';
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return 'Shows articles and article pages in the ePublication style: white cards, a genre colour rail, bold titles and green outline buttons. Works with any theme.';
    }
}
