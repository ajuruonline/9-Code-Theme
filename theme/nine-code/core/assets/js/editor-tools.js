(function(wp, cfg){
'use strict';
if(!wp || !cfg || !wp.plugins || !wp.editor || !wp.element || !wp.components || !wp.data){return;}
var el=wp.element.createElement;
var Fragment=wp.element.Fragment;
var registerPlugin=wp.plugins.registerPlugin;
var PluginSidebar=wp.editor.PluginSidebar;
var PluginDocumentSettingPanel=wp.editor.PluginDocumentSettingPanel;
var c=wp.components;
var apiFetch=wp.apiFetch;
if(apiFetch && apiFetch.createNonceMiddleware && cfg.nonce){apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));}

function icon(kind){
    var paths={
        brand:[['rect',{x:3,y:3,width:18,height:18,rx:5}],['path',{d:'M8 15.5h5a4 4 0 0 0 4-4V8H9.5A2.5 2.5 0 0 0 7 10.5v0A2.5 2.5 0 0 0 9.5 13H17'}],['circle',{cx:6.5,cy:17.5,r:.7}]],
        ai:[['path',{d:'m12 3 1.3 3.7L17 8l-3.7 1.3L12 13l-1.3-3.7L7 8l3.7-1.3L12 3Z'}],['path',{d:'m18 13 .8 2.2L21 16l-2.2.8L18 19l-.8-2.2L15 16l2.2-.8L18 13Z'}],['path',{d:'M4 19 14.5 8.5'}]],
        auto:[['path',{d:'M20 7v5h-5'}],['path',{d:'M4 17v-5h5'}],['path',{d:'M6.1 9A7 7 0 0 1 18.5 7L20 12'}],['path',{d:'M17.9 15A7 7 0 0 1 5.5 17L4 12'}]],
        doctor:[['path',{d:'M4 13h4l2-5 4 9 2-4h4'}],['path',{d:'M12 21C6.5 17.6 3 14.4 3 9.8A4.8 4.8 0 0 1 12 7a4.8 4.8 0 0 1 9 2.8c0 4.6-3.5 7.8-9 11.2Z'}]],
        elementor:[['rect',{x:4,y:4,width:16,height:16,rx:3}],['path',{d:'M8 8h2v8H8zM12 8h4M12 12h4M12 16h4'}]],
        gutenberg:[['rect',{x:3,y:3,width:8,height:8,rx:1}],['rect',{x:13,y:3,width:8,height:8,rx:1}],['rect',{x:3,y:13,width:8,height:8,rx:1}],['rect',{x:13,y:13,width:8,height:8,rx:1}]],
    };
    var nodes=(paths[kind]||paths.brand).map(function(item,i){return el(item[0],Object.assign({key:i},item[1]));});
    return el('svg',{viewBox:'0 0 24 24',width:24,height:24,fill:'none',stroke:'currentColor',strokeWidth:1.8,strokeLinecap:'round',strokeLinejoin:'round','aria-hidden':true},nodes);
}
function getMeta(){return wp.data.select('core/editor').getEditedPostAttribute('meta')||{};}
function setMeta(key,value){var meta=Object.assign({},getMeta());meta[key]=value;wp.data.dispatch('core/editor').editPost({meta:meta});}
function setMode(mode){setMeta('ncu_render_mode',mode);}
function notice(text,status){
    if(wp.data.dispatch('core/notices')){wp.data.dispatch('core/notices').createNotice(status||'success',text,{type:'snackbar',isDismissible:true});}
}
function download(name,data){var blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});var url=URL.createObjectURL(blob);var a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(url);},1000);}
function fetchJson(path){return apiFetch({path:path});}
function postJson(path,data){return apiFetch({path:path,method:'POST',data:data});}
function readFile(file,callback){var r=new FileReader();r.onload=function(){try{callback(null,JSON.parse(String(r.result||'')));}catch(e){callback(e);}};r.onerror=function(){callback(new Error('Could not read file.'));};r.readAsText(file);}
function applyPackageToEditor(pkg){
    if(pkg.renderer){setMode(pkg.renderer);}
    var ai=pkg.ai_builder||{};
    var map={preset:'ncu_ai_preset',content_width:'ncu_ai_content_width',radius:'ncu_ai_radius',spacing_scale:'ncu_ai_spacing_scale',font_scale:'ncu_ai_font_scale',background:'ncu_ai_background',text_color:'ncu_ai_text_color',accent_color:'ncu_ai_accent_color',hidden_sections:'ncu_ai_hidden_sections',section_order:'ncu_ai_section_order',page_css:'ncu_page_css'};
    Object.keys(map).forEach(function(k){if(Object.prototype.hasOwnProperty.call(ai,k)){setMeta(map[k],ai[k]);}});
}
function applyRepairToEditor(pkg){
    (pkg.actions||[]).forEach(function(a){
        if(!a||!a.type){return;}
        if(a.type==='set_renderer'){setMode(a.value);}
        if(a.type==='set_ai_builder'){applyPackageToEditor({ai_builder:a.value||{}});}
        if(a.type==='set_page_css'){setMeta('ncu_page_css',a.value||'');}
        if(a.type==='clear_page_css'){setMeta('ncu_page_css','');}
        if(a.type==='clear_ai_overrides'){['ncu_ai_preset','ncu_ai_content_width','ncu_ai_radius','ncu_ai_spacing_scale','ncu_ai_font_scale','ncu_ai_background','ncu_ai_text_color','ncu_ai_accent_color','ncu_ai_hidden_sections','ncu_ai_section_order','ncu_page_css'].forEach(function(k){setMeta(k,k==='ncu_ai_content_width'||k==='ncu_ai_radius'?0:'');});}
    });
}
function ModeSelect(){var meta=getMeta();var mode=meta.ncu_render_mode||cfg.globalMode||'auto';return el(c.SelectControl,{label:'Output renderer',value:mode,options:[{label:'Auto Builder (recommended)',value:'auto'},{label:'AI Builder',value:'ai'},{label:'Gutenberg',value:'gutenberg'},{label:'Elementor',value:'elementor'}],onChange:setMode,__nextHasNoMarginBottom:true});}
function StatusBox(props){return el('div',{className:'ncu-editor-status'},el('strong',null,props.title),el('p',null,props.children));}
function FileImportButton(props){return el('label',{className:'components-button is-secondary ncu-file-button'},props.label,el('input',{type:'file',accept:'application/json,.json',style:{display:'none'},onChange:function(e){var file=e.target.files&&e.target.files[0];if(!file){return;}readFile(file,props.onRead);e.target.value='';}}));}

