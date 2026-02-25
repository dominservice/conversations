@php
    $panelTheme = config('conversations.ui.theme', 'bootstrap');
    $viewName = 'conversations::panel.themes.' . $panelTheme;
@endphp

@includeIf($viewName, get_defined_vars())

@unless(view()->exists($viewName))
    @include('conversations::panel.themes.bootstrap', get_defined_vars())
@endunless

