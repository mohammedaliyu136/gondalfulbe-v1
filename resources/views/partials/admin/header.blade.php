@php
    $users=\Auth::user();
    $profile=\App\Models\Utility::get_file('uploads/avatar/');
    $languages=\App\Models\Utility::languages();

    $lang = isset($users->lang)?$users->lang:'en';
    if ($lang == null) {
        $lang = 'en';
    }
    $LangName = cache()->remember('full_language_data_' . $lang, now()->addHours(24), function () use ($lang) {
    return \App\Models\Language::languageData($lang);
    });

    $setting = \App\Models\Utility::settings();

    $unseenCounter=App\Models\ChMessage::where('to_id', Auth::user()->id)->where('seen', 0)->count();
@endphp
@if (isset($setting['cust_theme_bg']) && $setting['cust_theme_bg'] == 'on')
    <header class="dash-header transprent-bg gf-topbar-shell">
@else
    <header class="dash-header gf-topbar-shell">
@endif
    <style>
        .gf-topbar-shell .header-wrapper {
            min-height: 70px;
        }

        .gf-topbar {
            min-height: 70px;
            background: #fff;
            width: 100%;
        }

        .gf-topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0;
            width: 100%;
        }

        .gf-topbar-left,
        .gf-topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .gf-topbar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0.65rem 0.9rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            color: #111827;
            text-decoration: none;
            gap: 8px;
            white-space: nowrap;
        }

        .gf-topbar-btn:hover,
        .gf-topbar-btn:focus,
        .gf-topbar-btn:active {
            background: #f8fafc;
            color: #111827;
            text-decoration: none;
        }

        .gf-icon-btn {
            min-width: 44px;
            padding-left: 0.8rem;
            padding-right: 0.8rem;
        }

        .gf-topbar-avatar {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #0caf60;
        }

        .gf-topbar-menu {
            min-width: 220px;
            border-radius: 10px;
            border: 0;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
            padding: 6px 0;
        }

        .gf-topbar-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .gf-topbar-menu .dropdown-item.active,
        .gf-topbar-menu .dropdown-item:active {
            background-color: #eff0f2;
            color: #0caf60;
        }

        .gf-topbar-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(35%, -35%);
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            font-size: 10px;
            line-height: 18px;
            padding: 0 4px;
        }

        @media (max-width: 767.98px) {
            .gf-topbar-inner {
                gap: 10px;
            }

            .gf-topbar-left,
            .gf-topbar-right {
                gap: 8px;
            }
        }
    </style>

    <div class="header-wrapper">
        <div class="gf-topbar">
            <div class="container-fluid" style="max-width:none; width:100%; padding-left:0; padding-right:0;">
                <div class="gf-topbar-inner">
                    <div class="gf-topbar-left">
                        <div class="mob-hamburger" style="display:none;">
                            <a href="#!" id="mobile-collapse" class="gf-topbar-btn gf-icon-btn">
                                <div class="hamburger hamburger--arrowturn">
                                    <div class="hamburger-box">
                                        <div class="hamburger-inner"></div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="dropdown">
                            <a class="gf-topbar-btn dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                                <img src="{{ !empty(\Auth::user()->avatar) ? $profile . \Auth::user()->avatar :  $profile.'avatar.png'}}" class="gf-topbar-avatar" alt="{{ \Auth::user()->name }}">
                                <span class="hide-mob">{{__('Hi, ')}}{{\Auth::user()->name }}!</span>
                            </a>
                            <div class="dropdown-menu gf-topbar-menu dropdown-menu-start">
                                <a href="{{route('profile')}}" class="dropdown-item">
                                    <i class="ti ti-user text-dark"></i><span>{{__('Profile')}}</span>
                                </a>

                                <a href="{{route('2fa.setup')}}" class="dropdown-item">
                                    <i class="ti ti-shield text-dark"></i><span>{{__('2FA Setting')}}</span>
                                </a>

                                <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('frm-logout').submit();" class="dropdown-item">
                                    <i class="ti ti-power text-dark"></i><span>{{__('Logout')}}</span>
                                </a>

                                <form id="frm-logout" action="{{ route('logout') }}" method="POST" class="d-none">
                                    {{ csrf_field() }}
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="gf-topbar-right">
                        @if(\Auth::user()->type == 'company' )
                            @impersonating($guard = null)
                            <div>
                                <a class="btn btn-danger btn-sm" href="{{ route('exit.company') }}"><i class="ti ti-ban"></i>
                                    {{ __('Exit Company Login') }}
                                </a>
                            </div>
                            @endImpersonating
                        @endif

                        @if( \Auth::user()->type !='client' && \Auth::user()->type !='super admin' )
                            <div>
                                <a class="gf-topbar-btn gf-icon-btn position-relative" href="{{ url('chats') }}" aria-haspopup="false" aria-expanded="false">
                                    <i class="ti ti-message-circle"></i>
                                    <span class="bg-danger gf-topbar-badge message-toggle-msg message-counter custom_messanger_counter beep">{{ $unseenCounter }}<span class="sr-only"></span></span>
                                </a>
                            </div>
                        @endif

                        <div class="dropdown">
                            <a class="gf-topbar-btn dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                                <i class="ti ti-world"></i>
                                <span class="hide-mob">{{ucfirst($LangName->full_name)}}</span>
                            </a>
                            <div class="dropdown-menu gf-topbar-menu dropdown-menu-end">
                                @foreach ($languages as $code => $language)
                                    <a href="{{ route('change.language', $code) }}" class="dropdown-item {{ $lang == $code ? 'active' : '' }}">
                                        <span>{{ucFirst($language)}}</span>
                                    </a>
                                @endforeach

                                @if(\Auth::user()->type=='super admin')
                                    <a data-url="{{ route('create.language') }}" class="dropdown-item text-primary" data-ajax-popup="true" data-title="{{__('Create New Language')}}" style="cursor: pointer">
                                        {{ __('Create Language') }}
                                    </a>
                                    <a class="dropdown-item text-primary" href="{{route('manage.language',[isset($lang)?$lang:'english'])}}">{{ __('Manage Language') }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const collapseButton = document.getElementById('mobile-collapse');

            if (!collapseButton) {
                return;
            }

            collapseButton.addEventListener('click', function (event) {
                if (window.innerWidth <= 1024) {
                    return;
                }

                event.preventDefault();
                document.querySelectorAll('.dash-menu-overlay').forEach(function (overlay) {
                    overlay.remove();
                });
                document.querySelector('.dash-sidebar')?.classList.remove('mob-sidebar-active');
                document.body.classList.toggle('minimenu');
            });
        });
    </script>
</header>
