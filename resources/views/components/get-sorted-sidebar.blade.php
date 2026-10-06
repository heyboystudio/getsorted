@props(['tradeLinks', 'chat' => false])

<aside class="gs-side" aria-label="Main navigation">
  <div class="gs-side-head"><a class="gs-logo" href="{{ route('home') }}" wire:navigate.hover aria-label="Get Sorted home"><span class="gs-logo-mark"><i class="ph-bold ph-check"></i></span><span class="gs-lbl">Get Sorted</span></a><button class="gs-side-toggle" type="button" @click="toggle()" :aria-expanded="(!collapsed).toString()" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" aria-label="Collapse sidebar"><i class="ph ph-sidebar-simple"></i></button></div>
  @if ($chat)
  <button class="gs-nav-btn gs-primary" type="button" wire:click="restart" wire:confirm="{{ __('Start over? This clears the chat.') }}" title="{{ __('New chat') }}"><i class="ph ph-plus" aria-hidden="true"></i><span class="gs-lbl">{{ __('New chat') }}</span></button>
  @else
  <a class="gs-nav-btn gs-primary" href="{{ route('book') }}" wire:navigate.hover title="New job"><i class="ph ph-plus"></i><span class="gs-lbl">New job</span></a>
  @endif
  @foreach ($tradeLinks as $tradeLink)
  <a class="gs-nav-btn" href="{{ $tradeLink['url'] }}" wire:navigate.hover title="{{ $tradeLink['name'] }}"><i class="ph ph-{{ $tradeLink['icon'] }}"></i><span class="gs-lbl">{{ $tradeLink['name'] }}</span></a>
  @endforeach
  <p class="gs-side-label">Explore</p>
  <a class="gs-nav-btn" href="{{ route('customers') }}" wire:navigate.hover title="For customers"><i class="ph ph-house-line"></i><span class="gs-lbl">For customers</span></a>
  <a class="gs-nav-btn" href="{{ route('pros.join') }}" wire:navigate.hover title="For pros"><i class="ph ph-hard-hat"></i><span class="gs-lbl">For pros</span><span class="gs-badge">Free</span></a>
  <a class="gs-nav-btn" href="{{ route('about') }}" wire:navigate.hover title="About"><i class="ph ph-sparkle"></i><span class="gs-lbl">About</span></a>
  <a class="gs-nav-btn" href="{{ route('contact') }}" wire:navigate.hover title="Contact"><i class="ph ph-chat-circle"></i><span class="gs-lbl">Contact</span></a>
  <div class="gs-side-foot">
    @auth
    <a class="gs-btn gs-btn-white" href="{{ route('account.home') }}" wire:navigate.hover title="My account"><i class="ph ph-user"></i><span class="gs-lbl">My account</span></a>
    @else
    <a class="gs-btn" href="{{ route('login') }}" wire:navigate.hover title="Sign in"><i class="ph ph-sign-in"></i><span class="gs-lbl">Sign in</span></a>
    <a class="gs-btn gs-btn-white" href="{{ route('register') }}" wire:navigate.hover title="Create account"><i class="ph ph-user-plus"></i><span class="gs-lbl">Create account</span></a>
    @endauth
  </div>
</aside>
