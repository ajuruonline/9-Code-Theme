{**
 * plugins/themes/openscholar/templates/frontend/components/footer.tpl
 *
 * Open Scholar footer, then the navigation bar. The bar is outside the scrolling page area,
 * so it takes its own space at the bottom of the screen (phones) or the top (desktop) and
 * never covers content. Distributed under the GNU GPL v3.
 *
 * @uses $isFullWidth bool Page-level override: hide the sidebars.
 *}

		</div><!-- pkp_structure_main -->

		{* Sidebars *}
		{if empty($isFullWidth)}
			{capture assign="sidebarCode"}{call_hook name="Templates::Common::Sidebar"}{/capture}
			{if $sidebarCode}
				<div class="pkp_structure_sidebar left" role="complementary">
					{$sidebarCode}
				</div><!-- pkp_sidebar.left -->
			{/if}
		{/if}
	</div><!-- pkp_structure_content -->

	<footer class="os-footer pkp_structure_footer_wrapper" role="contentinfo">
		<a id="pkp_content_footer"></a>

		<div class="os-footer__inner">
			<div class="os-footer__brand">
				<img class="os-footer__cap" src="{$baseUrl}/plugins/themes/openscholar/images/open-scholar-cap.jpg" alt="" width="36" height="36">
				<div>
					<p class="os-footer__name">{if $currentContext}{$displayPageHeaderTitle|escape}{else}{$siteTitle|escape}{/if}</p>
					<p class="os-footer__by">Open Scholar</p>
				</div>
			</div>

			{if $pageFooter}
				<div class="os-footer__content pkp_footer_content">
					{$pageFooter}
				</div>
			{/if}

			<div class="os-footer__powered pkp_brand_footer">
				<a href="{url page="about" op="aboutThisPublishingSystem"}">
					<img alt="{translate key="about.aboutThisPublishingSystem"}" src="{$baseUrl}/{$brandImage}">
				</a>
			</div>
		</div>
	</footer><!-- .os-footer -->

</div><!-- pkp_structure_page -->

{* Navigation bar: bottom of the screen on phones, top of the page on desktop. *}
<nav class="pkp_site_nav_menu os-nav" aria-label="{translate|escape key="common.navigation.site"}">
	<a id="siteNav"></a>
	<div class="os-nav__bar">
		<a class="os-nav__brand" href="{url page="index" router=PKP\core\PKPApplication::ROUTE_PAGE}">
			<img src="{$baseUrl}/plugins/themes/openscholar/images/open-scholar-cap.jpg" alt="" width="32" height="32">
			<span>{if $currentContext}{$displayPageHeaderTitle|escape}{else}{$siteTitle|escape}{/if}</span>
		</a>
		<button type="button" class="pkp_site_nav_toggle os-nav__toggle" aria-expanded="false" aria-controls="os-nav-sheet">
			<span class="os-nav__toggle-icon" aria-hidden="true"></span>
			<span class="os-nav__toggle-label">{translate|escape key="common.navigation.site"}</span>
		</button>
		{if $currentContext && $requestedPage !== 'search'}
			<a href="{url page="search"}" class="os-nav__search">
				<span class="fa fa-search" aria-hidden="true"></span>
				<span class="os-nav__search-label">{translate key="common.search"}</span>
			</a>
		{/if}
	</div>
	<div class="os-nav__sheet" id="os-nav-sheet">
		<p class="os-nav__sheet-title">Pages</p>
		{load_menu name="primary" id="navigationPrimary" ulClass="pkp_navigation_primary os-nav__links"}
		<div class="os-nav__account" id="navigationUserWrapper">
			{load_menu name="user" id="navigationUser" ulClass="pkp_navigation_user os-nav__account-menu" liClass="profile"}
		</div>
	</div>
</nav>

{load_script context="frontend"}

{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>
