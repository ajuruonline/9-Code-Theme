(function($){
'use strict';
let postId = parseInt(window.NPM9CurrentPostId || 0,10) || 0;
let pendingBlocks = '';
let pendingElementor = '';
let state = null;

function ajax(action,data={}){return $.post(NPM9Design.ajaxUrl,Object.assign({action:'npm9_'+action,nonce:NPM9Design.nonce},data));}
function esc(v){return $('<div>').text(v==null?'':String(v)).html();}
function toast(msg,error){const $t=$('#npm9-toast');if(!$t.length)return; $t.text(msg).toggleClass('error',!!error).addClass('show');setTimeout(()=>$t.removeClass('show error'),3200);}
function download(name,mime,content){const blob=new Blob([content],{type:mime});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);}
function readFile(input,cb){const f=input.files&&input.files[0];if(!f)return;const reader=new FileReader();reader.onload=()=>cb(String(reader.result||''),f);reader.readAsText(f);input.value='';}

function switchTab(name){
  $('[data-npm9-design-tab]').removeClass('is-active').filter('[data-npm9-design-tab="'+name+'"]').addClass('is-active');
  $('[data-npm9-design-pane]').removeClass('is-active').prop('hidden',true).filter('[data-npm9-design-pane="'+name+'"]').addClass('is-active').prop('hidden',false);
}

function loadState(){
  if(!postId)return;
  ajax('design_state',{post_id:postId}).done(r=>{if(!r.success)return;state=r.data;renderState();});
}

function renderState(){
  const active=!!state?.active, engine=state?.engine||'', linked=!!state?.linked;
  const label=active?(state.name||'9PM design')+' · '+(linked?'Linked':'Detached')+(state?.manualChanged?' · Manual edits detected':''):'No 9PM design';
  $('#npm9-design-block-status').text(engine==='blocks'?label:'No Block design').toggleClass('is-on',engine==='blocks'&&active);
  $('#npm9-design-elementor-status').text(engine==='elementor'?label:'Experimental').toggleClass('is-on',engine==='elementor'&&active);
  $('#npm9-design-sync,#npm9-design-export,#npm9-design-detach,#npm9-design-clear').prop('disabled',!(active&&engine==='blocks'));
  $('#npm9-design-elementor-sync,#npm9-design-elementor-export,#npm9-design-elementor-detach').prop('disabled',!(active&&engine==='elementor'));
  if(state?.manualChanged){$('#npm9-design-manual-warning').html('<strong>Manual editor changes detected.</strong> Automatic ACF-driven rebuilding is paused to protect them. Detach the design to keep those manual changes, or use <strong>Rebuild from Current ACF</strong> if you intentionally want the linked .9pm recipe to replace them.').prop('hidden',false);}else{$('#npm9-design-manual-warning').prop('hidden',true).empty();}
  if(!state?.nineElementsActive){$('#npm9-design-nine-elements-warning').html('<strong>9 Elements is not active.</strong> Block Editor .9pm designs use the registered <code>nine/elements</code> block. Activate 9 Elements before applying a Block design.').prop('hidden',false);}else{$('#npm9-design-nine-elements-warning').prop('hidden',true).empty();}
  if(!state?.elementorActive){$('#npm9-design-elementor-warning').html('<strong>Elementor is not active.</strong> You can still use the Block Editor engine. The experimental Elementor importer will stay disabled until Elementor is active.').prop('hidden',false);$('#npm9-design-import-elementor').prop('disabled',true);}else{$('#npm9-design-elementor-warning').prop('hidden',true).empty();$('#npm9-design-import-elementor').prop('disabled',false);}
}

function mappingHtml(data,engine){
  const fields=data.availableFields||[];
  const req=data.requiredMissing||[], opt=data.optionalMissing||[];
  let html='<div class="npm9-design-preview-head"><strong>'+esc(data.name||'9PM design')+'</strong><span>'+esc(engine==='elementor'?'Elementor · Experimental':'Block Editor / 9 Elements')+'</span></div>';
  if(data.description)html+='<p>'+esc(data.description)+'</p>';
  if(req.length){
    html+='<div class="npm9-design-map"><strong>Link the fields 9PM could not find automatically</strong><p>9PM matched the required alias against 9CF/Site Fields and any legacy ACF bridge. Choose the correct post field for each remaining alias.</p>';
    req.forEach(alias=>{html+='<label><span>'+esc(alias)+'</span><select class="npm9-design-map-field" data-alias="'+esc(alias)+'"><option value="">— choose post field —</option>'+fields.map(f=>'<option value="'+esc(f.field_id||f.name)+'">'+esc(f.label)+' · '+esc(f.name)+' · '+esc(f.type)+'</option>').join('')+'</select></label>';});
    html+='</div>';
  }else{
    html+='<div class="npm9-design-good"><strong>Post field contract matched.</strong> '+Object.keys(data.resolved||{}).length+' field binding(s) resolved automatically.</div>';
  }
  if(opt.length)html+='<p class="description">Optional fields not found and will be skipped: '+opt.map(esc).join(', ')+'</p>';
  if(engine==='blocks'&&!data.nineElementsActive)html+='<div class="npm9-design-warning"><strong>Cannot apply yet:</strong> 9 Elements is not active.</div>';
  if(engine==='elementor'&&!data.elementorActive)html+='<div class="npm9-design-warning"><strong>Cannot apply yet:</strong> Elementor is not active.</div>';
  html+='<div class="npm9-design-apply-row"><button type="button" class="button button-primary npm9-design-apply" data-engine="'+esc(engine)+'" '+((engine==='blocks'&&!data.nineElementsActive)||(engine==='elementor'&&!data.elementorActive)?'disabled':'')+'>Apply to This Post</button><small>This changes this post only. A backup of the previous design/content state is stored first.</small></div>';
  return html;
}

function preview(content,engine){
  if(!postId){toast('Open a post/page in 9 Post Manager first.',true);return;}
  const $box=engine==='elementor'?$('#npm9-design-elementor-preview'):$('#npm9-design-block-preview');
  $box.html('<p>Reading design…</p>').prop('hidden',false);
  ajax('design_preview',{post_id:postId,content,engine}).done(r=>{
    if(!r.success){$box.html('<div class="npm9-design-warning">'+esc(r.data?.message||'Could not read this design file.')+'</div>');return;}
    $box.html(mappingHtml(r.data,engine));
  }).fail(()=>$box.html('<div class="npm9-design-warning">Preview failed.</div>'));
}

function collectMappings($box){const m={};$box.find('.npm9-design-map-field').each(function(){const v=$(this).val();if(v)m[$(this).data('alias')]=v;});return m;}

function applyPending(engine,$button){
  const content=engine==='elementor'?pendingElementor:pendingBlocks;
  const $box=engine==='elementor'?$('#npm9-design-elementor-preview'):$('#npm9-design-block-preview');
  const mappings=collectMappings($box);
  if(!content)return;
  const missing=$box.find('.npm9-design-map-field').filter(function(){return !$(this).val();}).length;
  if(missing){toast('Map every required post field before applying this design.',true);return;}
  if(!confirm(engine==='elementor'?'Apply this experimental Elementor design to this post only? Existing Elementor data for this post will be replaced.':'Compile this .9pm design into Gutenberg / 9 Elements blocks for this post? Existing post content may be replaced according to the design package placement setting.'))return;
  const original=$button.text();$button.prop('disabled',true).text('Applying…');
  ajax('design_apply',{post_id:postId,content,engine,mappings:JSON.stringify(mappings)}).done(r=>{
    if(!r.success){toast(r.data?.message||'Design could not be applied.',true);return;}
    state=r.data.state;renderState();const editLink=engine==='elementor'?(state?.elementorEditUrl||''):(state?.gutenbergEditUrl||'');const editLabel=engine==='elementor'?'Open Elementor':'Open Gutenberg Editor';$box.html('<div class="npm9-design-good"><strong>Applied.</strong> The design is linked to the current post fields. <a target="_blank" rel="noopener" href="'+esc(r.data.viewUrl||'#')+'">View front end</a>'+(editLink?' · <a href="'+esc(editLink)+'">'+editLabel+'</a>':'')+'</div>');toast('9PM design applied.');
  }).always(()=>$button.prop('disabled',false).text(original));
}

$(document).on('npm9:post-loaded',function(e,id){postId=parseInt(id||0,10)||0;pendingBlocks='';pendingElementor='';$('#npm9-design-block-preview,#npm9-design-elementor-preview').prop('hidden',true).empty();loadState();});
$(document).on('click','[data-npm9-design-tab]',function(){switchTab($(this).data('npm9-design-tab'));});

$('#npm9-design-import-blocks').on('click',()=>$('#npm9-design-blocks-file').trigger('click'));
$('#npm9-design-blocks-file').on('change',function(){readFile(this,(content)=>{pendingBlocks=content;preview(content,'blocks');});});
$('#npm9-design-import-elementor').on('click',()=>$('#npm9-design-elementor-file').trigger('click'));
$('#npm9-design-elementor-file').on('change',function(){readFile(this,(content)=>{pendingElementor=content;preview(content,'elementor');});});
$(document).on('click','.npm9-design-apply',function(){applyPending($(this).data('engine'),$(this));});

function syncDesign(engine){if(!postId)return;if(!confirm(engine==='elementor'?'Re-apply the linked Elementor recipe using the current post-field values? Manual Elementor changes made after the last sync can be overwritten.':'Rebuild the generated Gutenberg / 9 Elements blocks from the current post fields?'))return;const $b=engine==='elementor'?$('#npm9-design-elementor-sync'):$('#npm9-design-sync');const old=$b.text();$b.prop('disabled',true).text('Rebuilding…');ajax('design_sync',{post_id:postId}).done(r=>{if(!r.success){toast(r.data?.message||'Rebuild failed.',true);return;}state=r.data.state;renderState();toast('Design rebuilt from current post fields.');}).always(()=>$b.prop('disabled',false).text(old));}
$('#npm9-design-sync').on('click',()=>syncDesign('blocks'));
$('#npm9-design-elementor-sync').on('click',()=>syncDesign('elementor'));

$('#npm9-design-export').on('click',function(){if(!postId)return;const $b=$(this).prop('disabled',true);ajax('design_export',{post_id:postId}).done(r=>{if(r.success)download(r.data.filename,r.data.mime,r.data.content);else toast(r.data?.message||'No design to export.',true);}).always(()=>$b.prop('disabled',false));});
$('#npm9-design-elementor-export').on('click',function(){if(!postId)return;const $b=$(this).prop('disabled',true);ajax('design_elementor_export',{post_id:postId}).done(r=>{if(r.success)download(r.data.filename,r.data.mime,r.data.content);else toast(r.data?.message||'Elementor export failed.',true);}).always(()=>$b.prop('disabled',false));});

function detach(){if(!postId||!confirm('Detach the 9PM design? Current generated content stays exactly where it is, but Post Manager field changes will no longer rebuild it automatically.'))return;ajax('design_detach',{post_id:postId}).done(r=>{if(r.success){state=r.data.state;renderState();toast(r.data.message||'Design detached.');}else toast(r.data?.message||'Could not detach.',true);});}
$('#npm9-design-detach,#npm9-design-elementor-detach').on('click',detach);
$('#npm9-design-clear').on('click',function(){if(!postId||!confirm('Remove the 9PM design link from this post? Existing Gutenberg/Elementor content will NOT be deleted.'))return;ajax('design_clear',{post_id:postId}).done(r=>{if(r.success){state=r.data.state;renderState();toast(r.data.message||'Design link removed.');}else toast(r.data?.message||'Could not clear design link.',true);});});

$('#npm9-design-acf-brief').on('click',function(){if(!postId){toast('Open a post first.',true);return;}const $b=$(this).prop('disabled',true);ajax('design_acf_brief',{post_id:postId}).done(r=>{if(r.success){download(r.data.filename,r.data.mime,r.data.content);$('#npm9-design-ai-result').html('<div class="npm9-design-good"><strong>Field design brief downloaded.</strong> Upload it to your AI together with the Block Editor or Elementor Markdown guide.</div>').prop('hidden',false);}else toast(r.data?.message||'Could not build ACF brief.',true);}).always(()=>$b.prop('disabled',false));});

// If another script loaded the post before this file completed, recover the current ID.
if(window.NPM9CurrentPostId){postId=parseInt(window.NPM9CurrentPostId,10)||0;if(postId)loadState();}
})(jQuery);
