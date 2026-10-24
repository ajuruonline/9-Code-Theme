(function(){
  'use strict';

  document.addEventListener('change', function(e){
    if(e.target && e.target.matches('[data-ninecode-auto-submit]')){
      var form=e.target.closest('form');
      if(form){ form.submit(); }
    }
  });

  document.addEventListener('click', function(e){
    var importButton=e.target.closest('[data-ninecode-import]');
    if(importButton){
      var message=(window.NineCodeDataEngine&&NineCodeDataEngine.confirmImport)||'Import these data changes now?';
      if(!window.confirm(message)){ e.preventDefault(); }
    }
    var undo=e.target.closest('form[data-ninecode-confirm="undo"] button');
    if(undo){
      var undoMessage=(window.NineCodeDataEngine&&NineCodeDataEngine.confirmUndo)||'Undo latest import?';
      if(!window.confirm(undoMessage)){ e.preventDefault(); }
    }
    var versionRestore=e.target.closest('form[data-ninecode-confirm="restore-version"] button');
    if(versionRestore){
      var versionMessage=(window.NineCodeDataEngine&&NineCodeDataEngine.confirmVersionRestore)||'Restore this saved data version?';
      if(!window.confirm(versionMessage)){ e.preventDefault(); }
    }
  });


  document.querySelectorAll('.ninecode-bulk-row').forEach(function(row){
    row.addEventListener('toggle', function(){
      if(!row.open || !window.acf || !window.jQuery){ return; }
      try{
        window.acf.doAction('show', window.jQuery(row).find('.acf-field'));
      }catch(err){ /* ACF will still provide the basic HTML control. */ }
    });
  });

  var search=document.getElementById('ninecode-field-search');
  if(search){
    var editor=search.closest('.ninecode-acf-editor');
    var fields=editor ? Array.prototype.slice.call(editor.querySelectorAll('.acf-field')) : [];
    var count=editor ? editor.querySelector('[data-ninecode-field-count]') : null;
    var filterFields=function(){
      var query=(search.value||'').trim().toLowerCase();
      var shown=0;
      fields.forEach(function(field){
        var label=field.querySelector('.acf-label label');
        var description=field.querySelector('.acf-label .description');
        var haystack=[
          label ? label.textContent : '',
          description ? description.textContent : '',
          field.getAttribute('data-name') || '',
          field.getAttribute('data-type') || ''
        ].join(' ').toLowerCase();
        var visible=!query || haystack.indexOf(query)!==-1;
        field.classList.toggle('ninecode-field-hidden', !visible);
        if(visible){ shown++; }
      });
      if(count){ count.textContent=shown+' field'+(shown===1?'':'s')+' shown'; }
    };
    search.addEventListener('input', filterFields);
    filterFields();
  }


  var metaSearch=document.getElementById('ninecode-meta-search');
  if(metaSearch){
    var metaPanel=metaSearch.closest('.ninecode-plugin-meta');
    var metaFields=metaPanel ? Array.prototype.slice.call(metaPanel.querySelectorAll('[data-ninecode-meta-field]')) : [];
    var metaCount=metaPanel ? metaPanel.querySelector('[data-ninecode-meta-count]') : null;
    var filterMeta=function(){
      var query=(metaSearch.value||'').trim().toLowerCase();
      var shown=0;
      metaFields.forEach(function(field){
        var haystack=(field.getAttribute('data-search')||field.textContent||'').toLowerCase();
        var visible=!query || haystack.indexOf(query)!==-1;
        field.classList.toggle('ninecode-field-hidden', !visible);
        if(visible){ shown++; }
      });
      if(metaCount){ metaCount.textContent=shown+' field'+(shown===1?'':'s')+' shown'; }
    };
    metaSearch.addEventListener('input', filterMeta);
    filterMeta();
  }
})();


document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-ninecode-select-changes]');
    if (!button) return;
    var mode = button.getAttribute('data-ninecode-select-changes');
    document.querySelectorAll('input[form="ninecode-selective-apply"][name="approved_change_ids[]"]').forEach(function (box) {
        box.checked = mode === 'all';
    });
});
