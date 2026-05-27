<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SilverEngine</title>
    <script>
      // Apply theme before paint to avoid flicker. Mirrors logic in Welcome.vue.
      (function () {
        try {
          var stored = localStorage.getItem('silverengine-theme');
          var choice = (stored === 'light' || stored === 'dark') ? stored : 'auto';
          var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
          var dark = choice === 'dark' || (choice === 'auto' && prefersDark);
          if (dark) document.documentElement.classList.add('dark');
        } catch (e) {}
      })();
    </script>
    {{ vite() }}
</head>
<body class="antialiased">
    {{ wisp() }}
</body>
</html>
