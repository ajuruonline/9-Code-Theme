{**
 * Open Scholar theme: header (mobile first).
 * One top bar on every width: the journal brand, Search and a Menu button. The menu is part
 * of this header: on phones it opens as a squared panel over the page; on wide screens the
 * links sit in the same bar. The page itself scrolls below the header.
 * Distributed under the GNU GPL v3.
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
			<div class="os-bar">
				<a class="os-brand" href="{$homeUrl}">
					<img class="os-brand__cap" src="{$baseUrl}/plugins/themes/journaltheme/images/open-scholar-cap.jpg" alt="" width="40" height="40">
					<span class="os-brand__name">{if $currentContext}{$displayPageHeaderTitle|escape}{else}{$siteTitle|escape}{/if}</span>
				</a>
				<div class="os-bar__actions">
					{if $currentContext && $requestedPage !== 'search'}
						<a href="{url page="search"}" class="os-search">
							<span class="os-search__label">{translate key="common.search"}</span>
						</a>
					{/if}
					<button type="button" class="pkp_site_nav_toggle os-toggle" aria-expanded="false" aria-controls="os-nav-sheet">
						<span class="os-toggle__icon" aria-hidden="true"></span>
						<span class="os-toggle__label">Menu</span>
					</button>
				</div>
			</div>

			<nav class="pkp_site_nav_menu os-nav" aria-label="{translate|escape key="common.navigation.site"}">
				<a id="siteNav"></a>
				<div class="os-nav__sheet" id="os-nav-sheet">
					<p class="os-nav__title">Pages</p>
					{load_menu name="primary" id="navigationPrimary" ulClass="pkp_navigation_primary os-nav__links"}
					<div class="os-nav__account" id="navigationUserWrapper">
						{load_menu name="user" id="navigationUser" ulClass="pkp_navigation_user os-nav__account-menu" liClass="profile"}
					</div>
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
{if !$requestedPage || $requestedPage == 'index'}
				<section class="os-dash" aria-labelledby="os-dash-title">
					<h2 class="os-dash__title" id="os-dash-title">Start here</h2>
					<div class="os-dash__grid">
						<a class="os-tile" href="{url page="issue" op="current"}"><span class="os-tile__no">01</span><span class="os-tile__label">Current issue</span></a>
						<a class="os-tile" href="{url page="issue" op="archive"}"><span class="os-tile__no">02</span><span class="os-tile__label">Archives</span></a>
						<a class="os-tile" href="{url page="submissions"}"><span class="os-tile__no">03</span><span class="os-tile__label">Submit a paper</span></a>
						<a class="os-tile" href="{url page="about"}"><span class="os-tile__no">04</span><span class="os-tile__label">About</span></a>
						<a class="os-tile" href="{url page="search"}"><span class="os-tile__no">05</span><span class="os-tile__label">Search</span></a>
						<a class="os-tile" href="{url page="about" op="contact"}"><span class="os-tile__no">06</span><span class="os-tile__label">Contact</span></a>
						<a class="os-tile" href="{url page="login"}"><span class="os-tile__no">07</span><span class="os-tile__label">Log in</span></a>
						<a class="os-tile" href="{url page="user" op="register"}"><span class="os-tile__no">08</span><span class="os-tile__label">Register</span></a>
					</div>
				</section>
{/if}
