{**
 * plugins/themes/openscholar/templates/frontend/components/header.tpl
 *
 * Open Scholar header. Shows the journal brand at the top of the page. The menu, search and
 * sign-in are in the navigation bar (navigation.tpl is rendered after the footer), so the page
 * scrolls in its own area and the bar never sits on top of content. Uses the same OJS menus
 * and skip links, so every OJS page and link keeps working. Distributed under the GNU GPL v3.
 *
 * @uses $isFullWidth bool Page-level override: hide the sidebars.
 *}
{strip}
	{assign var="showingLogo" value=true}
	{if !$displayPageHeaderLogo}
		{assign var="showingLogo" value=false}
	{/if}
{/strip}
<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
{if !$pageTitleTranslated}{capture assign="pageTitleTranslated"}{translate key=$pageTitle}{/capture}{/if}
{include file="frontend/components/headerHead.tpl"}
<body class="pkp_page_{$requestedPage|escape|default:"index"} pkp_op_{$requestedOp|escape|default:"index"}{if $showingLogo} has_site_logo{/if} os-theme" dir="{$currentLocaleLangDir|escape|default:"ltr"}">

	<div class="pkp_structure_page os-page">

		<header class="pkp_structure_head os-head" id="headerNavigationContainer" role="banner">
			{include file="frontend/components/skipLinks.tpl"}
			{capture assign="homeUrl"}{url page="index" router=PKP\core\PKPApplication::ROUTE_PAGE}{/capture}
			<a class="os-head__brand" href="{$homeUrl}">
				<img class="os-head__cap" src="{$baseUrl}/plugins/themes/openscholar/images/open-scholar-cap.jpg" alt="" width="40" height="40">
				<span class="os-head__name">{if $currentContext}{$displayPageHeaderTitle|escape}{else}{$siteTitle|escape}{/if}</span>
			</a>
		</header><!-- .pkp_structure_head -->

		{* Wrapper for page content and sidebars *}
		{if $isFullWidth}
			{assign var=hasSidebar value=0}
		{/if}
		<div class="pkp_structure_content{if $hasSidebar} has_sidebar{/if}">
			<div class="pkp_structure_main" role="main">
				<a id="pkp_content_main"></a>
