(function(){
  'use strict';
  var C=window.NineCMAdmin||{},tax=C.taxonomy||'category',postType=C.postType||'page',termPage=1,postPage=1; C.taxMeta=C.taxMeta||{};C.postTypeMeta=C.postTypeMeta||{};
  var selectedTerms=new Map(),termSeq=0,postSeq=0,relSeq=0,parentSeq=0;

  function api(path,opt){
    opt=opt||{};
    opt.headers=Object.assign({'Content-Type':'application/json','X-WP-Nonce':C.nonce||''},opt.headers||{});
    return fetch(C.root+path,opt).then(function(r){
      var total=r.headers.get('X-WP-Total'),pages=r.headers.get('X-WP-TotalPages');
      return r.text().then(function(text){
        var j={};try{j=text?JSON.parse(text):{};}catch(e){throw new Error('The server returned an invalid response. Check the WordPress/PHP error log.');}
        if(!r.ok){var err=new Error(j.message||'Request failed');err.code=j.code||'';err.status=r.status;err.data=j.data||{};throw err;}
        return {data:j,total:total,pages:pages};
      });
    });
  }
  function esc(s){return String(s==null?'':s).replace(/[&<>'"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];});}
  function message(text){window.alert(text);}
  function cap(kind){return !!(C.taxCaps&&C.taxCaps[tax]&&C.taxCaps[tax][kind]);}
  function currentTax(){return C.taxMeta&&C.taxMeta[tax]||{};}function currentPostType(){return C.postTypeMeta&&C.postTypeMeta[postType]||{};}function taxonomyCompatible(){return !!((currentPostType().taxonomies||[]).some(function(t){return t.name===tax;}));}

  function syncCapabilities(){
    var n=document.querySelector('[data-ninecm-new-term]');if(n)n.hidden=!cap('manage');
    var pd=currentPostType();
    var apply=document.querySelector('[data-ninecm-apply]');if(apply){var compatible=taxonomyCompatible();apply.disabled=!cap('assign')||!postType||!compatible;apply.title=!compatible?'The selected taxonomy is not attached to this content type. Use Edit structure for its other allocation systems.':(cap('assign')?'':'You do not have permission to assign this taxonomy.');}var bs=document.querySelector('[data-ninecm-bulk-shell-run]');if(bs){bs.disabled=!postType||!taxonomyCompatible()||!cap('assign')||!pd.canCreate;bs.title=!pd.canCreate?'This content type is not eligible for generic planning-shell creation.':(taxonomyCompatible()?'':'Choose a taxonomy attached to this content type before bulk shell allocation.');}var createBtn=document.querySelector('[data-ninecm-create-shell]');if(createBtn){createBtn.disabled=!postType||!pd.canCreate;createBtn.title=pd.canCreate?'Create a blank planning shell':'This content type is editable for structure but not eligible for generic blank-shell creation.';}
    var hierarchical=!!currentTax().hierarchical;
    var bulk=document.querySelector('[data-ninecm-bulk-run]');if(bulk){bulk.disabled=!cap('manage');bulk.title=hierarchical?'Build nested hierarchy in safe batches.':'Bulk-create flat tags/terms, one per line.';bulk.textContent=hierarchical?'Create hierarchy':'Create terms';}var bulkHelp=document.querySelector('[data-ninecm-bulk-help]');if(bulkHelp)bulkHelp.textContent=hierarchical?'Use > indentation/levels to create hierarchy. Large plans run in safe resumable batches.':'Flat taxonomy mode: enter one tag/term per line. Hierarchy markers are ignored.';
    var pf=document.querySelector('[data-ninecm-parent-filter]');if(pf)pf.hidden=!hierarchical;
    var authorBox=document.querySelector('[data-ninecm-bulk-author-box]'),parentBox=document.querySelector('[data-ninecm-bulk-parent-box]'),orderBox=document.querySelector('[data-ninecm-bulk-order-box]');
    if(authorBox)authorBox.hidden=!postType||!(pd.supports&&pd.supports.author);
    if(parentBox)parentBox.hidden=!postType||!pd.supportsParent;
    if(orderBox)orderBox.hidden=!postType||!pd.supportsOrder;
  }

  function updateExportLinks(){
    document.querySelectorAll('[data-ninecm-export-categories],[data-ninecm-export-json],[data-ninecm-export-blueprint],[data-ninecm-export-planning]').forEach(function(a){
      try{
        var u=new URL(a.href,window.location.href);u.searchParams.set('taxonomy',tax);
        if((a.hasAttribute('data-ninecm-export-planning')||a.hasAttribute('data-ninecm-export-blueprint'))&&postType)u.searchParams.set('post_type',postType);
        a.href=u.toString();
        if(a.hasAttribute('data-ninecm-export-planning')||a.hasAttribute('data-ninecm-export-blueprint')){a.style.pointerEvents=postType?'':'none';a.setAttribute('aria-disabled',postType?'false':'true');}
      }catch(e){}
    });
  }

  document.querySelectorAll('[data-tab]').forEach(function(b){
    b.onclick=function(){
      document.querySelectorAll('[data-tab]').forEach(function(x){x.classList.remove('nav-tab-active');});
      b.classList.add('nav-tab-active');
      document.querySelectorAll('[data-panel]').forEach(function(p){p.hidden=p.dataset.panel!==b.dataset.tab;});
      try{localStorage.setItem('ninecm_admin_tab',b.dataset.tab);}catch(e){}
    };
  });
  try{var savedTab=localStorage.getItem('ninecm_admin_tab');var savedTabButton=savedTab&&document.querySelector('[data-tab="'+savedTab+'"]');if(savedTabButton)savedTabButton.click();}catch(e){}

  var taxSel=document.querySelector('[data-ninecm-taxonomy]');
  function loadPostTypes(){
    var sel=document.querySelector('[data-ninecm-post-type]');if(!sel)return Promise.resolve();
    sel.disabled=true;
    return api('post-types').then(function(r){
      var available=r.data||[];C.postTypeMeta={};available.forEach(function(p){C.postTypeMeta[p.name]=p;});
      var wanted=postType;try{var savedPost=localStorage.getItem('ninecm_admin_posttype');if(savedPost)wanted=savedPost;}catch(e){}
      if(!available.some(function(p){return p.name===wanted;}))wanted=available.length?available[0].name:'';
      postType=wanted;
      sel.innerHTML=available.length?available.map(function(p){return '<option value="'+esc(p.name)+'"'+(p.name===postType?' selected':'')+'>'+esc(p.label)+'</option>';}).join(''):'<option value="">No compatible content types</option>';
      sel.disabled=!available.length;
      postPage=1;syncCapabilities();updateExportLinks();loadPosts();
    }).catch(function(e){postType='';sel.innerHTML='<option value="">Unavailable</option>';syncCapabilities();message(e.message);});
  }
  if(taxSel){
    api('taxonomies').then(function(r){
      (r.data||[]).forEach(function(t){C.taxCaps=C.taxCaps||{};C.taxCaps[t.name]={manage:!!t.canManage,edit:!!t.canEdit,delete:!!t.canDelete,assign:!!t.canAssign};C.taxMeta[t.name]=t;});
      try{var savedTax=localStorage.getItem('ninecm_admin_taxonomy');if(savedTax&&(r.data||[]).some(function(t){return t.name===savedTax;}))tax=savedTax;}catch(e){}
      taxSel.innerHTML=(r.data||[]).map(function(t){return '<option value="'+esc(t.name)+'"'+(t.name===tax?' selected':'')+'>'+esc(t.label)+'</option>';}).join('');
      if(!taxSel.value&&r.data.length){tax=r.data[0].name;taxSel.value=tax;}
      syncCapabilities();updateExportLinks();loadTerms();searchRelationshipTerms();loadPostTypes();
    }).catch(function(e){message(e.message);});
    taxSel.onchange=function(){tax=this.value;try{localStorage.setItem('ninecm_admin_taxonomy',tax);}catch(e){};termPage=1;postPage=1;selectedTerms.clear();renderSelectedTerms();syncCapabilities();updateExportLinks();loadTerms();searchRelationshipTerms();loadPostTypes().then(refreshUndo);};
  }

  function pager(el,p,total,go){
    if(!el)return;el.innerHTML='';total=parseInt(total||1,10);if(total<=1)return;
    var prev=document.createElement('button');prev.className='button';prev.type='button';prev.textContent='‹';prev.disabled=p<=1;prev.onclick=function(){go(p-1);};
    var next=document.createElement('button');next.className='button';next.type='button';next.textContent='›';next.disabled=p>=total;next.onclick=function(){go(p+1);};
    var txt=document.createElement('span');txt.textContent='Page '+p+' of '+total;
    el.append(prev,txt,next);
  }

  function loadTerms(){
    var searchEl=document.querySelector('[data-ninecm-term-search]');if(!searchEl)return;
    var seq=++termSeq,parentEl=document.querySelector('[data-ninecm-parent-filter]'),visEl=document.querySelector('[data-ninecm-term-visibility]');
    var s=encodeURIComponent(searchEl.value||''),parent=parentEl?parentEl.value:'',vis=visEl?visEl.value:'all';
    api('terms?taxonomy='+encodeURIComponent(tax)+'&page='+termPage+'&per_page='+encodeURIComponent(C.perPage||50)+'&search='+s+(parent!==''?'&parent='+encodeURIComponent(parent):'')+(vis==='used'?'&hide_empty=1':'')+(vis==='active'?'&archived=active':(vis==='archived'?'&archived=archived':''))).then(function(r){
      if(seq!==termSeq)return;
      var tb=document.querySelector('[data-ninecm-term-rows]');if(!tb)return;
      tb.innerHTML=r.data.map(function(t){
        var actions=[];
        if(cap('edit'))actions.push('<button type="button" class="button button-small" data-edit="'+t.id+'" data-name="'+esc(t.name)+'" data-slug="'+esc(t.slug)+'" data-parent="'+t.parent+'" data-desc="'+esc(t.description)+'" data-order="'+(parseInt(t.order||0,10))+'" data-protected="'+(t.protected?'1':'0')+'" data-archived="'+(t.archivedRoot?'1':'0')+'">Edit</button>');
        if(cap('delete'))actions.push('<button type="button" class="button button-small" data-del="'+t.id+'" data-count="'+t.count+'">Delete</button>');
        return '<tr><td data-label="Name / hierarchy"><div class="ninecm-path">'+(t.protected?'🔒 ':'')+(t.archived?'⏸ ':'')+esc(t.path)+'</div><div class="ninecm-subtle">ID '+t.id+' · parent '+t.parent+'</div></td><td data-label="Slug">'+esc(t.slug)+'</td><td data-label="Published">'+t.count+'</td><td data-label="Order">'+(parseInt(t.order||0,10))+'</td><td data-label="State">'+(t.archived?(t.archivedRoot?'<strong>Archived branch</strong>':'Archived by parent'):(t.protected?'<strong>Protected</strong>':'Active'))+'</td><td data-label="Actions">'+(actions.join(' ')||'—')+'</td></tr>';
      }).join('')||'<tr><td colspan="6">No terms found.</td></tr>';
      pager(document.querySelector('[data-ninecm-term-pager]'),termPage,r.pages,function(p){termPage=p;loadTerms();});
    }).catch(function(e){if(seq===termSeq)message(e.message);});
  }
  var st,termSearch=document.querySelector('[data-ninecm-term-search]');if(termSearch)termSearch.oninput=function(){clearTimeout(st);st=setTimeout(function(){termPage=1;loadTerms();},250);};
  var pf=document.querySelector('[data-ninecm-parent-filter]');if(pf)pf.onchange=function(){termPage=1;loadTerms();};
  var tv=document.querySelector('[data-ninecm-term-visibility]');if(tv)tv.onchange=function(){termPage=1;loadTerms();};

  var dlg=document.querySelector('[data-ninecm-dialog]'),dc=document.querySelector('[data-ninecm-dialog-content]');
  function openTermForm(t){
    if(!dlg||!dc||(t?!cap('edit'):!cap('manage')))return;
    dc.innerHTML='<h2>'+(t?'Edit category':'New category')+'</h2><div class="ninecm-form-grid"><label>Name<input data-f-name value="'+esc(t&&t.name||'')+'"></label><label>Slug<input data-f-slug value="'+esc(t&&t.slug||'')+'"></label><div><label>Parent category<input type="search" data-f-parent-search placeholder="Search parent category"></label><input type="hidden" data-f-parent value="'+esc(t&&t.parent||0)+'"><div class="ninecm-parent-current" data-f-parent-current>'+(t&&parseInt(t.parent||0,10)?'Current parent ID: '+esc(t.parent):'Top level')+'</div><div class="ninecm-term-results" data-f-parent-results></div><button type="button" class="button" data-f-parent-top>Set top level</button></div><label>Display order<input type="number" data-f-order value="'+esc(t&&t.order||0)+'"><span class="description">Used when a Post of Contents is set to 9 Manual order.</span></label><label>Description<textarea data-f-desc rows="4">'+esc(t&&t.description||'')+'</textarea></label><label class="ninecm-protect-check"><input type="checkbox" data-f-protected '+(t&&t.protected?'checked':'')+' '+(cap('manage')?'':'disabled')+'> Protect this category from accidental move/delete</label><label class="ninecm-protect-check"><input type="checkbox" data-f-archived '+(t&&t.archived?'checked':'')+' '+(cap('manage')?'':'disabled')+'> Archive this category from public Post of Contents (relationships are preserved)</label><button type="button" class="button button-primary" data-f-save>Save category</button></div>';
    dlg.hidden=false;var first=dc.querySelector('[data-f-name]');if(first)first.focus();
    var parentInput=dc.querySelector('[data-f-parent-search]'),parentResults=dc.querySelector('[data-f-parent-results]'),parentId=dc.querySelector('[data-f-parent]'),parentCurrent=dc.querySelector('[data-f-parent-current]'),ptimer;
    function parentSearch(){var seq=++parentSeq,q=encodeURIComponent(parentInput.value||'');parentResults.innerHTML='<span class="ninecm-subtle">Searching…</span>';api('terms?taxonomy='+encodeURIComponent(tax)+'&per_page=30&archived=active&search='+q).then(function(r){if(seq!==parentSeq)return;parentResults.innerHTML=r.data.filter(function(x){return !t||parseInt(x.id,10)!==parseInt(t.id,10);}).map(function(x){return '<button type="button" class="ninecm-term-pick" data-parent-pick data-id="'+x.id+'" data-path="'+esc(x.path)+'"><span>'+esc(x.path)+'</span><small>#'+x.id+'</small></button>';}).join('')||'<span class="ninecm-subtle">No matching terms.</span>';}).catch(function(e){if(seq===parentSeq)parentResults.textContent=e.message;});}
    parentInput.oninput=function(){clearTimeout(ptimer);ptimer=setTimeout(parentSearch,250);};
    parentResults.onclick=function(e){var b=e.target.closest('[data-parent-pick]');if(!b)return;parentId.value=b.dataset.id;parentCurrent.textContent='Parent: '+b.dataset.path+' (#'+b.dataset.id+')';parentResults.innerHTML='';};
    dc.querySelector('[data-f-parent-top]').onclick=function(){parentId.value='0';parentCurrent.textContent='Top level';parentResults.innerHTML='';parentInput.value='';};
    dc.querySelector('[data-f-save]').onclick=function(){
      var body={taxonomy:tax,name:dc.querySelector('[data-f-name]').value,slug:dc.querySelector('[data-f-slug]').value,parent:parseInt(parentId.value||0,10),description:dc.querySelector('[data-f-desc]').value,order:parseInt(dc.querySelector('[data-f-order]').value||0,10)};var protect=dc.querySelector('[data-f-protected]');if(protect&&!protect.disabled)body.protected=!!protect.checked;var archived=dc.querySelector('[data-f-archived]');if(archived&&!archived.disabled)body.archived=!!archived.checked;
      var btn=this;btn.disabled=true;
      api('terms'+(t?'/'+t.id:''),{method:t?'PUT':'POST',body:JSON.stringify(body)}).then(function(){dlg.hidden=true;loadTerms();searchRelationshipTerms();}).catch(function(e){message(e.message);}).finally(function(){btn.disabled=false;});
    };
  }
  var nt=document.querySelector('[data-ninecm-new-term]');if(nt)nt.onclick=function(){openTermForm(null);};
  var dclose=document.querySelector('[data-ninecm-dialog-close]');if(dclose)dclose.onclick=function(){dlg.hidden=true;};

  function deleteTerm(id,force){
    api('terms/'+id+'?taxonomy='+encodeURIComponent(tax)+(force?'&force=1':''),{method:'DELETE'}).then(function(){loadTerms();searchRelationshipTerms();}).catch(function(err){
      if(!force&&err.code==='ninecm_delete_confirmation'){
        if(window.confirm(err.message+'\n\nThis cannot be undone. Continue?'))deleteTerm(id,true);
      }else message(err.message);
    });
  }
  document.addEventListener('click',function(e){
    var ed=e.target.closest('[data-edit]');if(ed)openTermForm({id:ed.dataset.edit,name:ed.dataset.name,slug:ed.dataset.slug,parent:ed.dataset.parent,description:ed.dataset.desc,order:parseInt(ed.dataset.order||0,10),protected:ed.dataset.protected==='1',archived:ed.dataset.archived==='1'});
    var del=e.target.closest('[data-del]');if(del&&cap('delete')&&window.confirm('Delete this term? If it has assigned content or child terms you will receive one final warning.'))deleteTerm(del.dataset.del,false);
    var add=e.target.closest('[data-ninecm-pick-term]');if(add){selectedTerms.set(parseInt(add.dataset.id,10),{id:parseInt(add.dataset.id,10),path:add.dataset.path||add.dataset.name||('Category '+add.dataset.id)});renderSelectedTerms();}
    var remove=e.target.closest('[data-ninecm-remove-term]');if(remove){selectedTerms.delete(parseInt(remove.dataset.id,10));renderSelectedTerms();}
  });

  var postTypeSel=document.querySelector('[data-ninecm-post-type]');
  if(postTypeSel)postTypeSel.onchange=function(){postType=this.value;try{localStorage.setItem('ninecm_admin_posttype',postType);}catch(e){};postPage=1;syncCapabilities();updateExportLinks();loadPosts();refreshUndo();refreshStructureUndo();};

  function loadPosts(){
    var sEl=document.querySelector('[data-ninecm-post-search]'),tb=document.querySelector('[data-ninecm-post-rows]');if(!sEl||!tb)return;
    var selAll=document.querySelector('[data-ninecm-select-all]');if(selAll)selAll.checked=false;
    if(!postType){tb.innerHTML='<tr><td colspan="4">No editable content type is available for this account.</td></tr>';pager(document.querySelector('[data-ninecm-post-pager]'),1,1,function(){});return;}
    var seq=++postSeq,s=encodeURIComponent(sEl.value||''),statusEl=document.querySelector('[data-ninecm-post-status]'),scopeEl=document.querySelector('[data-ninecm-post-rel-scope]'),status=statusEl?statusEl.value:'',scope=scopeEl?scopeEl.value:'all';
    api('posts?taxonomy='+encodeURIComponent(tax)+'&post_type='+encodeURIComponent(postType)+'&page='+postPage+'&per_page=30&search='+s+'&status='+encodeURIComponent(status)+'&relationship_scope='+encodeURIComponent(scope)).then(function(r){
      if(seq!==postSeq)return;
      tb.innerHTML=r.data.map(function(p){
        var rel=!p.taxonomyCompatible?'<span class="ninecm-subtle">Selected taxonomy not attached · use Edit structure</span>':((p.terms||[]).map(function(t){return '<span class="ninecm-rel">'+esc(t.path)+'</span>';}).join('')||'<span class="ninecm-subtle">No relationship in selected taxonomy</span>');
        return '<tr><td data-label="Select"><input type="checkbox" data-post-id value="'+p.id+'"></td><td data-label="Page / post"><a href="'+esc(p.view||'#')+'" target="_blank" rel="noopener">'+esc(p.title||'(no title)')+'</a><div class="ninecm-subtle">#'+p.id+' · '+esc(p.type)+'</div><button type="button" class="button button-small" data-ninecm-structure-item="'+p.id+'">Edit structure</button></td><td data-label="Status">'+esc(p.status)+'</td><td data-label="Relationships"><div class="ninecm-rel-list">'+rel+'</div></td></tr>';
      }).join('')||'<tr><td colspan="4">No pages/posts found.</td></tr>';
      pager(document.querySelector('[data-ninecm-post-pager]'),postPage,r.pages,function(p){postPage=p;loadPosts();});
    }).catch(function(e){if(seq===postSeq)message(e.message);});
  }
  var ps,postSearch=document.querySelector('[data-ninecm-post-search]');if(postSearch)postSearch.oninput=function(){clearTimeout(ps);ps=setTimeout(function(){postPage=1;loadPosts();},250);};
  var postStatus=document.querySelector('[data-ninecm-post-status]');if(postStatus)postStatus.onchange=function(){postPage=1;loadPosts();};
  var postRelScope=document.querySelector('[data-ninecm-post-rel-scope]');if(postRelScope)postRelScope.onchange=function(){postPage=1;loadPosts();};
  var selAll=document.querySelector('[data-ninecm-select-all]');if(selAll)selAll.onchange=function(){var v=this.checked;document.querySelectorAll('[data-post-id]').forEach(function(x){x.checked=v;});};

  function renderSelectedTerms(){
    var box=document.querySelector('[data-ninecm-selected-terms]');if(!box)return;
    if(!selectedTerms.size){box.innerHTML='<span class="ninecm-subtle">No terms selected.</span>';return;}
    box.innerHTML=Array.from(selectedTerms.values()).map(function(t){return '<span class="ninecm-chip">'+esc(t.path)+' <button type="button" data-ninecm-remove-term data-id="'+t.id+'" aria-label="Remove">×</button></span>';}).join('');
  }

  function searchRelationshipTerms(){
    var input=document.querySelector('[data-ninecm-assign-term-search]'),out=document.querySelector('[data-ninecm-assign-term-results]');if(!input||!out)return;
    var seq=++relSeq,q=encodeURIComponent(input.value||'');out.innerHTML='<span class="ninecm-subtle">Searching…</span>';
    api('terms?taxonomy='+encodeURIComponent(tax)+'&per_page=50&archived=active&search='+q).then(function(r){if(seq!==relSeq)return;out.innerHTML=r.data.map(function(t){return '<button type="button" class="ninecm-term-pick" data-ninecm-pick-term data-id="'+t.id+'" data-path="'+esc(t.path)+'"><span>'+esc(t.path)+'</span><small>#'+t.id+'</small></button>';}).join('')||'<span class="ninecm-subtle">No matching terms.</span>';}).catch(function(e){if(seq===relSeq)out.textContent=e.message;});
  }
  var rtl=document.querySelector('[data-ninecm-assign-term-load]');if(rtl)rtl.onclick=searchRelationshipTerms;
  var rts=document.querySelector('[data-ninecm-assign-term-search]');if(rts){var rtimer;rts.oninput=function(){clearTimeout(rtimer);rtimer=setTimeout(searchRelationshipTerms,250);};}

  var apply=document.querySelector('[data-ninecm-apply]');
  if(apply)apply.onclick=function(){
    if(!cap('assign'))return message('You do not have permission to assign this taxonomy.');
    if(!postType)return message('Choose a content type.');if(!taxonomyCompatible())return message('The selected taxonomy is not attached to this content type. Use Edit structure to manage its compatible allocation systems.');
    var ids=Array.from(document.querySelectorAll('[data-post-id]:checked')).map(function(x){return parseInt(x.value,10);});
    var terms=Array.from(selectedTerms.keys()),mode=document.querySelector('[data-ninecm-assign-mode]').value;
    if(!ids.length)return message('Select at least one page or post.');
    if(!terms.length&&mode!=='replace')return message('Select at least one taxonomy relationship.');
    if(mode==='replace'&&!terms.length&&!window.confirm('Replace with no terms? This will remove all relationships in the selected taxonomy from the selected content.'))return;
    var btn=this;btn.disabled=true;
    api('assign',{method:'POST',body:JSON.stringify({taxonomy:tax,post_ids:ids,term_ids:terms,mode:mode})}).then(function(r){message('Updated '+r.data.updated+' item(s).'+(r.data.skipped&&r.data.skipped.length?' Skipped '+r.data.skipped.length+'.':'')+(r.data.undoAvailable?' You can undo this allocation for 30 minutes.':''));loadPosts();refreshUndo();}).catch(function(e){message(e.message);}).finally(function(){syncCapabilities();});
  };


  var undoBtn=document.querySelector('[data-ninecm-undo-assignment]');
  function refreshUndo(){
    if(!undoBtn||!tax)return;
    api('undo-assignment?taxonomy='+encodeURIComponent(tax)).then(function(r){var d=r.data||{};undoBtn.hidden=!d.available;undoBtn.textContent=d.available?'Undo last allocation ('+d.count+' item'+(d.count===1?'':'s')+')':'Undo last allocation';}).catch(function(){undoBtn.hidden=true;});
  }
  if(undoBtn)undoBtn.onclick=function(){
    if(!window.confirm('Restore the taxonomy relationships that existed immediately before your last bulk allocation?'))return;
    undoBtn.disabled=true;api('undo-assignment',{method:'POST',body:JSON.stringify({taxonomy:tax})}).then(function(r){message('Restored '+r.data.restored+' item(s).'+(r.data.skipped&&r.data.skipped.length?' Skipped '+r.data.skipped.length+'.':''));loadPosts();refreshUndo();}).catch(function(e){message(e.message);}).finally(function(){undoBtn.disabled=false;});
  };

  function selectedPostIds(){return Array.from(document.querySelectorAll('[data-post-id]:checked')).map(function(x){return parseInt(x.value,10);}).filter(Boolean);}
  var structUndoBtn=document.querySelector('[data-ninecm-undo-structure]');
  function refreshStructureUndo(){
    if(!structUndoBtn||!postType){if(structUndoBtn)structUndoBtn.hidden=true;return;}
    api('undo-structure?post_type='+encodeURIComponent(postType)).then(function(r){var d=r.data||{};structUndoBtn.hidden=!d.available;structUndoBtn.textContent=d.available?'Undo '+String(d.action||'structural')+' change ('+d.count+' item'+(d.count===1?'':'s')+')':'Undo structural change';}).catch(function(){structUndoBtn.hidden=true;});
  }
  function applyBulkStructure(action,payload,confirmText){
    var ids=selectedPostIds();if(!ids.length)return message('Select at least one content item in the table above.');
    if(confirmText&&!window.confirm(confirmText.replace('{count}',ids.length)))return;
    payload=Object.assign({post_type:postType,post_ids:ids,action_type:action},payload||{});
    api('bulk-structure',{method:'POST',body:JSON.stringify(payload)}).then(function(r){message('Updated '+r.data.updated+' item(s).'+(r.data.skipped&&r.data.skipped.length?' Skipped '+r.data.skipped.length+'.':'')+(r.data.undoAvailable?' You can undo this structural change for 30 minutes.':''));loadPosts();refreshStructureUndo();}).catch(function(e){message(e.message);});
  }
  if(structUndoBtn)structUndoBtn.onclick=function(){if(!window.confirm('Restore author, parent and native order values from immediately before the last structural bulk change?'))return;structUndoBtn.disabled=true;api('undo-structure',{method:'POST',body:JSON.stringify({post_type:postType})}).then(function(r){message('Restored '+r.data.restored+' item(s).'+(r.data.skipped&&r.data.skipped.length?' Skipped '+r.data.skipped.length+'.':''));loadPosts();refreshStructureUndo();}).catch(function(e){message(e.message);}).finally(function(){structUndoBtn.disabled=false;});};

  var bulkAuthorSearch=document.querySelector('[data-ninecm-bulk-author-search]'),bulkAuthorHidden=document.querySelector('[data-ninecm-bulk-author]'),bulkAuthorResults=document.querySelector('[data-ninecm-bulk-author-results]');
  if(bulkAuthorSearch){var bat,baseq=0;bulkAuthorSearch.oninput=function(){clearTimeout(bat);bat=setTimeout(function(){var my=++baseq;api('authors?post_type='+encodeURIComponent(postType)+'&search='+encodeURIComponent(bulkAuthorSearch.value||'')).then(function(r){if(my!==baseq)return;bulkAuthorResults.innerHTML=(r.data||[]).map(function(a){return '<button type="button" class="button-link" data-ninecm-bulk-author-pick="'+a.id+'" data-name="'+esc(a.name)+'">'+esc(a.name)+'</button>';}).join('<br>')||'<span class="ninecm-subtle">No matching authors.</span>';});},250);};bulkAuthorResults.onclick=function(e){var b=e.target.closest('[data-ninecm-bulk-author-pick]');if(!b)return;bulkAuthorHidden.value=b.dataset.ninecmBulkAuthorPick;bulkAuthorSearch.value=b.dataset.name;bulkAuthorResults.innerHTML='';};}
  var bulkAuthorApply=document.querySelector('[data-ninecm-bulk-author-apply]');if(bulkAuthorApply)bulkAuthorApply.onclick=function(){var author=parseInt(bulkAuthorHidden&&bulkAuthorHidden.value||0,10);if(!author)return message('Choose an author first.');applyBulkStructure('author',{author:author},'Assign this author to {count} selected item(s)?');};

  var bulkParentSearch=document.querySelector('[data-ninecm-bulk-parent-search]'),bulkParentHidden=document.querySelector('[data-ninecm-bulk-parent]'),bulkParentResults=document.querySelector('[data-ninecm-bulk-parent-results]');
  if(bulkParentSearch){var bpt,bpseq=0;bulkParentSearch.oninput=function(){clearTimeout(bpt);bpt=setTimeout(function(){var my=++bpseq;api('parents?post_type='+encodeURIComponent(postType)+'&search='+encodeURIComponent(bulkParentSearch.value||'')).then(function(r){if(my!==bpseq)return;bulkParentResults.innerHTML=(r.data||[]).map(function(x){return '<button type="button" class="button-link" data-ninecm-bulk-parent-pick="'+x.id+'" data-title="'+esc(x.title)+'">'+esc(x.title)+' <small>#'+x.id+'</small></button>';}).join('<br>')||'<span class="ninecm-subtle">No matching parent items.</span>';});},250);};bulkParentResults.onclick=function(e){var b=e.target.closest('[data-ninecm-bulk-parent-pick]');if(!b)return;bulkParentHidden.value=b.dataset.ninecmBulkParentPick;bulkParentSearch.value=b.dataset.title;bulkParentResults.innerHTML='';};}
  var bulkParentClear=document.querySelector('[data-ninecm-bulk-parent-clear]');if(bulkParentClear)bulkParentClear.onclick=function(){if(bulkParentHidden)bulkParentHidden.value='0';if(bulkParentSearch)bulkParentSearch.value='Top level';if(bulkParentResults)bulkParentResults.innerHTML='';};
  var bulkParentApply=document.querySelector('[data-ninecm-bulk-parent-apply]');if(bulkParentApply)bulkParentApply.onclick=function(){var parent=parseInt(bulkParentHidden&&bulkParentHidden.value||0,10)||0;applyBulkStructure('parent',{parent:parent},parent?'Move {count} selected item(s) beneath this parent?':'Move {count} selected item(s) to the top level?');};

  var bulkOrderApply=document.querySelector('[data-ninecm-bulk-order-apply]');if(bulkOrderApply)bulkOrderApply.onclick=function(){var start=parseInt(document.querySelector('[data-ninecm-bulk-order-start]').value||0,10)||0,step=parseInt(document.querySelector('[data-ninecm-bulk-order-step]').value||1,10)||1;applyBulkStructure('order',{order_start:start,order_step:step},'Apply a sequential native order to {count} selected item(s) in their current table order?');};

  function requestId(){return 'ncm-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,10);}
  function openMedia(state,preview){
    if(!C.canUpload||!window.wp||!wp.media)return message('The WordPress Media Library is unavailable for this account/session.');
    var frame=wp.media({title:'Select planning featured image',button:{text:'Use image'},multiple:false,library:{type:'image'}});
    frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();state.id=parseInt(a.id||0,10);state.url=(a.sizes&&a.sizes.medium&&a.sizes.medium.url)||a.url||'';preview.innerHTML=state.url?'<img src="'+esc(state.url)+'" alt=""><button type="button" class="button" data-struct-media-clear>Remove</button>':'<span class="ninecm-subtle">No featured image</span>';var c=preview.querySelector('[data-struct-media-clear]');if(c)c.onclick=function(){state.id=0;state.url='';preview.innerHTML='<span class="ninecm-subtle">No featured image</span>';};});frame.open();
  }
  function descriptorPlan(d){return {id:0,title:'',type:d.name,typeLabel:d.singular||d.label||d.name,status:'draft',excerpt:'',author:{id:0,name:'Current user'},parent:{id:0,title:''},menuOrder:0,featuredMedia:{id:0,url:''},supports:d.supports||{},supportsParent:!!d.supportsParent,supportsOrder:!!d.supportsOrder,taxonomies:(d.taxonomies||[]).map(function(t){var terms=[];if(t.name===tax)terms=Array.from(selectedTerms.values()).map(function(x){return {id:x.id,path:x.path,name:x.path,archived:false};});return Object.assign({},t,{terms:terms});}),providers:d.providers||[]};}
  function openStructureEditor(postId){
    if(!dlg||!dc||!postType)return;dc.innerHTML='<p>Loading structure editor…</p>';dlg.hidden=false;
    var promise=postId?api('plan/'+postId).then(function(r){return r.data;}):api('infrastructure?post_type='+encodeURIComponent(postType)).then(function(r){return descriptorPlan(r.data);});
    promise.then(function(plan){
      var taxState={},media={id:parseInt(plan.featuredMedia&&plan.featuredMedia.id||0,10),url:plan.featuredMedia&&plan.featuredMedia.url||''},author={id:parseInt(plan.author&&plan.author.id||0,10),name:plan.author&&plan.author.name||''},parent={id:parseInt(plan.parent&&plan.parent.id||0,10),title:plan.parent&&plan.parent.title||''},providers={},createKey=postId?'':requestId();
      (plan.providers||[]).forEach(function(p){providers[p.key]=p.value==null?'':p.value;});
      (plan.taxonomies||[]).forEach(function(t){var m=new Map();(t.terms||[]).forEach(function(x){m.set(parseInt(x.id,10),{id:parseInt(x.id,10),path:x.path||x.name,archived:!!x.archived});});taxState[t.name]={d:t,m:m};});
      var taxHtml=(plan.taxonomies||[]).map(function(t){return '<details class="ninecm-struct-tax" data-struct-tax="'+esc(t.name)+'" '+(t.name===tax?'open':'')+'><summary><strong>'+esc(t.label)+'</strong> <span class="ninecm-subtle">'+(t.hierarchical?'hierarchy':'tags / flat')+'</span></summary><div class="ninecm-picker-search"><input type="search" data-struct-tax-search placeholder="Search '+esc(t.label)+'"><button class="button" type="button" data-struct-tax-load>Search</button></div><div class="ninecm-term-results" data-struct-tax-results></div><div class="ninecm-selected-terms" data-struct-tax-selected></div></details>';}).join('')||'<p>No compatible taxonomies are registered for this content type.</p>';
      var provHtml=(plan.providers||[]).map(function(p){if(p.type==='select'&&p.hasOptions){return '<label>'+esc(p.label)+'<input type="search" data-struct-provider-search="'+esc(p.key)+'" value="'+esc(p.value==null?'':p.value)+'" placeholder="Search relationship"><input type="hidden" data-struct-provider="'+esc(p.key)+'" value="'+esc(p.value==null?'':p.value)+'"><div class="ninecm-mini-results" data-struct-provider-results="'+esc(p.key)+'"></div></label>';}return '<label>'+esc(p.label)+'<input '+(p.type==='number'?'type="number"':'type="text"')+' data-struct-provider="'+esc(p.key)+'" value="'+esc(p.value==null?'':p.value)+'"></label>';}).join('');
      var mediaHtml=(plan.supports&&plan.supports.thumbnail)?'<div><strong>Featured image</strong><div class="ninecm-admin-media-preview" data-struct-media-preview>'+(media.url?'<img src="'+esc(media.url)+'" alt=""><button type="button" class="button" data-struct-media-clear>Remove</button>':'<span class="ninecm-subtle">No featured image</span>')+'</div>'+(C.canUpload?'<button type="button" class="button" data-struct-media>Choose image</button>':'')+'</div>':'';
      var authorHtml=(plan.supports&&plan.supports.author)?'<label>Author<input type="search" data-struct-author-search value="'+esc(author.name)+'" placeholder="Search authors"><input type="hidden" data-struct-author value="'+author.id+'"><div class="ninecm-mini-results" data-struct-author-results></div></label>':'';
      var titleHtml=(plan.supports&&plan.supports.title)?'<label>Title<input data-struct-title value="'+esc(plan.title||'')+'"></label>':'';
      var excerptHtml=(plan.supports&&plan.supports.excerpt)?'<label>Excerpt / planning summary<textarea data-struct-excerpt rows="5">'+esc(plan.excerpt||'')+'</textarea></label>':'';
      dc.innerHTML='<h2>'+(postId?'Edit site structure':'Create planning shell')+'</h2><p class="description">Planning fields only. Detailed content, ACF and other page data remain in 9 Post Manager.</p><div class="ninecm-structure-editor-grid"><div class="ninecm-card"><div class="ninecm-form-grid">'+titleHtml+excerptHtml+mediaHtml+authorHtml+''+(plan.supportsParent?'<label>Parent '+esc(plan.typeLabel||'item')+'<input type="search" data-struct-parent-search value="'+esc(parent.title)+'" placeholder="Search parent"><input type="hidden" data-struct-parent value="'+parent.id+'"><div class="ninecm-mini-results" data-struct-parent-results></div><button type="button" class="button" data-struct-parent-clear>Top level</button></label>':'')+(plan.supportsOrder?'<label>Manual order<input type="number" data-struct-order value="'+parseInt(plan.menuOrder||0,10)+'"></label>':'')+provHtml+'</div><p><button type="button" class="button button-primary" data-struct-save>'+(postId?'Save structure':'Create draft shell')+'</button> <span data-struct-result></span></p></div><div class="ninecm-card"><h3>All allocation systems for this content type</h3>'+taxHtml+'</div></div>';
      function renderSelected(name){var st=taxState[name],box=dc.querySelector('[data-struct-tax="'+CSS.escape(name)+'"] [data-struct-tax-selected]');if(!st||!box)return;var arr=Array.from(st.m.values());box.innerHTML=arr.length?arr.map(function(x){return '<span class="ninecm-chip">'+esc(x.path)+(x.archived?' <em>archived</em>':'')+' <button type="button" data-struct-tax-remove="'+x.id+'">×</button></span>';}).join(''):'<span class="ninecm-subtle">No terms selected.</span>';}
      Object.keys(taxState).forEach(renderSelected);
      dc.querySelectorAll('[data-struct-tax]').forEach(function(panel){var tn=panel.dataset.structTax,st=taxState[tn],seq=0;function load(){var q=panel.querySelector('[data-struct-tax-search]').value||'',out=panel.querySelector('[data-struct-tax-results]'),my=++seq;out.innerHTML='<span class="ninecm-subtle">Searching…</span>';api('terms?taxonomy='+encodeURIComponent(tn)+'&archived=active&per_page=50&search='+encodeURIComponent(q)).then(function(r){if(my!==seq)return;out.innerHTML=(r.data||[]).map(function(t){return '<button type="button" class="ninecm-term-pick '+(st.m.has(parseInt(t.id,10))?'is-selected':'')+'" data-struct-tax-add="'+t.id+'" data-path="'+esc(t.path||t.name)+'"><span>'+esc(t.path||t.name)+'</span><small>#'+t.id+'</small></button>';}).join('')||'<span class="ninecm-subtle">No matches.</span>';}).catch(function(e){out.textContent=e.message;});}panel.querySelector('[data-struct-tax-load]').onclick=load;var ti=panel.querySelector('[data-struct-tax-search]'),tm;ti.oninput=function(){clearTimeout(tm);tm=setTimeout(load,250);};panel.onclick=function(e){var a=e.target.closest('[data-struct-tax-add]');if(a){st.m.set(parseInt(a.dataset.structTaxAdd,10),{id:parseInt(a.dataset.structTaxAdd,10),path:a.dataset.path,archived:false});renderSelected(tn);load();}var rem=e.target.closest('[data-struct-tax-remove]');if(rem){st.m.delete(parseInt(rem.dataset.structTaxRemove,10));renderSelected(tn);load();}};load();});
      var preview=dc.querySelector('[data-struct-media-preview]'),mediaBtn=dc.querySelector('[data-struct-media]');if(mediaBtn)mediaBtn.onclick=function(){openMedia(media,preview);};var mc=dc.querySelector('[data-struct-media-clear]');if(mc)mc.onclick=function(){media.id=0;media.url='';preview.innerHTML='<span class="ninecm-subtle">No featured image</span>';};
      var as=dc.querySelector('[data-struct-author-search]'),ah=dc.querySelector('[data-struct-author]'),ar=dc.querySelector('[data-struct-author-results]');if(as){var at;as.oninput=function(){clearTimeout(at);at=setTimeout(function(){api('authors?post_type='+encodeURIComponent(plan.type)+'&search='+encodeURIComponent(as.value||'')).then(function(r){ar.innerHTML=(r.data||[]).map(function(a){return '<button type="button" class="button-link" data-author="'+a.id+'" data-name="'+esc(a.name)+'">'+esc(a.name)+'</button>';}).join('<br>');});},250);};ar.onclick=function(e){var b=e.target.closest('[data-author]');if(!b)return;author.id=parseInt(b.dataset.author,10);author.name=b.dataset.name;ah.value=author.id;as.value=author.name;ar.innerHTML='';};}
      var ps=dc.querySelector('[data-struct-parent-search]'),ph=dc.querySelector('[data-struct-parent]'),pr=dc.querySelector('[data-struct-parent-results]');if(ps){var ptime;ps.oninput=function(){clearTimeout(ptime);ptime=setTimeout(function(){api('parents?post_type='+encodeURIComponent(plan.type)+'&exclude='+(plan.id||0)+'&search='+encodeURIComponent(ps.value||'')).then(function(r){pr.innerHTML=(r.data||[]).map(function(x){return '<button type="button" class="button-link" data-parent="'+x.id+'" data-title="'+esc(x.title)+'">'+esc(x.title)+' #'+x.id+'</button>';}).join('<br>');});},250);};pr.onclick=function(e){var b=e.target.closest('[data-parent]');if(!b)return;parent.id=parseInt(b.dataset.parent,10);parent.title=b.dataset.title;ph.value=parent.id;ps.value=parent.title;pr.innerHTML='';};var pc=dc.querySelector('[data-struct-parent-clear]');if(pc)pc.onclick=function(){parent.id=0;parent.title='';ph.value='0';ps.value='';};}
      dc.querySelectorAll('[data-struct-provider]').forEach(function(i){i.oninput=function(){providers[i.dataset.structProvider]=i.value;};});dc.querySelectorAll('[data-struct-provider-search]').forEach(function(input){var key=input.dataset.structProviderSearch,hidden=dc.querySelector('[data-struct-provider=\"'+CSS.escape(key)+'\"]'),results=dc.querySelector('[data-struct-provider-results=\"'+CSS.escape(key)+'\"]'),tm,seq=0;input.oninput=function(){clearTimeout(tm);tm=setTimeout(function(){var my=++seq;api('provider-options?post_type='+encodeURIComponent(plan.type)+'&post_id='+(plan.id||0)+'&key='+encodeURIComponent(key)+'&search='+encodeURIComponent(input.value||'')).then(function(r){if(my!==seq)return;results.innerHTML=(r.data||[]).map(function(o){return '<button type=\"button\" class=\"button-link\" data-provider-value=\"'+esc(o.value)+'\" data-provider-label=\"'+esc(o.label)+'\">'+esc(o.label)+'</button>';}).join('<br>')||'<span class=\"ninecm-subtle\">No matches.</span>';});},250);};results.onclick=function(e){var b=e.target.closest('[data-provider-value]');if(!b)return;hidden.value=b.dataset.providerValue;providers[key]=b.dataset.providerValue;input.value=b.dataset.providerLabel;results.innerHTML='';};});
      dc.querySelector('[data-struct-save]').onclick=function(){var btn=this,titleInput=dc.querySelector('[data-struct-title]'),excerptInput=dc.querySelector('[data-struct-excerpt]'),title=titleInput?titleInput.value.trim():'';if(titleInput&&!title)return message('Enter a title.');var taxes={};Object.keys(taxState).forEach(function(k){taxes[k]=Array.from(taxState[k].m.keys());});var body={taxonomies:taxes,providers:providers};if(titleInput)body.title=title;if(excerptInput)body.excerpt=excerptInput.value;if(plan.supportsOrder)body.menu_order=parseInt(dc.querySelector('[data-struct-order]')&&dc.querySelector('[data-struct-order]').value||0,10)||0;if(plan.supports&&plan.supports.thumbnail)body.featured_media=media.id;if(plan.supports&&plan.supports.author)body.author=parseInt(ah&&ah.value||0,10)||0;if(plan.supportsParent)body.parent=parseInt(ph&&ph.value||0,10)||0;btn.disabled=true;var req=postId?api('plan/'+postId,{method:'PATCH',body:JSON.stringify(body)}):api('posts',{method:'POST',body:JSON.stringify(Object.assign(body,{post_type:plan.type,request_id:createKey}))});req.then(function(r){var d=r.data;dc.querySelector('[data-struct-result]').innerHTML=postId?'<strong>Saved.</strong>':'<strong>Created #'+d.id+'</strong> <a href="'+esc(d.view||'#')+'" target="_blank" rel="noopener">Open</a>';if(!postId)createKey=requestId();loadPosts();}).catch(function(e){message(e.message);}).finally(function(){btn.disabled=false;});};
    }).catch(function(e){dc.innerHTML='<div class="notice notice-error"><p>'+esc(e.message)+'</p></div>';});
  }
  function openCreateShell(){var d=currentPostType();if(!d||!d.canCreate)return message('This content type can be structurally managed here, but generic blank-shell creation is disabled for safety.');openStructureEditor(0);}
  var create=document.querySelector('[data-ninecm-create-shell]');if(create)create.onclick=openCreateShell;
  document.addEventListener('click',function(e){var b=e.target.closest('[data-ninecm-structure-item]');if(b)openStructureEditor(parseInt(b.dataset.ninecmStructureItem,10));});

  function parseBulkPaths(text){
    var stack=[],paths=[],errors=[];
    String(text||'').split(/\r?\n/).forEach(function(line,i){
      if(!line.trim())return;
      var m=line.match(/^\s*(>*)(.*)$/),depth=(m&&m[1]||'').length,name=(m&&m[2]||'').trim();
      if(!name)return;
      if(depth>50){errors.push('Line '+(i+1)+': depth is greater than 50.');return;}
      if(depth>0&&!stack[depth-1]){errors.push('Line '+(i+1)+' ('+name+'): missing parent level.');return;}
      stack[depth]=name;stack.length=depth+1;paths.push(stack.slice());
    });
    return {paths:paths,errors:errors};
  }

  var importedStructureState=[];
  var importStructure=document.querySelector('[data-ninecm-import-structure]');
  if(importStructure)importStructure.onchange=function(){
    var file=this.files&&this.files[0];if(!file)return;var input=document.querySelector('[data-ninecm-bulk]');
    file.text().then(function(text){var j=JSON.parse(text);if(!j||!Array.isArray(j.terms))throw new Error('This is not a 9 Category Manager structure/blueprint JSON file.');
      var terms=j.terms,byParent={};terms.forEach(function(t){var p=parseInt(t.parent||0,10);(byParent[p]||(byParent[p]=[])).push(t);});
      Object.keys(byParent).forEach(function(k){byParent[k].sort(function(a,b){var ao=parseInt(a.display_order||0,10),bo=parseInt(b.display_order||0,10);return ao-bo||String(a.name||'').localeCompare(String(b.name||''));});});
      var lines=[];function walk(parent,depth){(byParent[parent]||[]).forEach(function(t){lines.push('>'.repeat(depth)+String(t.name||''));walk(parseInt(t.id,10),depth+1);});}walk(0,0);
      if(!lines.length)throw new Error('No restorable category terms were found in this JSON file.');input.value=lines.join('\n');importedStructureState=terms.map(function(t){return {path:String(t.path||''),display_order:parseInt(t.display_order||0,10),protected:!!t.protected,archived:!!t.archived};}).filter(function(t){return !!t.path;});message('Loaded '+lines.length+' category lines. Review them, then click Create hierarchy. After topology is created, 9-specific order/protection/archive state will be restored automatically in safe batches.');
    }).catch(function(e){message(e.message||String(e));});this.value='';
  };


  function restoreImportedStructureState(done){
    if(!importedStructureState.length){done('');return;}
    var rows=importedStructureState.slice(),idx=0,restored=0,errors=[];
    function next(){
      if(idx>=rows.length){var detail=' · Restored plugin state: '+restored+(errors.length?' · State errors: '+esc(errors.slice(0,20).join('; ')):'');importedStructureState=[];done(detail);return;}
      var batch=rows.slice(idx,idx+80);idx+=batch.length;
      api('restore-term-state',{method:'POST',body:JSON.stringify({taxonomy:tax,rows:batch})}).then(function(r){restored+=parseInt(r.data.restored||0,10);if(r.data.errors&&r.data.errors.length)errors=errors.concat(r.data.errors);next();}).catch(function(e){errors.push(e.message||String(e));importedStructureState=[];done(' · Plugin-state restore stopped: '+esc(errors.slice(0,20).join('; ')));});
    }
    next();
  }

  var bulk=document.querySelector('[data-ninecm-bulk-run]');
  if(bulk)bulk.onclick=function(){
    if(!cap('manage'))return message('You do not have permission to create categories in this taxonomy.');
    var text=document.querySelector('[data-ninecm-bulk]').value,parsed=parseBulkPaths(text);if(!text.trim())return;
    var out=document.querySelector('[data-ninecm-bulk-result]');
    if(parsed.errors.length){out.innerHTML='<div class="ninecm-toast ninecm-toast-error">Fix the hierarchy first:<br>'+esc(parsed.errors.slice(0,20).join('; '))+(parsed.errors.length>20?' …':'')+'</div>';return;}
    if(parsed.paths.length>5000){out.textContent='For one run, use no more than 5,000 hierarchy lines.';return;}
    var index=0,created=0,existing=0,errors=[],maxPathsPerBatch=100,maxSegmentsPerBatch=600,restoreMode=importedStructureState.length>0;bulk.disabled=true;
    function next(){
      if(index>=parsed.paths.length){restoreImportedStructureState(function(stateDetail){bulk.disabled=false;out.innerHTML='<div class="ninecm-toast">Completed '+parsed.paths.length+' hierarchy line(s). Created: '+created+' · Existing checks: '+existing+(errors.length?' · Errors: '+esc(errors.slice(0,20).join('; ')):'')+stateDetail+'</div>';loadTerms();searchRelationshipTerms();});return;}
      var from=index,batch=[],segments=0;
      while(index<parsed.paths.length&&batch.length<maxPathsPerBatch){var candidate=parsed.paths[index],cost=candidate.length;if(batch.length&&segments+cost>maxSegmentsPerBatch)break;batch.push(candidate);segments+=cost;index++;}
      out.textContent='Building hierarchy… '+Math.min(index,parsed.paths.length)+' / '+parsed.paths.length+'. You may safely rerun the same plan if the connection is interrupted.';
      api('bulk-terms',{method:'POST',body:JSON.stringify({taxonomy:tax,paths:batch,restore_mode:restoreMode})}).then(function(r){created+=parseInt(r.data.created||0,10);existing+=parseInt(r.data.existing||0,10);if(r.data.errors&&r.data.errors.length)errors=errors.concat(r.data.errors);next();}).catch(function(e){bulk.disabled=false;out.innerHTML='<div class="ninecm-toast ninecm-toast-error">Stopped after '+from+' line(s): '+esc(e.message)+'. Already-created categories are safe; rerun the same plan to continue idempotently.</div>';loadTerms();});
    }
    next();
  };


  function parseBulkShells(text){
    var rows=[],errors=[];String(text||'').split(/\r?\n/).forEach(function(line,i){if(!line.trim())return;var parts=line.split('::');if(parts.length<2){errors.push('Line '+(i+1)+': use Category > Subcategory :: Page title :: Optional excerpt');return;}var path=parts.shift().trim(),title=parts.shift().trim(),excerpt=parts.join('::').trim();if(!path||!title){errors.push('Line '+(i+1)+': category path and page title are required.');return;}rows.push({path:path,title:title,excerpt:excerpt});});return {rows:rows,errors:errors};
  }
  var bulkShell=document.querySelector('[data-ninecm-bulk-shell-run]');
  if(bulkShell)bulkShell.onclick=function(){
    if(!postType)return message('Choose a planning content type first.');if(!taxonomyCompatible())return message('Choose a taxonomy attached to this content type for bulk shell allocation.');if(!cap('assign'))return message('You do not have permission to assign this taxonomy.');
    var text=document.querySelector('[data-ninecm-bulk-shells]').value,parsed=parseBulkShells(text),out=document.querySelector('[data-ninecm-bulk-shell-result]');if(!text.trim())return;
    if(parsed.errors.length){out.innerHTML='<div class="ninecm-toast ninecm-toast-error">'+esc(parsed.errors.slice(0,20).join('; '))+'</div>';return;}if(parsed.rows.length>5000){return message('For one run, use no more than 5,000 page-shell lines.');}
    if(!window.confirm('Create '+parsed.rows.length+' blank '+postType+' shell(s) using the selected taxonomy? Existing identical bulk-plan shells will be reused.'))return;
    var index=0,created=0,reused=0,errors=[];bulkShell.disabled=true;
    function next(){if(index>=parsed.rows.length){bulkShell.disabled=false;out.innerHTML='<div class="ninecm-toast">Completed '+parsed.rows.length+' line(s). Created: '+created+' · Reused: '+reused+(errors.length?' · Errors: '+esc(errors.slice(0,25).join('; ')):'')+'</div>';loadPosts();return;}var batch=parsed.rows.slice(index,index+40);index+=batch.length;out.textContent='Creating planning shells… '+index+' / '+parsed.rows.length;api('bulk-shells',{method:'POST',body:JSON.stringify({taxonomy:tax,post_type:postType,rows:batch})}).then(function(r){created+=parseInt(r.data.created||0,10);reused+=parseInt(r.data.reused||0,10);if(r.data.errors&&r.data.errors.length)errors=errors.concat(r.data.errors);next();}).catch(function(e){bulkShell.disabled=false;out.innerHTML='<div class="ninecm-toast ninecm-toast-error">Stopped near line '+(index-batch.length+1)+': '+esc(e.message)+'. Re-run safely; identical completed rows are idempotent.</div>';});}
    next();
  };

  function healthList(items,kind){if(!items||!items.length)return '<span class="ninecm-subtle">No sampled issues.</span>';if(kind==='duplicate_names')return '<ul>'+items.map(function(x){return '<li><strong>'+esc(x.name)+'</strong>: '+esc((x.paths||[]).join(' | '))+'</li>';}).join('')+'</ul>';return '<ul>'+items.map(function(x){return '<li>'+esc(x.title||x.path||('#'+x.id))+(x.depth?' · depth '+x.depth:'')+(x.children?' · '+x.children+' direct children':'')+'</li>';}).join('')+'</ul>';}
  var healthBtn=document.querySelector('[data-ninecm-health-run]');
  if(healthBtn)healthBtn.onclick=function(){
    if(!postType)return message('Choose a compatible content type in Page Planning first.');var out=document.querySelector('[data-ninecm-health-result]');healthBtn.disabled=true;out.innerHTML='<p>Auditing taxonomy and planning relationships…</p>';
    api('health?taxonomy='+encodeURIComponent(tax)+'&post_type='+encodeURIComponent(postType)).then(function(r){var d=r.data,c=d.counts||{},samp=d.samples||{},scope=d.scope||{};var cards=[['Unassigned content',c.unassigned_content,'unassigned_content'],['Only in archived branches',c.archived_only_content,'archived_only_content'],['Redundant ancestor assignments',c.redundant_relationships,'redundant_relationships'],['Empty leaf categories',scope.empty_leaf_check_limited?null:c.empty_leaf_categories,'empty_leaf_categories'],['Deep categories',c.deep_categories,'deep_categories'],['Wide branches',c.wide_branches,'wide_branches'],['Repeated category names',c.duplicate_names,'duplicate_names'],['Stale blank shells',c.stale_shells,'stale_shells']];out.innerHTML='<div class="ninecm-health-score"><strong>'+d.score+'/100</strong><span>'+esc(d.label)+'</span><small>'+scope.term_count+' categories · '+scope.scanned_items+' content items scanned'+(scope.scan_limited?' (bounded relationship scan)':'')+'</small></div><div class="ninecm-health-grid">'+cards.map(function(x){var unknown=x[1]===null;return '<details class="ninecm-health-card" '+(!unknown&&!x[1]?'data-zero="1"':'')+'><summary><strong>'+esc(x[0])+'</strong><span>'+(unknown?'—':parseInt(x[1]||0,10))+'</span></summary>'+(unknown?'<p class="ninecm-subtle">Skipped because the relationship scan reached its safety bound; reporting zero here could create false confidence or false positives.</p>':healthList(samp[x[2]],x[2]))+'</details>';}).join('')+'</div><p class="description">Protected categories: '+(c.protected_categories||0)+' · Archived roots: '+(c.archived_roots||0)+' ('+(c.archived_categories||0)+' categories hidden in archived branches) · Categories with manual order: '+(c.manual_ordered||0)+'. Health Audit is diagnostic; it never deletes or rewrites your structure automatically.</p>';}).catch(function(e){out.innerHTML='<div class="ninecm-toast ninecm-toast-error">'+esc(e.message)+'</div>';}).finally(function(){healthBtn.disabled=false;});
  };

  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&dlg&&!dlg.hidden)dlg.hidden=true;});
  syncCapabilities();updateExportLinks();renderSelectedTerms();refreshUndo();refreshStructureUndo();
})();
