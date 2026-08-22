<button type="button" id="theme-toggle" class="btn btn-ghost btn-sm" aria-label="Toggle dark mode" onclick="
  (function() {
    var d = document.documentElement;
    var isDark = d.classList.contains('dark');
    if (isDark) { d.classList.remove('dark'); localStorage.setItem('theme','light'); }
    else { d.classList.add('dark'); localStorage.setItem('theme','dark'); }
  })();
">
  <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
</button>
