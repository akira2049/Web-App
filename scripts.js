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

  // scripts.js (LIVE version – no demo submit blocking)

document.addEventListener('DOMContentLoaded', () => {
  const yearSpan = document.getElementById('year');
  if (yearSpan) {
    yearSpan.textContent = new Date().getFullYear();
  }

  const form = document.getElementById('signup-form');
  if (!form) return;

  // OPTIONAL: simple client-side password check (no preventDefault if OK)
  const pw1 = document.getElementById('pw1');
  const pw2 = document.getElementById('pw2');

  form.addEventListener('submit', (e) => {
    // basic front-end check; PHP will still re-validate
    if (pw1 && pw2 && pw1.value !== pw2.value) {
      e.preventDefault();   // only block if clearly invalid
      alert('Passwords do not match.');
      return;
    }

    // IMPORTANT:
    // No "Form submitted successfully (demo)" and NO preventDefault here.
    // Let the browser actually POST to signup.php so PHP runs.
  });
});

})();