function MainPanel(){
    return el(PluginDocumentSettingPanel,{name:'ncu-output',title:'9Code Styles',icon:icon('brand'),className:'ncu-document-panel'},
        el(ModeSelect),
        el('p',{className:'description'},'Open the 9Code Styles sidebar for page width, spacing, typography, colours and presentation exchange.'),
        el(c.Button,{variant:'secondary',href:cfg.designUrl},'Global Design System'),
        el(c.Button,{variant:'tertiary',href:cfg.buildersUrl},'Builder defaults')
    );
}

function BuilderSidebar(){return el(PluginSidebar,{name:'ncu-builder-control',title:'9 Code Ultra · Builders',icon:icon('brand')},el(c.PanelBody,{title:'Renderer',initialOpen:true},el(ModeSelect),el(StatusBox,{title:'Single source of truth'},'The post remains one WordPress record. These controls only select its presentation path.')),el(c.PanelBody,{title:'Quick destinations',initialOpen:true},el(c.Button,{variant:'secondary',href:cfg.buildersUrl},'Global defaults'),el(c.Button,{variant:'tertiary',href:cfg.dashboardUrl},'9 Code Ultra Dashboard')));}

function AISidebar(){
    var meta=getMeta();
    return el(PluginSidebar,{name:'ncu-ai-builder',title:'AI Builder',icon:icon('ai')},
        el(c.PanelBody,{title:'Per-page presentation',initialOpen:true},
            el(c.Button,{variant:(meta.ncu_render_mode==='ai'?'primary':'secondary'),onClick:function(){setMode('ai');}},'Use AI Builder for this page'),
            el(c.SelectControl,{label:'Design preset',value:meta.ncu_ai_preset||'inherit',options:[{label:'Inherit global design',value:'inherit'},{label:'9code Black & White',value:'neutral'},{label:'Editorial',value:'editorial'},{label:'Compact',value:'compact'},{label:'Executive',value:'executive'},{label:'Learning',value:'learning'},{label:'Newsroom',value:'newsroom'},{label:'Visual',value:'visual'},{label:'Minimal',value:'minimal'},{label:'Technical',value:'technical'},{label:'Dark Premium',value:'dark'}],onChange:function(v){setMeta('ncu_ai_preset',v);}}),
            el(c.TextControl,{label:'Content width (0 = inherit)',type:'number',value:String(meta.ncu_ai_content_width||0),onChange:function(v){setMeta('ncu_ai_content_width',parseInt(v||'0',10)||0);}}),
            el(c.TextControl,{label:'Corner radius (0 = inherit)',type:'number',value:String(meta.ncu_ai_radius||0),onChange:function(v){setMeta('ncu_ai_radius',parseInt(v||'0',10)||0);}}),
            el(c.TextControl,{label:'Spacing scale',type:'number',step:'0.05',value:meta.ncu_ai_spacing_scale||'1',onChange:function(v){setMeta('ncu_ai_spacing_scale',v);}}),
            el(c.TextControl,{label:'Font scale',type:'number',step:'0.05',value:meta.ncu_ai_font_scale||'1',onChange:function(v){setMeta('ncu_ai_font_scale',v);}}),
            el(c.TextControl,{label:'Background override',type:'color',value:meta.ncu_ai_background||'#ffffff',onChange:function(v){setMeta('ncu_ai_background',v);}}),
            el(c.TextControl,{label:'Text override',type:'color',value:meta.ncu_ai_text_color||'#000000',onChange:function(v){setMeta('ncu_ai_text_color',v);}}),
            el(c.TextControl,{label:'Accent override',type:'color',value:meta.ncu_ai_accent_color||'#000000',onChange:function(v){setMeta('ncu_ai_accent_color',v);}}),
            el(c.TextControl,{label:'Hide top-level section indexes',help:'Example: 1,4',value:meta.ncu_ai_hidden_sections||'',onChange:function(v){setMeta('ncu_ai_hidden_sections',v);}}),
            el(c.TextControl,{label:'Top-level section order',help:'Example: 2,0,1. Unlisted sections follow.',value:meta.ncu_ai_section_order||'',onChange:function(v){setMeta('ncu_ai_section_order',v);}}),
            el(c.TextareaControl,{label:'Page-scoped CSS',help:'For small visual corrections. Imports reject executable code and remote CSS URLs.',value:meta.ncu_page_css||'',onChange:function(v){setMeta('ncu_page_css',v);}})
        ),
        el(c.PanelBody,{title:'AI exchange',initialOpen:true},
            el(c.Button,{variant:'secondary',onClick:function(){fetchJson(cfg.restBase+'/ai-export/'+cfg.postId).then(function(data){download((cfg.fileBase||('9code-page-'+cfg.postId))+'-ai-builder.json',data);}).catch(function(e){notice(e.message||'Export failed.','error');});}},'Export AI page package'),
            el(FileImportButton,{label:'Import AI presentation package',onRead:function(err,pkg){if(err){notice('Invalid JSON file.','error');return;}if(!window.confirm('Import this AI presentation package into the current page? Content is not replaced; only presentation controls are accepted.')){return;}postJson(cfg.restBase+'/ai-import/'+cfg.postId,pkg).then(function(){applyPackageToEditor(pkg);notice('AI presentation package imported. Save/update the post.');}).catch(function(e){notice(e.message||'Import failed.','error');});}}),
            el('p',{className:'description'},'The package describes page structure and presentation settings. It is not a second copy of the page data.')
        )
    );
}

