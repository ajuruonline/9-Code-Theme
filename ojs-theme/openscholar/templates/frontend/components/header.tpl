{**
 * plugins/themes/openscholar/templates/frontend/components/header.tpl
 *
 * Open Scholar header. Replaces the OJS site header with the Open Scholar bar and menu.
 * The same OJS menus (primary and user), search and skip links are used, so every OJS
 * page and link keeps working. Distributed under the GNU GPL v3.
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

	<div class="pkp_structure_page">

		{* Open Scholar header *}
		<header class="pkp_structure_head os-head" id="headerNavigationContainer" role="banner">
			{include file="frontend/components/skipLinks.tpl"}

			<div class="os-topbar">
				{capture assign="homeUrl"}{url page="index" router=PKP\core\PKPApplication::ROUTE_PAGE}{/capture}
				<a class="os-brand" href="{$homeUrl}">
					<img class="os-brand__cap" src="{$baseUrl}/plugins/themes/openscholar/images/open-scholar-cap.jpg" alt="Open Scholar" width="40" height="40">
					<span class="os-brand__name">
						{if $currentContext}{$displayPageHeaderTitle|escape}{else}{$siteTitle|escape}{/if}
					</span>
				</a>

				<div class="os-topbar__actions">
					{if $currentContext && $requestedPage !== 'search'}
						<a href="{url page="search"}" class="os-icon-link">
							<span class="fa fa-search" aria-hidden="true"></span>
							<span class="os-icon-link__label">{translate key="common.search"}</span>
						</a>
					{/if}
					<div class="os-account" id="navigationUserWrapper">
						{load_menu name="user" id="navigationUser" ulClass="pkp_navigation_user os-account__menu" liClass="profile"}
					</div>
				</div>
			</div>

			<nav class="pkp_site_nav_menu os-nav" aria-label="{translate|escape key="common.navigation.site"}">
				<a id="siteNav"></a>
				<button class="pkp_site_nav_toggle os-nav__toggle" type="button" aria-controls="navigationPrimary">
					<span class="os-nav__toggle-icon" aria-hidden="true"></span>
					<span class="os-nav__toggle-label">{translate|escape key="common.navigation.site"}</span>
				</button>
				<div class="os-nav__inner">
					{capture assign="primaryMenu"}
						{load_menu name="primary" id="navigationPrimary" ulClass="pkp_navigation_primary"}
					{/capture}
					{$primaryMenu}
				</div>
			</nav>
		</header><!-- .pkp_structure_head -->

		{* Wrapper for page content and sidebars *}
		{if $isFullWidth}
			{assign var=hasSidebar value=0}
		{/if}
		<div class="pkp_structure_content{if $hasSidebar} has_sidebar{/if}">
			<div class="pkp_structure_main" role="main">
				<a id="pkp_content_main"></a>
