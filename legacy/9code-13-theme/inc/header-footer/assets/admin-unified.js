(function(){'use strict';
document.addEventListener('DOMContentLoaded',function(){var root=document.querySelector('[data-elhh-admin]');if(!root)return;
 var tabs=[].slice.call(root.querySelectorAll('[data-elhh-tab]')),surfaces=[].slice.call(root.querySelectorAll('[data-elhh-surface]')),search=root.querySelector('[data-elhh-search]'),clear=root.querySelector('[data-elhh-clear]'),status=root.querySelector('[data-elhh-search-status]');
 var sectionState={};
 function groupFor(surface,detail){var name=surface.getAttribute('data-elhh-surface'),summary=(detail.querySelector('summary')||{}).textContent||'',id=detail.id||'';
  if(name==='header'){if(/^1\.\s/.test(summary))return 'setup';if(/^1A\.|^2\.|^3\./.test(summary))return 'identity';if(/^4\.|^5\./.test(summary))return 'navigation';if(/^9\.|^10\./.test(summary))return 'design';return 'advanced';}
  if(id.indexOf('sitewide')>=0)return 'setup';if(id.indexOf('identity')>=0)return 'identity';if(id.indexOf('navigation')>=0)return 'navigation';if(/modules|manager|copyright/.test(id))return 'content';if(id.indexOf('design')>=0)return 'design';return 'advanced';
 }
 function labelFor(g){return {setup:'On / Off',identity:'Identity',navigation:'Navigation',content:'Content',design:'Design',advanced:'Advanced'}[g]||g;}
 function initSectionTabs(surface){var details=[].slice.call(surface.querySelectorAll('details'));if(!details.length)return;var groups=[];details.forEach(function(d){var g=groupFor(surface,d);d.setAttribute('data-elhh-section-panel',g);if(groups.indexOf(g)<0)groups.push(g);});
  var nav=document.createElement('nav');nav.className='elhh-section-tabs';nav.setAttribute('aria-label',(surface.getAttribute('data-elhh-surface')||'')+' settings sections');nav.setAttribute('data-elhh-section-tabs','1');
  groups.forEach(function(g){var b=document.createElement('button');b.type='button';b.className='elhh-section-tab';b.setAttribute('data-elhh-section-tab',g);b.textContent=labelFor(g);nav.appendChild(b);});var anchor=surface.querySelector('.n9lh-admin,.n9f-admin-wrap');if(anchor)anchor.insertBefore(nav,anchor.children[1]||null);else surface.insertBefore(nav,surface.firstChild);
  function setGroup(g){sectionState[surface.getAttribute('data-elhh-surface')]=g;[].slice.call(nav.querySelectorAll('[data-elhh-section-tab]')).forEach(function(b){var on=b.getAttribute('data-elhh-section-tab')===g;b.classList.toggle('is-active',on);b.setAttribute('aria-selected',on?'true':'false');});details.forEach(function(d){d.hidden=d.getAttribute('data-elhh-section-panel')!==g;});}
  nav.addEventListener('click',function(e){var b=e.target.closest('[data-elhh-section-tab]');if(!b)return;setGroup(b.getAttribute('data-elhh-section-tab'));});setGroup(groups[0]);surface._elhhSetGroup=setGroup;surface._elhhDetails=details;
 }
 surfaces.forEach(initSectionTabs);
 function setTab(name){root.setAttribute('data-active-surface',name);tabs.forEach(function(b){var on=b.getAttribute('data-elhh-tab')===name;b.classList.toggle('is-active',on);b.setAttribute('aria-selected',on?'true':'false');});surfaces.forEach(function(s){s.hidden=s.getAttribute('data-elhh-surface')!==name;});try{var u=new URL(window.location.href);u.searchParams.set('surface',name);history.replaceState(null,'',u.toString());}catch(e){}}
 tabs.forEach(function(b){b.addEventListener('click',function(){if(search&&search.value)search.value='';applySearch('');setTab(b.getAttribute('data-elhh-tab'));});});
 function applySearch(q){q=(q||'').trim().toLowerCase();var count=0;if(!q){surfaces.forEach(function(s){(s._elhhDetails||[]).forEach(function(d){d.removeAttribute('data-elhh-filtered');});var g=sectionState[s.getAttribute('data-elhh-surface')];if(s._elhhSetGroup&&g)s._elhhSetGroup(g);});if(status)status.textContent='';setTab(root.getAttribute('data-active-surface')||'header');return;}
  surfaces.forEach(function(s){s.hidden=false;(s._elhhDetails||[]).forEach(function(d){var text=(d.textContent||'').toLowerCase(),match=text.indexOf(q)!==-1;d.hidden=!match;if(match){d.removeAttribute('data-elhh-filtered');d.open=true;count++;}else d.setAttribute('data-elhh-filtered','1');});});if(status)status.textContent=count?count+' matching setting section'+(count===1?'':'s')+'.':'No setting section matches “'+q+'”.';}
 if(search)search.addEventListener('input',function(){applySearch(search.value);});if(clear)clear.addEventListener('click',function(){if(search){search.value='';search.focus();}applySearch('');});
 root.querySelectorAll('.elhh-switch input[type=checkbox]').forEach(function(input){input.addEventListener('change',function(){var b=input.parentNode.querySelector('b');if(b)b.textContent=input.checked?'ON':'OFF';});});
 var sliderNames=['n9f_settings[footer_max_width]','n9f_settings[footer_padding_top]','n9f_settings[footer_padding_bottom]','n9f_settings[footer_logo_width]','n9f_settings[footer_column_gap]','n9f_settings[footer_manager_icon_size]','n9f_settings[footer_manager_text_size]','n9f_settings[footer_manager_text_weight]','n9f_settings[footer_manager_name_weight]'];
 sliderNames.forEach(function(name){var input=root.querySelector('input[type="number"][name="'+name+'"]');if(!input||input.dataset.elhhSlider)return;input.dataset.elhhSlider='1';input.type='range';var wrap=document.createElement('span');wrap.className='elhh-range-wrap';var out=document.createElement('output');function sync(){out.value=input.value+(name.indexOf('weight')>=0?'':'px');out.textContent=out.value;}input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);wrap.appendChild(out);input.addEventListener('input',sync);sync();});
 setTab(root.getAttribute('data-active-surface')||'header');
});
 document.addEventListener('change',function(e){
   if(!e.target.matches('.elhh-master-switch input[type="checkbox"]'))return;
   var wrap=e.target.closest('.elhh-master-switch'),label=wrap&&wrap.querySelector('.elhh-switch b');
   if(label)label.textContent=e.target.checked?'ON':'OFF';
   if(wrap)wrap.setAttribute('data-enabled',e.target.checked?'1':'0');
 });
})();
