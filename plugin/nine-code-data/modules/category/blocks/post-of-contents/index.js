(function(blocks, element, components, blockEditor){
  'use strict';
  var el=element.createElement, InspectorControls=blockEditor.InspectorControls;
  var PanelBody=components.PanelBody, ToggleControl=components.ToggleControl, SelectControl=components.SelectControl, TextControl=components.TextControl, RangeControl=components.RangeControl, Placeholder=components.Placeholder;
  blocks.registerBlockType('ninecm/post-of-contents',{
    edit:function(p){
      var a=p.attributes,set=p.setAttributes,types=Array.isArray(a.postTypes)?a.postTypes:[];
      var useSelect=window.wp.data&&window.wp.data.useSelect;
      var discovered=useSelect?useSelect(function(select){var core=select('core');return {postTypes:core.getPostTypes({per_page:100})||[],taxonomies:core.getTaxonomies({per_page:100})||[]};},[]):{postTypes:[],taxonomies:[]};
      var taxList=(discovered.taxonomies||[]).filter(function(t){return (!t.visibility||t.visibility.public!==false);});
      var activeTax=taxList.filter(function(t){return t.slug===a.taxonomy;})[0]||null;
      var allowed=activeTax&&Array.isArray(activeTax.types)?activeTax.types:null;
      var typeList=(discovered.postTypes||[]).filter(function(t){return t.slug!=='attachment'&&t.slug!=='nav_menu_item'&&t.viewable!==false&&(!allowed||allowed.indexOf(t.slug)>-1);});
      function setType(type,on){var next=types.filter(function(x){return x!==type;});if(on)next.push(type);set({postTypes:next.length?next:[typeList[0]&&typeList[0].slug||'page']});}
      return el(element.Fragment,{},
        el(InspectorControls,{},
          el(PanelBody,{title:'Content',initialOpen:true},
            taxList.length?el(SelectControl,{label:'Taxonomy',value:a.taxonomy,options:taxList.map(function(t){return {label:t.name||t.slug,value:t.slug};}),onChange:function(v){var chosen=taxList.filter(function(t){return t.slug===v;})[0],valid=chosen&&Array.isArray(chosen.types)?types.filter(function(x){return chosen.types.indexOf(x)>-1;}):types;set({taxonomy:v||'category',root:0,postTypes:valid.length?valid:[chosen&&Array.isArray(chosen.types)&&chosen.types[0]||'page']});}}):el(TextControl,{label:'Taxonomy',value:a.taxonomy,onChange:function(v){set({taxonomy:v||'category'});}}),
            activeTax&&activeTax.hierarchical?el(TextControl,{label:'Root term ID (0 = all)',type:'number',value:a.root,onChange:function(v){set({root:parseInt(v||0,10)});}}):null,
            activeTax&&activeTax.hierarchical?el(RangeControl,{label:'Hierarchy depth (0 = all)',value:a.depth,min:0,max:12,onChange:function(v){set({depth:v});}}):null,
            typeList.length?el('div',{className:'ninecm-block-type-list'},typeList.map(function(t){return el(ToggleControl,{key:t.slug,label:'Include '+(t.name||t.slug),checked:types.indexOf(t.slug)>-1,onChange:function(v){setType(t.slug,v);}});})):el('p',{},'Loading compatible content types…'),
            el(ToggleControl,{label:'Hide empty terms',checked:a.hideEmpty,onChange:function(v){set({hideEmpty:v});}}),
            el(TextControl,{label:'Items per term (0 = all)',type:'number',value:a.postsPerCategory,onChange:function(v){set({postsPerCategory:Math.max(0,parseInt(v||0,10))});}}),
            el(ToggleControl,{label:'Show item counts',checked:a.showCounts,onChange:function(v){set({showCounts:v});}}),
            el(ToggleControl,{label:'Show each page/post only once',checked:a.deduplicate,onChange:function(v){set({deduplicate:v});}})
          ),
          el(PanelBody,{title:'Search & Dropdowns',initialOpen:false},
            el(ToggleControl,{label:'Dropdown / collapsible',checked:a.collapsible,onChange:function(v){set({collapsible:v});}}),
            a.collapsible?el(ToggleControl,{label:'Start open',checked:a.initiallyOpen,onChange:function(v){set({initiallyOpen:v});}}):null,
            el(ToggleControl,{label:'Search',checked:a.showSearch,onChange:function(v){set({showSearch:v});}}),
            el(ToggleControl,{label:'Term filter',checked:a.showFilter,onChange:function(v){set({showFilter:v});}}),
            el(TextControl,{label:'Empty/search message',value:a.emptyMessage,onChange:function(v){set({emptyMessage:v});}})
          ),
          el(PanelBody,{title:'Ordering & Style',initialOpen:false},
            el(SelectControl,{label:'Term order by',value:a.termOrderby,options:[{label:'Name',value:'name'},{label:'Slug',value:'slug'},{label:'ID',value:'term_id'},{label:'Published count',value:'count'},{label:'9 Manual order',value:'ninecm_order'}],onChange:function(v){set({termOrderby:v});}}),
            el(SelectControl,{label:'Term order',value:a.termOrder,options:[{label:'Ascending',value:'ASC'},{label:'Descending',value:'DESC'}],onChange:function(v){set({termOrder:v});}}),
            el(SelectControl,{label:'Page/post order by',value:a.postOrderby,options:[{label:'Title',value:'title'},{label:'Published date',value:'date'},{label:'Modified date',value:'modified'},{label:'Menu order',value:'menu_order'},{label:'ID',value:'ID'}],onChange:function(v){set({postOrderby:v});}}),
            el(SelectControl,{label:'Page/post order',value:a.postOrder,options:[{label:'Ascending',value:'ASC'},{label:'Descending',value:'DESC'}],onChange:function(v){set({postOrder:v});}}),
            el(SelectControl,{label:'Marker',value:a.marker,options:[{label:'Numbering',value:'number'},{label:'Bullets',value:'bullet'},{label:'Icon',value:'icon'},{label:'None',value:'none'}],onChange:function(v){set({marker:v});}}),
            a.marker==='icon'?el(TextControl,{label:'Text icon',value:a.icon,onChange:function(v){set({icon:v||'›'});}}):null,
            el(SelectControl,{label:'Style',value:a.style,options:[{label:'Clean',value:'clean'},{label:'Compact',value:'compact'},{label:'Bordered',value:'bordered'},{label:'Academic',value:'academic'},{label:'Minimal',value:'minimal'}],onChange:function(v){set({style:v});}})
          )
        ),
        el(Placeholder,{icon:'networking',label:'9 Post of Contents'},
          el('p',{},'Dynamic front-end directory: taxonomy terms → content items. Hierarchical taxonomies retain their tree; tags render flat. Text only.'),
          el('strong',{},'Content: '+(types.join(', ')||'page')+' · Style: '+a.style+' · Marker: '+a.marker)
        )
      );
    },
    save:function(){return null;}
  });
})(window.wp.blocks,window.wp.element,window.wp.components,window.wp.blockEditor);
