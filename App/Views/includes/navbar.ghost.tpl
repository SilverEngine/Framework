<nav id="sidebar-wrapper">
  <ul class="sidebar-nav">
    <li class="sidebar-brand">
      <a class="js-scroll-trigger" href="{{ route('home') }}">Your name</a>
    </li>
    <li class="sidebar-nav-item">
      <a class="js-scroll-trigger" href="{{ route('home') }}#page-top">{{ trans('navbar.home') }}</a>
    </li>
    <li class="sidebar-nav-item">
      <a class="js-scroll-trigger" href="{{ route('home') }}#about">{{ trans('navbar.about') }}</a>
    </li>
    <li class="sidebar-nav-item">
      <a class="js-scroll-trigger" href="{{ url('/blog') }}">{{ trans('navbar.blog') }}</a>
    </li>
    <li class="sidebar-nav-item">
      <a class="js-scroll-trigger" href="{{ route('home') }}#services">{{ trans('navbar.services') }}</a>
    </li>
    <li class="sidebar-nav-item">
      <a class="js-scroll-trigger" href="{{ route('home') }}#portfolio">{{ trans('navbar.portfolio') }}</a>
    </li>
    <li class="sidebar-nav-item">
      <a class="js-scroll-trigger" href="{{ route('home') }}#contact">{{ trans('navbar.contact') }}</a>
    </li>
  </ul>
</nav>
