{{ extends('layouts.master') }}

#set[content]

{{ include('includes.navbar') }}

<!-- Header -->
{{ include('includes.banner') }}

<!-- About -->
{{ include('includes.about') }}

<!-- Services -->
{{ include('includes.service') }}

<!-- Callout -->
{{ include('includes.callout') }}

<!-- Portfolio -->
{{ component('portfolio') }}

<!-- Call to Action -->
{{ include('includes.callToAction') }}

<!-- Map -->
{{ component('map') }}


#end
