(function(){
'use strict';
function restoreNativeEditor(){
  ['ncu-editor-panel-fullscreen-open','ncu-editor-tools-open','ncu-editor-focus-panels','ncu-editor-hide-plugin-panels','ncu-editor-compact'].forEach(function(c){document.body.classList.remove(c);});
  document.querySelectorAll('.ncu-editor-focus-backdrop,.ncu-edit-panels-menu,.ncu-edit-panels-fab,.ncu-editor-panel-save,.ncu-editor-panel-status,.ncu-editor-tools-shell,.ncu-editor-tools-backdrop,.ncu-editor-tools-drawer,.ncu-command-palette').forEach(function(el){el.remove();});
  document.querySelectorAll('.ncu-editor-panel-hidden').forEach(function(el){el.classList.remove('ncu-editor-panel-hidden');if(el.hasAttribute('hidden'))el.removeAttribute('hidden');});
  document.querySelectorAll('.ncu-editor-panel-fullscreen').forEach(function(el){el.classList.remove('ncu-editor-panel-fullscreen');el.style.removeProperty('position');el.style.removeProperty('inset');el.style.removeProperty('z-index');el.style.removeProperty('width');el.style.removeProperty('height');el.style.removeProperty('overflow');el.style.removeProperty('visibility');el.style.removeProperty('opacity');});
  document.querySelectorAll('.ncu-editor-panel-fullscreen-ancestor').forEach(function(el){el.classList.remove('ncu-editor-panel-fullscreen-ancestor');el.style.removeProperty('transform');el.style.removeProperty('filter');el.style.removeProperty('perspective');el.style.removeProperty('contain');el.style.removeProperty('overflow');el.style.removeProperty('clip');el.style.removeProperty('clip-path');});
}
function parseValue(value){
  var text=String(value||'').trim();
  if(!text)return '';
  if((text[0]==='{'&&text[text.length-1]==='}')||(text[0]==='['&&text[text.length-1]===']')){try{return JSON.parse(text);}catch(e){}}
  return value;
}
function saveRecovery(){
  if(!window.NPM9NativeRecovery)return;
  var button=document.getElementById('npm9-native-recovery-save');
  var status=document.getElementById('npm9-native-recovery-status');
  var content=document.getElementById('npm9-native-recovery-content');
  if(!button||!content)return;
  var meta={};
  document.querySelectorAll('.npm9-native-recovery-meta-row[data-meta-key]').forEach(function(row){var key=row.getAttribute('data-meta-key'),field=row.querySelector('textarea');if(key&&field)meta[key]=parseValue(field.value);});
  button.disabled=true;if(status)status.textContent='Saving...';
  var body=new URLSearchParams();
  body.set('action','npm9_native_recovery_save');body.set('nonce',NPM9NativeRecovery.nonce);body.set('post_id',String(NPM9NativeRecovery.postId));body.set('content',content.value);body.set('meta',JSON.stringify(meta));
  fetch(NPM9NativeRecovery.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
    .then(function(r){return r.json();}).then(function(r){if(!r||!r.success)throw new Error(r&&r.data&&r.data.message?r.data.message:'Save failed.');if(status)status.textContent=r.data.message||'Saved.';})
    .catch(function(err){if(status)status.textContent=err.message||'Save failed.';})
    .finally(function(){button.disabled=false;});
}
document.addEventListener('DOMContentLoaded',function(){restoreNativeEditor();var save=document.getElementById('npm9-native-recovery-save');if(save)save.addEventListener('click',saveRecovery);var restore=document.getElementById('npm9-native-recovery-restore');if(restore)restore.addEventListener('click',restoreNativeEditor);setTimeout(restoreNativeEditor,300);setTimeout(restoreNativeEditor,1200);});
})();
