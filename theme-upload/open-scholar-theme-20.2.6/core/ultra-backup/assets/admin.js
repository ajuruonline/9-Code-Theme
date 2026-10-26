(function($){
    var exportForm=document.querySelector('.nine-export-form');
    var feedback=document.getElementById('osch-backup-feedback');
    function backupFeedback(text,busy){
        if(!feedback)return;
        feedback.hidden=false;feedback.classList.toggle('is-working',!!busy);
        feedback.querySelector('[data-backup-feedback-text]').textContent=text;
    }
    if(exportForm){
        exportForm.addEventListener('invalid',function(event){
            var section=event.target.closest('details');
            while(section){section.open=true;section=section.parentElement.closest('details');}
            backupFeedback('Complete the required backup name and selections above, then create your backup.',false);
        },true);
        exportForm.addEventListener('submit',function(event){
            if(exportForm.dataset.working==='1'){event.preventDefault();return;}
            exportForm.dataset.working='1';exportForm.setAttribute('aria-busy','true');
            backupFeedback('Preparing your backup… Keep this page open. You will be taken to the progress screen when the package steps are ready.',true);
            exportForm.querySelectorAll('[type=submit]').forEach(function(button){button.disabled=true;if(button.tagName==='INPUT')button.value='Preparing backup…';else button.textContent='Preparing backup…';});
            feedback.scrollIntoView({behavior:'smooth',block:'center'});
            window.setTimeout(function(){backupFeedback('The server is still preparing the backup. Large selections may take longer. Keep this page open; if the host returns an error, check Ultra Backup → Diagnostics before starting another backup.',true);},45000);
        });
        window.addEventListener('pageshow',function(event){if(!event.persisted)return;delete exportForm.dataset.working;exportForm.removeAttribute('aria-busy');exportForm.querySelectorAll('[type=submit]').forEach(function(button){button.disabled=false;if(button.tagName==='INPUT')button.value='Create Open Scholar Backup';else button.textContent='Create Open Scholar Backup';});if(feedback)feedback.hidden=true;});
    }
    $('[data-ninecode-settings-root] [data-ninecode-settings-section]').each(function(index){
        this.open=this.hasAttribute('data-osch-keep-open');
        $(this).addClass('ninecode-settings-section '+(index%2?'ninecode-tone-green':'ninecode-tone-red'));
        var summary=this.querySelector(':scope > summary');
        if(summary){summary.setAttribute('aria-expanded','false');this.addEventListener('toggle',function(){summary.setAttribute('aria-expanded',this.open?'true':'false');});}
    });
    function tokens(){
        return $('#nine-selected-tokens').val().split(/[\n,]+/).map(function(v){return v.trim();}).filter(Boolean);
    }
    function renderSelected(){
        var list=tokens(), $box=$('#nine-selected-list');
        if(!$box.length)return;
        if(!list.length){$box.html('<em>Nothing selected yet.</em>');return;}
        $box.empty();
        list.forEach(function(t){ $('<button type="button" class="nine-token"/>').text(t+' ×').attr('data-token',t).appendTo($box); });
    }
    function addToken(token){ var list=tokens(); if(list.indexOf(token)===-1){list.push(token); $('#nine-selected-tokens').val(list.join('\n')); renderSelected();} }
    $(document).on('click','.nine-token',function(){var target=$(this).data('token');var list=tokens().filter(function(t){return t!==target;});$('#nine-selected-tokens').val(list.join('\n'));renderSelected();});
    $(document).on('change keyup','#nine-selected-tokens',renderSelected);
    $(document).on('click','.nine-add-result',function(){addToken($(this).data('token'));});
    $('#nine-resource-search-btn').on('click',function(){
        var $btn=$(this), $results=$('#nine-resource-results');
        $btn.prop('disabled',true).text('Searching…'); $results.html('<p>Searching…</p>');
        $.post(NineBackup.ajaxUrl,{action:'nine_backup_search',nonce:NineBackup.nonce,kind:$('#nine-resource-kind').val(),q:$('#nine-resource-search').val()})
        .done(function(resp){
            if(!resp || !resp.success){$results.html('<p>Search failed.</p>');return;}
            if(!resp.data.length){$results.html('<p>No matching records found.</p>');return;}
            var html='<table class="widefat striped nine-results"><thead><tr><th>Record</th><th>Detail</th><th></th></tr></thead><tbody>';
            resp.data.forEach(function(r){html+='<tr><td>'+esc(r.label)+'</td><td>'+esc(r.detail)+'</td><td><button type="button" class="button nine-add-result" data-token="'+esc(r.token)+'">Add</button></td></tr>';});
            html+='</tbody></table>'; $results.html(html);
        }).fail(function(){$results.html('<p>Search request failed.</p>');}).always(function(){$btn.prop('disabled',false).text('Find');});
    });
    $('#nine-resource-search').on('keydown',function(e){if(e.key==='Enter'){e.preventDefault();$('#nine-resource-search-btn').trigger('click');}});
    function esc(s){return $('<div/>').text(s==null?'':s).html();}
    $(document).on('change','input[name="plugin_strategy"]',function(){
        var $submit=$('.nine-import-form input[type="submit"]');
        if(!$submit.length)return;
        if($(this).val()==='package_only'){$submit.val('Confirm Import');}
        else{$submit.val('Stage Plugins & Continue');}
    });
    renderSelected();
})(jQuery);

