<div class="nav-tabs-navigation">
  <div class="nav-tabs-wrapper">
    <ul class="navbar-nav align-middle">
      @if(Auth::check())
        <li class="nav-item">
          <a class="nav-link" href="{{ route('frontend.dashboard.index') }}">
            <i class="fas fa-tachometer-alt"></i>
            <p>@lang('common.dashboard')</p>
          </a>
        </li>
      @endif

      <li class="nav-item">
        <a class="nav-link" href="{{ route('frontend.livemap.index') }}">
          <i class="fas fa-globe"></i>
          <p>@lang('common.livemap')</p>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="{{ route('frontend.pilots.index') }}">
          <i class="fas fa-users"></i>
          <p>{{ trans_choice('common.pilot', 2) }}</p>
        </a>
      </li>

      {{-- Show the module links that don't require being logged in --}}
      @foreach($moduleSvc->getFrontendLinks($logged_in=false) as &$link)
        <li class="nav-item">
          <a class="nav-link" href="{{ url($link['url']) }}">
            <i class="{{ $link['icon'] }}"></i>
            <p>{{ ($link['title']) }}</p>
          </a>
        </li>
      @endforeach

      @foreach($page_links as $page)
        <li class="nav-item">
          <a class="nav-link" href="{{ $page->url }}" target="{{ $page->new_window ? '_blank':'_self' }}">
            <i class="{{ $page['icon'] }}"></i>
            <p>{{ $page['name'] }}</p>
          </a>
        </li>
      @endforeach

      @if(!Auth::check())
         <li class="nav-item">
          <a class="nav-link" href="{{ url('/register') }}">
            <i class="far fa-id-card"></i>
            <p>@lang('common.register')</p>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ url('/login') }}">
            <i class="fas fa-sign-in-alt"></i>
            <p>@lang('common.login')</p>
          </a>
        </li>
      @else
        <li class="nav-item">
          <a class="nav-link" href="{{ route('frontend.flights.index') }}">
            <i class="fab fa-avianex"></i>
            <p>{{ trans_choice('common.flight', 2) }}</p>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('frontend.pireps.index') }}">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>{{ trans_choice('common.pirep', 2) }}</p>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('frontend.downloads.index') }}">
            <i class="fas fa-download"></i>
            <p>{{ trans_choice('common.download', 2) }}</p>
          </a>
        </li>

        {{-- Show the module links for being logged in --}}
        @foreach($moduleSvc->getFrontendLinks($logged_in=true) as &$link)
          <li class="nav-item">
            <a class="nav-link" href="{{ url($link['url']) }}">
              <i class="{{ $link['icon'] }}"></i>
              <p>{{ ($link['title']) }}</p>
            </a>
          </li>
        @endforeach

        <li class="nav-item dropdown ">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button"
             data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
            @if (Auth::user()->avatar == null)
              <img src="{{ Auth::user()->gravatar(38) }}" style="height: 38px; width: 38px;">
            @else
              <img src="{{ Auth::user()->avatar->url }}" style="height: 38px; width: 38px;">
            @endif
          </a>
          <div class="dropdown-menu dropdown-menu-right">

            <a class="dropdown-item" href="{{ route('frontend.profile.index') }}">
              <i class="far fa-user"></i>&nbsp;&nbsp;@lang('common.profile')
            </a>

            @ability('admin', 'admin-access')
            <a class="dropdown-item" href="{{ url('/admin') }}">
              <i class="fas fa-circle-notch"></i>&nbsp;&nbsp;@lang('common.administration')
            </a>
            @endability
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="{{ url('/logout') }}">
              <i class="fas fa-sign-out-alt"></i>&nbsp;&nbsp;@lang('common.logout')
            </a>
          </div>
        </li>
      @endif
        <li class="nav-item dropdown ">
			    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button"
				     data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
				    <img src="{{ public_asset('/assets/img/flags/'. strtoupper(app()->getLocale()) .'.png') }}">
			    </a>
			    <div class="dropdown-menu dropdown-menu-right">
				    @foreach (array_diff(scandir(resource_path('lang')), array('..', '.','vendor')) as $locale)
					    @if ( $locale !== app()->getLocale() )
						    <a class="dropdown-item" href="{{ url()->current() . '/?lang=' . $locale }}" title="">
							    <img src="{{ public_asset('/assets/img/flags/'. strtoupper($locale) .'.png') }}">
							    {{ locale_get_display_language($locale, $locale) }}
						    </a>
					    @endif
				    @endforeach
			    </div>
        </li>
    </ul>
  </div>
</div>
