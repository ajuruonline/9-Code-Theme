(function($){
'use strict';
const $overlay=$('#npm9-front-overlay');
const $toggle=$('.npm9-front-toggle');
const $frame=$overlay.find('.npm9-front-frame');
const $state=$overlay.find('.npm9-front-saved-state');
let saved=false;
let opener=null;
let dockTimer=0;

function adjustAdminDock(){
  window.clearTimeout(dockTimer);
  dockTimer=window.setTimeout(function(){
    let lift=0;
    const vv=window.visualViewport;
    const h=(vv&&vv.height)||window.innerHeight||0;
    const w=(vv&&vv.width)||window.innerWidth||0;
    const cx=w/2;
    document.querySelectorAll('.nan-wrap,.nine-action-menu,.nan-launchers').forEach(function(wrap){
      if(!wrap||!wrap.getBoundingClientRect)return;
      const style=window.getComputedStyle(wrap);
      if(style.display==='none'||style.visibility==='hidden')return;
      const el=wrap.querySelector?.('.nan-launchers')||wrap;
      const r=el.getBoundingClientRect();
      if(!r.width||!r.height)return;
      const horizontallyNearCenter=r.left<cx+100&&r.right>cx-100;
      const nearBottom=r.bottom>h-210&&r.top<h;
      if(horizontallyNearCenter&&nearBottom) lift=Math.max(lift,Math.ceil(h-r.top+10));
    });
    const safeLift=Math.max(0,Math.min(lift,220));
    document.documentElement.style.setProperty('--npm9-sister-lift',safeLift+'px');
    document.querySelectorAll('.ninecm-fab.ninecm-has-sister').forEach(function(el){const desired='calc(10px + env(safe-area-inset-bottom,0px) + '+safeLift+'px)';if(el.style.getPropertyValue('bottom')!==desired||el.style.getPropertyPriority('bottom')!=='important'){el.style.setProperty('bottom',desired,'important');}});
  },40);
}

function openWorkspace(){
  opener=document.activeElement;
  if(!$frame.attr('src')) $frame.attr('src',$frame.data('src')||NPM9Front.workspaceUrl||'');
  document.dispatchEvent(new CustomEvent('nine:sister-open',{detail:{source:'post'}}));
  $overlay.addClass('is-open').attr('aria-hidden','false');
  if($toggle.length)$toggle.attr('aria-expanded','true');
  $('html,body').addClass('npm9-front-lock');
  requestAnimationFrame(()=>{$overlay.find('.npm9-front-close').trigger('focus')});
}
function closeWorkspace(){
  $overlay.removeClass('is-open').attr('aria-hidden','true');
  if($toggle.length)$toggle.attr('aria-expanded','false');
  $('html,body').removeClass('npm9-front-lock');
  if(opener&&opener.focus) opener.focus(); else if($toggle.length)$toggle.trigger('focus');
  if(saved){
    $state.text('Saved. Refreshing the page…');
    window.location.reload();
  }
}
adjustAdminDock();
window.addEventListener('resize',adjustAdminDock,{passive:true});
window.addEventListener('scroll',adjustAdminDock,{passive:true});
if(window.visualViewport){window.visualViewport.addEventListener('resize',adjustAdminDock,{passive:true});window.visualViewport.addEventListener('scroll',adjustAdminDock,{passive:true});}
if(window.MutationObserver){new MutationObserver(adjustAdminDock).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['class','style','hidden']});}

document.addEventListener('ninecode:data-post-editor',function(e){if(e&&e.detail&&e.detail.source)opener=e.detail.source;openWorkspace();});
document.addEventListener('ninecode:front-save',function(e){
  if(!$overlay.hasClass('is-open'))return;
  if(e&&e.preventDefault)e.preventDefault();
  const frame=$frame.get(0);if(!frame||!frame.contentWindow)return;
  try{const save=frame.contentWindow.document.getElementById('npm9-save');if(save){save.click();$state.text('Saving…');}}catch(_err){}
});
$toggle.on('click',openWorkspace);
$overlay.on('click','.npm9-front-close',closeWorkspace);
$(document).on('keydown',function(e){if(e.key==='Escape'&&$overlay.hasClass('is-open'))closeWorkspace()});
document.addEventListener('nine:sister-open',function(e){if(e&&e.detail&&e.detail.source!=='post'&&$overlay.hasClass('is-open'))closeWorkspace()});
window.addEventListener('message',function(e){
  if(e.origin!==window.location.origin||e.source!==$frame.get(0)?.contentWindow)return;
  const d=e.data||{};
  if(d.type==='npm9:saved'){
    saved=true;
    $state.text('Saved. You can close the workspace safely.');
  }
  if(d.type==='npm9:dirty') $state.text(d.dirty?'Unsaved changes — press Update before closing.':'No unsaved changes.');
});
})(jQuery);
