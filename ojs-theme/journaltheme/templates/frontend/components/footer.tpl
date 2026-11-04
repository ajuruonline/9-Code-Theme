{**
 * Open Scholar theme: footer (mobile first).
 * Phones: a light, stacked footer with a green top rule. Wide screens: three columns.
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

	<footer class="os-footer pkp_structure_footer_wrapper" role="contentinfo">
		<a id="pkp_content_footer"></a>
		<div class="os-footer__inner">
			<div class="os-footer__brand">
				<img class="os-footer__cap" src="{$baseUrl}/plugins/themes/journaltheme/images/open-scholar-cap.jpg" alt="" width="36" height="36">
				<div>
					<p class="os-footer__name">{if $currentContext}{$displayPageHeaderTitle|escape}{else}{$siteTitle|escape}{/if}</p>
					<p class="os-footer__by">Open Scholar</p>
				</div>
			</div>
			<div class="os-acc-group">
				<details class="os-acc">
					<summary class="os-acc__sum">Read the journal</summary>
					<div class="os-acc__body"><ul class="os-acc__list">
						<li><a href="{url page="issue" op="current"}">Current issue</a></li>
						<li><a href="{url page="issue" op="archive"}">Archives</a></li>
					</ul></div>
				</details>
				<details class="os-acc">
					<summary class="os-acc__sum">Publish with us</summary>
					<div class="os-acc__body"><ul class="os-acc__list">
						<li><a href="{url page="submissions"}">Submit a paper</a></li>
					</ul></div>
				</details>
				<details class="os-acc">
					<summary class="os-acc__sum">About the journal</summary>
					<div class="os-acc__body"><ul class="os-acc__list">
						<li><a href="{url page="about"}">About</a></li>
						<li><a href="{url page="about" op="editorialTeam"}">Editorial team</a></li>
						<li><a href="{url page="about" op="contact"}">Contact</a></li>
					</ul></div>
				</details>
				<details class="os-acc">
					<summary class="os-acc__sum">Your account</summary>
					<div class="os-acc__body"><ul class="os-acc__list">
						<li><a href="{url page="login"}">Log in</a></li>
						<li><a href="{url page="user" op="register"}">Register</a></li>
					</ul></div>
				</details>
			</div>
			{if $pageFooter}
				<div class="os-footer__content pkp_footer_content">
					{$pageFooter}
				</div>
			{/if}
		</div>
	</footer><!-- .os-footer -->

</div><!-- pkp_structure_page -->

{load_script context="frontend"}

{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>
