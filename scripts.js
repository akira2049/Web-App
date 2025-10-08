// scripts.js — tiny helpers for forms
(function(){
  function byId(id){ return document.getElementById(id); }
  // Password confirmation check
  var pw1 = byId('pw1'), pw2 = byId('pw2');
  if(pw1 && pw2){
    function checkMatch(){
      if(pw2.value && pw1.value !== pw2.value){
        pw2.setCustomValidity('Passwords do not match');
      }else{
        pw2.setCustomValidity('');
      }
    }
    pw1.addEventListener('input', checkMatch);
    pw2.addEventListener('input', checkMatch);
  }

  // Generic form submit handler (demo only)
  Array.from(document.querySelectorAll('form')).forEach(function(f){
    f.addEventListener('submit', function(e){
      if(!f.checkValidity()){
        // Let browser show native messages
        return;
      }
      e.preventDefault();
      alert('Form submitted successfully (demo). Hook this up to your backend.');
    });
  });
})();