function AutoSidebar(){return el(PluginSidebar,{name:'ncu-auto-builder',title:'Auto Builder',icon:icon('auto')},el(c.PanelBody,{title:'Automatic output',initialOpen:true},el(c.Button,{variant:'primary',onClick:function(){setMode('auto');}},'Use Auto Builder'),el(StatusBox,{title:'Recommended default'},'The theme chooses the normal WordPress rendering path and preserves legitimate plugin/content filters. Use another renderer only when you deliberately need it.'),el(c.Button,{variant:'secondary',href:cfg.buildersUrl},'Configure Auto Builder defaults')));}
function GutenbergSidebar(){return el(PluginSidebar,{name:'ncu-gutenberg-builder',title:'Gutenberg Output',icon:icon('gutenberg')},el(c.PanelBody,{title:'Native block output',initialOpen:true},el(c.Button,{variant:'primary',onClick:function(){setMode('gutenberg');}},'Use Gutenberg for this page'),el('p',null,'Useful for temporary notices, coming-soon messages, or when you want the normal block editor output even if another builder exists on this content item.')));}
function ElementorSidebar(){return el(PluginSidebar,{name:'ncu-elementor-builder',title:'Elementor Output',icon:icon('elementor')},el(c.PanelBody,{title:'Elementor',initialOpen:true},el(c.Button,{variant:'primary',onClick:function(){setMode('elementor');}},'Use Elementor for this page'),cfg.elementorActive?el(Fragment,null,el('p',null,'Elementor is available.'),el(c.Button,{variant:'secondary',onClick:function(){setMode('elementor');var d=wp.data.dispatch('core/editor');var promise=d.savePost?d.savePost():null;if(promise&&promise.then){promise.then(function(){window.location.href=cfg.elementorEditUrl;});}else{window.location.href=cfg.elementorEditUrl;}}},'Save & open Elementor')):el(StatusBox,{title:'Elementor is not active'},'Selecting Elementor will not crash the page. 9 Code Ultra falls back safely until Elementor is available.')));}
function DoctorSidebar(){return el(PluginSidebar,{name:'ncu-doctor',title:'9 Code Ultra Doctor',icon:icon('doctor')},el(c.PanelBody,{title:'Diagnose this page',initialOpen:true},el(c.Button,{variant:'primary',onClick:function(){fetchJson(cfg.restBase+'/diagnose/'+cfg.postId).then(function(data){download((cfg.fileBase||('9code-page-'+cfg.postId))+'-doctor.json',data);}).catch(function(e){notice(e.message||'Diagnosis failed.','error');});}},'Download page diagnosis'),el(FileImportButton,{label:'Upload safe repair package',onRead:function(err,pkg){if(err){notice('Invalid JSON file.','error');return;}if(!window.confirm('Apply this safe Doctor repair package to the current page?')){return;}postJson(cfg.restBase+'/repair/'+cfg.postId,pkg).then(function(){applyRepairToEditor(pkg);notice('Repair package applied. Review and update the post.');}).catch(function(e){notice(e.message||'Repair failed.','error');});}}),el(c.Button,{variant:'tertiary',href:cfg.doctorUrl},'Open full Doctor'),el('p',{className:'description'},'Diagnosis maps blocks, shortcodes and content callbacks to their runtime owner where WordPress reflection can resolve them. A PHP error log remains the strongest proof of an exact fatal line.')));}

