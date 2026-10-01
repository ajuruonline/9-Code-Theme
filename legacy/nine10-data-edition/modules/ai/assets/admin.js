document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('form[data-nine-ai-confirm]').forEach(function(form){
    form.addEventListener('submit',function(e){
      var message=form.getAttribute('data-nine-ai-confirm')||'Continue?';
      if(!window.confirm(message)){e.preventDefault();}
    });
  });
});
