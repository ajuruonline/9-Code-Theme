{**
 * plugins/themes/openscholar/templates/frontend/components/footer.tpl
 *
 * Open Scholar footer. Shows the journal name, the journal's own footer content and the
 * "powered by Open Journal Systems" mark that OJS already provides.
 * Distributed under the GNU GPL v3.
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

<footer class="ninestagram-author-footer os-footer pkp_structure_footer_wrapper" role="contentinfo">
	<a id="pkp_content_footer"></a>

	<div class="ninestagram-footer-inner os-footer__inner">
		<div class="ninestagram-footer-strip os-footer__brand">
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

{load_script context="frontend"}

{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>