function StylesSidebar(){
    var meta=getMeta();
    var Range=c.RangeControl||c.TextControl;
    var presetOptions=[{label:'Inherit global design',value:'inherit'},{label:'9Code Black & White',value:'neutral'},{label:'Editorial',value:'editorial'},{label:'Compact',value:'compact'},{label:'Executive',value:'executive'},{label:'Learning',value:'learning'},{label:'Newsroom',value:'newsroom'},{label:'Visual',value:'visual'},{label:'Minimal',value:'minimal'},{label:'Technical',value:'technical'},{label:'Dark Premium',value:'dark'}];
    return el(PluginSidebar,{name:'ncu-styles',title:'9Code Styles',icon:icon('brand')},
        el(c.PanelBody,{title:'Output and style',initialOpen:true},
            el(ModeSelect),
            el(c.Button,{variant:(meta.ncu_render_mode==='gutenberg'?'primary':'secondary'),onClick:function(){setMode('gutenberg');}},'Use Gutenberg output'),
            el(c.SelectControl,{label:'Page design preset',value:meta.ncu_ai_preset||'inherit',options:presetOptions,onChange:function(v){setMeta('ncu_ai_preset',v);}})
        ),
        el(c.PanelBody,{title:'Size and spacing',initialOpen:true},
            el(Range,{label:'Content width',min:0,max:1600,step:20,value:Number(meta.ncu_ai_content_width||0),help:'0 inherits the global design.',onChange:function(v){setMeta('ncu_ai_content_width',Number(v)||0);}}),
            el(Range,{label:'Corner radius',min:0,max:40,step:1,value:Number(meta.ncu_ai_radius||0),help:'0 inherits the global design.',onChange:function(v){setMeta('ncu_ai_radius',Number(v)||0);}}),
            el(Range,{label:'Spacing scale',min:.7,max:1.5,step:.05,value:Number(meta.ncu_ai_spacing_scale||1),onChange:function(v){setMeta('ncu_ai_spacing_scale',String(v));}}),
            el(Range,{label:'Font scale',min:.8,max:1.4,step:.05,value:Number(meta.ncu_ai_font_scale||1),onChange:function(v){setMeta('ncu_ai_font_scale',String(v));}})
        ),
        el(c.PanelBody,{title:'Page colours',initialOpen:false},
            el(c.TextControl,{label:'Background',type:'color',value:meta.ncu_ai_background||'#ffffff',onChange:function(v){setMeta('ncu_ai_background',v);}}),
            el(c.TextControl,{label:'Text',type:'color',value:meta.ncu_ai_text_color||'#000000',onChange:function(v){setMeta('ncu_ai_text_color',v);}}),
            el(c.TextControl,{label:'Accent',type:'color',value:meta.ncu_ai_accent_color||'#000000',onChange:function(v){setMeta('ncu_ai_accent_color',v);}})
        ),
        el(c.PanelBody,{title:'Advanced page style',initialOpen:false},
            el(c.TextareaControl,{label:'Page-scoped CSS',help:'Small page-specific visual corrections only.',value:meta.ncu_page_css||'',onChange:function(v){setMeta('ncu_page_css',v);}}),
            el(c.Button,{variant:'secondary',href:cfg.designUrl},'Global Design System'),
            el(c.Button,{variant:'tertiary',href:cfg.styleAuthorityUrl},'Style Authority'),
            el(c.Button,{variant:'tertiary',href:cfg.buildersUrl},'Builder defaults')
        ),
        el(c.PanelBody,{title:'AI style exchange',initialOpen:false},
            el(c.Button,{variant:'secondary',onClick:function(){fetchJson(cfg.restBase+'/ai-export/'+cfg.postId).then(function(data){download((cfg.fileBase||('9code-page-'+cfg.postId))+'-ai-builder.json',data);}).catch(function(e){notice(e.message||'Export failed.','error');});}},'Export page style package'),
            el(FileImportButton,{label:'Import page style package',onRead:function(err,pkg){if(err){notice('Invalid JSON file.','error');return;}if(!window.confirm('Import this presentation package into the current page?')){return;}postJson(cfg.restBase+'/ai-import/'+cfg.postId,pkg).then(function(){applyPackageToEditor(pkg);notice('Style package imported. Save/update the post.');}).catch(function(e){notice(e.message||'Import failed.','error');});}})
        )
    );
}

registerPlugin('ncu-document-controls',{render:MainPanel,icon:icon('brand')});
if(!window.matchMedia || window.matchMedia('(min-width:1181px)').matches){registerPlugin('ncu-styles-sidebar',{render:StylesSidebar,icon:icon('brand')});}
})(window.wp,window.NCU_EDITOR);
