(function($){'use strict';
var form=document.getElementById('n9f-settings-form'),dirty=false,submitting=false;
function setDirty(v){dirty=!!v;document.querySelectorAll('.n9f-save-top,.n9f-mobile-save').forEach(function(el){el.classList.toggle('is-dirty',dirty);});}

function updateSourceFields(scope){
  (scope||document).querySelectorAll('.n9f-subtab').forEach(function(tab){
    var sel=tab.querySelector('select[name*="_source]"]');
    if(!sel)return;
    tab.querySelectorAll('[data-n9f-source-only]').forEach(function(group){group.hidden=group.getAttribute('data-n9f-source-only')!==sel.value;});
  });
}
function updateColourFields(){
  var footer=document.querySelector('select[name="n9f_settings[footer_color_mode]"]');
  document.querySelectorAll('[data-n9f-color-mode]').forEach(function(group){group.hidden=!footer||group.getAttribute('data-n9f-color-mode')!==footer.value;});
  var manager=document.querySelector('select[name="n9f_settings[footer_manager_color_mode]"]');
  document.querySelectorAll('[data-n9f-manager-color-mode]').forEach(function(group){group.hidden=!manager||group.getAttribute('data-n9f-manager-color-mode')!==manager.value;});
  var auto=document.querySelector('input[name="n9f_settings[footer_auto_contrast]"]');
  document.querySelectorAll('[data-n9f-manual-footer-colors]').forEach(function(group){group.hidden=!!auto&&auto.checked;});
  var managerAuto=document.querySelector('input[name="n9f_settings[footer_manager_auto_contrast]"]');
  document.querySelectorAll('[data-n9f-manual-manager-color]').forEach(function(group){group.hidden=!!managerAuto&&managerAuto.checked;});
}
function remember(id){try{sessionStorage.setItem('n9fActiveTab',id);}catch(e){}}function recall(){try{return sessionStorage.getItem('n9fActiveTab');}catch(e){return null;}}function openTab(id,scroll){var el=document.getElementById('n9f-tab-'+id);if(!el)return;el.open=true;remember(id);document.querySelectorAll('[data-n9f-jump]').forEach(function(b){b.classList.toggle('is-active',b.getAttribute('data-n9f-jump')===id);});if(scroll)el.scrollIntoView({behavior:'smooth',block:'start'});}
$(document).on('click','.n9f-media-button',function(e){e.preventDefault();var target=$('#'+$(this).data('target'));var frame=wp.media({title:'Choose media',button:{text:'Use this media'},multiple:false});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();target.val(a.url).trigger('change');});frame.open();});
$(document).on('click','.n9f-media-clear',function(e){e.preventDefault();$('#'+$(this).data('target')).val('').trigger('change');});
$(document).on('click','[data-n9f-jump]',function(){openTab($(this).data('n9f-jump'),true);});
if(!document.querySelector('[data-elhh-admin]')&&window.matchMedia&&window.matchMedia('(max-width:782px)').matches){document.addEventListener('toggle',function(e){if(!e.target.matches('.n9f-admin-tab')||!e.target.open)return;document.querySelectorAll('.n9f-admin-tab[open]').forEach(function(other){if(other!==e.target)other.open=false;});},true);}
document.addEventListener('toggle',function(e){if(!e.target.matches('.n9f-admin-tab')||!e.target.open)return;var id=(e.target.id||'').replace('n9f-tab-','');if(id)openTab(id,false);},true);
if(form){form.addEventListener('change',function(e){setDirty(true);if(e.target.matches('select[name*="_source]"]'))updateSourceFields(e.target.closest('.n9f-subtab')||document);if(e.target.matches('select[name="n9f_settings[footer_color_mode]"],select[name="n9f_settings[footer_manager_color_mode]"],input[name="n9f_settings[footer_auto_contrast]"],input[name="n9f_settings[footer_manager_auto_contrast]"]'))updateColourFields();});form.addEventListener('input',function(){setDirty(true);});form.addEventListener('submit',function(){submitting=true;setDirty(false);});}
window.addEventListener('beforeunload',function(e){if(!dirty||submitting)return;e.preventDefault();e.returnValue='';});
var remembered=recall();if(remembered)openTab(remembered,false);else{var first=document.querySelector('.n9f-admin-tab[open]');if(first)openTab(first.id.replace('n9f-tab-',''),false);}
var search=document.getElementById('n9f-settings-search'),clear=document.querySelector('[data-n9f-search-clear]');
function applySearch(){if(!search)return;var q=search.value.trim().toLowerCase(),visibleTabs=0;document.querySelectorAll('.n9f-admin-tab').forEach(function(tab){var fields=tab.querySelectorAll('.n9f-field,.n9f-subtab,.n9f-mini-card,.n9f-callout'),matches=!q||(tab.querySelector('summary')&&tab.querySelector('summary').textContent.toLowerCase().indexOf(q)!==-1),fieldMatches=0;fields.forEach(function(field){var hit=!q||field.textContent.toLowerCase().indexOf(q)!==-1;field.classList.toggle('n9f-search-hidden',!hit);if(hit)fieldMatches++;});var show=!q||matches||fieldMatches>0;tab.classList.toggle('n9f-search-hidden',!show);if(show){visibleTabs++;if(q)tab.open=true;}});var old=document.querySelector('.n9f-search-empty');if(!visibleTabs&&q){if(!old){old=document.createElement('div');old.className='n9f-search-empty';old.textContent='No footer setting matches your search.';search.parentNode.insertAdjacentElement('afterend',old);}}else if(old)old.remove();}
if(search){search.addEventListener('input',applySearch);}if(clear){clear.addEventListener('click',function(){search.value='';applySearch();search.focus();});}
updateSourceFields(document);updateColourFields();
})(jQuery);