(function($){
    function runSafeStep(){
        var $box=$('#nine-safe-job-progress');
        if(!$box.length || !window.NineBackup || !NineBackup.safeNonce){return;}
        if (new URLSearchParams(window.location.search).get('manual')==='1') {
            $box.find('.nine-safe-status').append(' Manual continuation mode: use the button below for the next part.');
            return;
        }
        var $manual=$('#nine-backup-manual-step button[type=submit]');
        $manual.prop('disabled',true).attr('title','Automatic backup is currently running');
        function enableManual(){ $manual.prop('disabled',false).removeAttr('title'); }
        var attempts=0;
        function step(){
            var total=parseInt($box.attr('data-total')||'1',10);
            $.ajax({
                url:NineBackup.ajaxUrl,
                method:'POST',
                dataType:'json',
                timeout:65000,
                data:{action:'nine_backup_safe_step',nonce:NineBackup.safeNonce}
            }).done(function(resp){
                if(!resp || !resp.success){
                    var msg=(resp&&resp.data&&resp.data.message)?resp.data.message:'Backup part failed.';
                    $box.find('.nine-safe-status').text(msg+' Retrying…');
                    attempts++;
                    if(attempts<3){setTimeout(step,1500);}else{enableManual();$box.find('.nine-safe-status').text(msg+' Open Diagnostics or use the manual continuation button below.');}
                    return;
                }
                attempts=0;
                var d=resp.data||{}, current=parseInt(d.current||0,10), t=parseInt(d.total||total,10)||1;
                var percent=Math.min(100,Math.round((current/t)*100));
                $box.attr('data-current',current).attr('data-total',t);
                $box.find('.nine-progress-bar').css('width',percent+'%');
                var extra=d.last?' Last: '+d.last:'';
                $box.find('.nine-safe-status').text('Part '+current+' of '+t+' completed.'+extra);
                if(d.done){
                     $box.find('.nine-safe-status').text(d.errors ? 'Backup finished with '+d.errors+' failed part(s). Open Diagnostics. Loading archives…' : 'Backup complete. Loading archives…');
                    setTimeout(function(){window.location.reload();},700);
                }else{setTimeout(step,250);}
            }).fail(function(xhr){
                attempts++;
                var text='The server interrupted this backup part';
                if(xhr && xhr.status){text+=' (HTTP '+xhr.status+')';}
                if(attempts<3){$box.find('.nine-safe-status').text(text+'. Retrying the same small part…');setTimeout(step,1800);}
                else{
                    enableManual();
                    $box.find('.nine-safe-status').text(text+'. Checking whether AJAX is blocked…');
                    $.post(NineBackup.ajaxUrl,{action:'nine_backup_probe',nonce:NineBackup.safeNonce}).done(function(r){
                        $box.find('.nine-safe-status').text(r&&r.success?text+'. AJAX is available, but the export worker failed. Open Diagnostics or use manual continuation.':text+'. Probe failed. Use manual continuation below.');
                    }).fail(function(){
                        $box.find('.nine-safe-status').text(text+'. The AJAX service is unavailable or blocked. Use the manual continuation button below (nine_backup_safe_step_manual).');
                    });
                }
            });
        }
        step();
    }
    $(runSafeStep);
})(jQuery);
