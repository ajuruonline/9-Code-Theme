(function($){'use strict';
$(function(){
    var frames={};

    function openMedia(key,title){
        if(frames[key]){frames[key].open();return;}
        frames[key]=wp.media({title:title||'Choose image',button:{text:'Use this image'},multiple:false});
        frames[key].on('select',function(){
            var item=frames[key].state().get('selection').first().toJSON();
            var input=$('[data-ncu-media-input="'+key+'"]');
            var preview=$('[data-ncu-media-preview="'+key+'"]');
            input.val(item.id);
            var src=(item.sizes&&item.sizes.thumbnail)?item.sizes.thumbnail.url:item.url;
            preview.html('<img src="'+src+'" alt="" width="64" height="64">');
        });
        frames[key].open();
    }

    $(document).on('click','[data-ncu-media-choose]',function(e){e.preventDefault();openMedia($(this).attr('data-ncu-media-choose'),'Choose branding image');});
    $(document).on('click','[data-ncu-media-clear]',function(e){e.preventDefault();var key=$(this).attr('data-ncu-media-clear');$('[data-ncu-media-input="'+key+'"]').val('0');$('[data-ncu-media-preview="'+key+'"]').empty();});

    /* Backward-compatible footer media control. */
    var footerFrame;
    $('[data-ncu-footer-image]').on('click',function(e){
        e.preventDefault();
        if(footerFrame){footerFrame.open();return;}
        footerFrame=wp.media({title:'Choose footer image',button:{text:'Use this image'},multiple:false});
        footerFrame.on('select',function(){
            var item=footerFrame.state().get('selection').first().toJSON();
            $('#ncu_footer_image_id').val(item.id);
            var src=(item.sizes&&item.sizes.thumbnail)?item.sizes.thumbnail.url:item.url;
            $('[data-ncu-footer-preview]').html('<img src="'+src+'" alt="" style="width:64px;height:64px;object-fit:contain;border-radius:10px">');
        });
        footerFrame.open();
    });
    $('[data-ncu-footer-clear]').on('click',function(e){e.preventDefault();$('#ncu_footer_image_id').val('0');$('[data-ncu-footer-preview]').empty();});

    $(document).on('change','.ncu-dark-palette-card input[type="radio"]',function(){
        $('.ncu-dark-palette-card').removeClass('is-selected');
        $(this).closest('.ncu-dark-palette-card').addClass('is-selected');
    });

    $(document).on('change','.ncu-admin-skin-preview input[type="radio"]',function(){
        $('.ncu-admin-skin-preview').removeClass('is-selected');
        $(this).closest('.ncu-admin-skin-preview').addClass('is-selected');
    });

    /* v3 Style Library: selected state and fast mobile-friendly search. */
    $(document).on('change','.ncu-style-card input[type="radio"]',function(){
        $('.ncu-style-card').removeClass('is-selected');
        $(this).closest('.ncu-style-card').addClass('is-selected');
    });
    $('#ncu-style-library-search').on('input',function(){
        var q=$.trim(String($(this).val()||'')).toLowerCase(),visible=0;
        $('[data-ncu-style-card]').each(function(){
            var card=$(this),hay=String(card.attr('data-search')||'').toLowerCase(),show=!q||hay.indexOf(q)!==-1;
            card.toggle(show); if(show){visible++;}
        });
        $('[data-ncu-style-family]').each(function(){
            var family=$(this),matches=family.find('[data-ncu-style-card]:visible').length>0;
            family.prop('hidden',!matches); if(q&&matches){family.prop('open',true);}
        });
        var marker=$('.ncu-style-search-empty');
        if(!visible){if(!marker.length){$('.ncu-style-family').last().after('<div class="ncu-style-search-empty">No matching style. Try a brand, colour or variant name.</div>');}}
        else{marker.remove();}
    });

    /* Success messages should never sit over the interface indefinitely. */
    window.setTimeout(function(){
        $('.ncu-one-shot-notice.notice-success').each(function(){
            var n=$(this);n.fadeOut(180,function(){n.remove();});
        });
    },4500);
});
})(jQuery);
