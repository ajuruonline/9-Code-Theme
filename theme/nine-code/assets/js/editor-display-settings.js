(function(wp){
'use strict';
if(!wp||!wp.plugins||!wp.editPost||!wp.element||!wp.components||!wp.data){return;}
var el=wp.element.createElement;
var Panel=wp.editPost.PluginDocumentSettingPanel;
var Select=wp.components.SelectControl;
var Fragment=wp.element.Fragment;
var useSelect=wp.data.useSelect;
var useDispatch=wp.data.useDispatch;
var __=wp.i18n.__;

var visibilityOptions=[
  {label:__('Site default','nine-code-ultra'),value:'inherit'},
  {label:__('Show','nine-code-ultra'),value:'show'},
  {label:__('Hide','nine-code-ultra'),value:'hide'}
];
function setting(label,key,meta,setMeta){
  return el(Select,{label:label,value:meta[key]||'inherit',options:visibilityOptions,onChange:function(v){var next={};next[key]=v;setMeta(next);}});
}
function DisplayPanel(){
  var state=useSelect(function(select){
    var ed=select('core/editor');
    return {meta:ed.getEditedPostAttribute('meta')||{},postType:ed.getCurrentPostType()};
  },[]);
  var dispatch=useDispatch('core/editor');
  var setMeta=function(patch){dispatch.editPost({meta:Object.assign({},state.meta,patch)});};
  if(['post','page'].indexOf(state.postType)===-1){return null;}
  var controls=[];
  controls.push(el(Select,{key:'owner',label:__('Presentation owner','nine-code-ultra'),value:state.meta._ncu_presentation_owner||'auto',options:[
    {label:__('Automatic','nine-code-ultra'),value:'auto'},
    {label:__('9Code Theme','nine-code-ultra'),value:'theme'},
    {label:__('Plugin / custom presentation','nine-code-ultra'),value:'plugin'}
  ],help:__('Plugin / custom suppresses Theme title, meta, category line, featured image, breadcrumbs, tags and navigation unless an item below is explicitly set to Show.','nine-code-ultra'),onChange:function(v){setMeta({_ncu_presentation_owner:v});}}));
  controls.push(setting(__('Title','nine-code-ultra'),'_ncu_display_title',state.meta,setMeta));
  if(state.postType==='post'){
    controls.push(setting(__('Date / author meta','nine-code-ultra'),'_ncu_display_meta',state.meta,setMeta));
    controls.push(setting(__('Category line','nine-code-ultra'),'_ncu_display_taxonomy',state.meta,setMeta));
  }
  controls.push(setting(__('Featured image on public page','nine-code-ultra'),'_ncu_display_featured',state.meta,setMeta));
  controls.push(setting(__('Breadcrumbs','nine-code-ultra'),'_ncu_display_breadcrumbs',state.meta,setMeta));
  if(state.postType==='post'){
    controls.push(setting(__('Tags','nine-code-ultra'),'_ncu_display_tags',state.meta,setMeta));
    controls.push(setting(__('Previous / next navigation','nine-code-ultra'),'_ncu_display_navigation',state.meta,setMeta));
  }
  controls.push(setting(__('Comments','nine-code-ultra'),'_ncu_display_comments',state.meta,setMeta));
  controls.push(el(Select,{key:'width',label:__('Content width','nine-code-ultra'),value:state.meta._ncu_content_width||'default',options:[
    {label:__('Theme default','nine-code-ultra'),value:'default'},
    {label:__('Reading width','nine-code-ultra'),value:'reading'},
    {label:__('Wide','nine-code-ultra'),value:'wide'},
    {label:__('Full width','nine-code-ultra'),value:'full'}
  ],onChange:function(v){setMeta({_ncu_content_width:v});}}));
  return el(Panel,{name:'ncu-display-settings',title:__('9Code Display','nine-code-ultra'),className:'ncu-display-settings'},el(Fragment,null,controls));
}
wp.plugins.registerPlugin('ncu-display-settings',{render:DisplayPanel,icon:'visibility'});
})(window.wp);
