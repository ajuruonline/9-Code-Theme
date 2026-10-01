(function(){
'use strict';
if(window.__ELCompatBooted)return;window.__ELCompatBooted=true;
var doc=document,html=doc.documentElement,body=doc.body,lastExclusive='';
function event(owner,state,group){try{window.dispatchEvent(new CustomEvent('elcompat:surface-'+state,{detail:{owner:owner,group:group||'chrome',version:'1.0.0'}}));}catch(_e){}}
function px(n){return Math.max(0,Math.round(Number(n)||0))+'px';}
function publishInsets(){
 var h=doc.querySelector('.n9lh-shell'),header=0,bottom=0,b=doc.querySelector('.nbmn-bar');
 if(h){var r=h.getBoundingClientRect();if(r.height&&r.bottom>0)header=Math.max(0,r.bottom);}
 if(b){var br=b.getBoundingClientRect();if(br.height)bottom=Math.max(0,window.innerHeight-br.top);}
 html.style.setProperty('--el-header-offset',px(header));html.style.setProperty('--el-bottom-offset',px(bottom));
 html.style.setProperty('--el-safe-height','calc(100dvh - '+px(header)+' - '+px(bottom)+')');
}
function closeHeader(){doc.querySelectorAll('.n9lh-shell.is-open').forEach(function(s){var c=s.querySelector('[data-n9lh-close]'),t=s.querySelector('[data-n9lh-toggle]');if(c)c.click();else if(t)t.click();});doc.querySelectorAll('.n9lh-popup.is-open').forEach(function(p){var c=p.querySelector('[data-n9lh-popup-close]');if(c)c.click();});}
function closeNavigation(){doc.querySelectorAll('.nlns-root.is-open').forEach(function(r){try{r.dispatchEvent(new CustomEvent('nlns:close-drawer'));}catch(_e){var c=r.querySelector('[data-nlns-close],.nlns-close');if(c)c.click();}});}
function closeSide(){var r=doc.getElementById('nine-stpc');if(r&&r.classList.contains('is-open')){var c=r.querySelector('.nine-stpc__close');if(c)c.click();else r.classList.remove('is-open');}}
function closeMeeting(){doc.querySelectorAll('[data-gm-root],.gm-app,.gm-app-shell').forEach(function(r){r.querySelectorAll('[data-gm-close-drawer]').forEach(function(c){var panel=r.querySelector('[data-gm-drawer-panel].is-open');if(panel)c.click();});});}
window.ELCompat=window.ELCompat||{};window.ELCompat.version='1.0.0';window.ELCompat.announce=event;window.ELCompat.publishInsets=publishInsets;
window.addEventListener('elcompat:surface-open',function(e){var o=e.detail&&e.detail.owner||'';if(o!=='header'&&o!=='header-popup')closeHeader();if(o!=='navigation')closeNavigation();if(o!=='side-tab')closeSide();if(o!=='google-meeting')closeMeeting();lastExclusive=o;});
function watch(el,owner,pred){if(!el||!window.MutationObserver)return;var was=!!pred(el);new MutationObserver(function(){var now=!!pred(el);if(now&&!was)event(owner,'open','chrome');if(!now&&was)event(owner,'close','chrome');was=now;publishInsets();}).observe(el,{attributes:true,attributeFilter:['class','hidden','style']});}
function ready(){body=doc.body;publishInsets();
 doc.querySelectorAll('.n9lh-shell').forEach(function(x){watch(x,'header',function(e){return e.classList.contains('is-open');});});
 doc.querySelectorAll('.nlns-root').forEach(function(x){watch(x,'navigation',function(e){return e.classList.contains('is-open');});});
 var s=doc.getElementById('nine-stpc');if(s)watch(s,'side-tab',function(e){return e.classList.contains('is-open');});
 if(body)watch(body,'google-meeting',function(e){return e.classList.contains('gm-drawer-open')||e.classList.contains('gm-modal-open')||e.classList.contains('nrl-settings-modal-open');});
 window.addEventListener('resize',publishInsets,{passive:true});window.addEventListener('orientationchange',publishInsets,{passive:true});
 if(window.ResizeObserver){var ro=new ResizeObserver(publishInsets);doc.querySelectorAll('.n9lh-shell,.nbmn-bar').forEach(function(e){ro.observe(e);});}
 window.setTimeout(publishInsets,60);window.setTimeout(publishInsets,450);
}
if(doc.readyState==='loading')doc.addEventListener('DOMContentLoaded',ready,{once:true});else ready();
})();
