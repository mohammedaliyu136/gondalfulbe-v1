<!DOCTYPE html>
@php
    use App\Models\Utility;

    $setting = Utility::settings();
    $company_logo = $setting['company_logo_dark'] ?? '';
    $company_logos = $setting['company_logo_light'] ?? '';
    $company_favicon = $setting['company_favicon'] ?? '';

    $logo = \App\Models\Utility::get_file('uploads/logo/');

    $color = !empty($setting['color']) ? $setting['color'] : 'theme-3';

    if(isset($setting['color_flag']) && $setting['color_flag'] == 'true')
    {
        $themeColor = 'custom-color';
    }
    else {
        $themeColor = $color;
    }

    $company_logo = \App\Models\Utility::GetLogo();
    $SITE_RTL = isset($setting['SITE_RTL']) ? $setting['SITE_RTL'] : 'off';

    $lang = \App::getLocale('lang');
    if ($lang == 'ar' || $lang == 'he') {
        $SITE_RTL = 'on';
    }
    elseif($SITE_RTL == 'on')
    {
        $SITE_RTL = 'on';
    }
    else {
        $SITE_RTL = 'off';
    }

    $metatitle = isset($setting['meta_title']) ? $setting['meta_title'] : '';
    $metsdesc = isset($setting['meta_desc']) ? $setting['meta_desc'] : '';
    $meta_image = \App\Models\Utility::get_file('uploads/meta/');
    $meta_logo = isset($setting['meta_image']) ? $setting['meta_image'] : '';
    $get_cookie = isset($setting['enable_cookie']) ? $setting['enable_cookie'] : '';

@endphp

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ $SITE_RTL == 'on' ? 'rtl' : '' }}">

<head>
    <title>
        {{ Utility::getValByName('title_text') ? Utility::getValByName('title_text') : config('app.name', 'Gondal Fulbe') }}
        - @yield('page-title')</title>

    <meta name="title" content="{{ $metatitle }}">
    <meta name="description" content="{{ $metsdesc }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ env('APP_URL') }}">
    <meta property="og:title" content="{{ $metatitle }}">
    <meta property="og:description" content="{{ $metsdesc }}">
    <meta property="og:image" content="{{ $meta_image . $meta_logo }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ env('APP_URL') }}">
    <meta property="twitter:title" content="{{ $metatitle }}">
    <meta property="twitter:description" content="{{ $metsdesc }}">
    <meta property="twitter:image" content="{{ $meta_image . $meta_logo }}">


    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Dashboard Template Description" />
    <meta name="keywords" content="Dashboard Template" />
    <meta name="author" content="WorkDo" />

    <!-- Favicon icon -->
    <link rel="icon"
        href="{{ $logo . '/' . (isset($company_favicon) && !empty($company_favicon) ? $company_favicon : 'favicon.png')  . '?' . time() }}"
        type="image/x-icon" />

    <!-- font css -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}">

    <!-- vendor css -->

    @if ($SITE_RTL == 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style-rtl.css') }}" id="main-style-link">
    @endif

    @if ($setting['cust_darklayout'] == 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style-dark.css') }}">
    @endif

    @if ($SITE_RTL != 'on' && $setting['cust_darklayout'] != 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link">
    @endif


    @if ($SITE_RTL == 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/custom-auth-rtl.css') }}" id="main-style-link">
        @else
        <link rel="stylesheet" href="{{ asset('assets/css/custom-auth.css') }}" id="main-style-link">
    @endif

    @if ($setting['cust_darklayout'] == 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/custom-auth-dark.css') }}" id="main-style-link">
    @endif

    <style>
        :root {
            --color-customColor: <?= $color ?>;
        }
    </style>

    <link rel="stylesheet" href="{{ asset('css/custom-color.css') }}">

    <link rel="stylesheet" href="{{ asset('assets/css/customizer.css') }}">

    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

</head>

<body class="{{ $themeColor }}">
    <div class="custom-login">
        <div class="login-bg-img">
            @include('partials.admin.farm-svg')
        </div>
        <style>
            .custom-login {
                position: relative;
                overflow: hidden;
                background: #e0f2fe !important; /* Sky blue base */
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-bg-img {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 0;
            }
            .farm-svg {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            /* SVG Animations */
            .sun-glow {
                animation: sun-pulse 4s ease-in-out infinite alternate;
            }
            @keyframes sun-pulse {
                from { r: 50; fill: #fef08a; opacity: 0.8; }
                to { r: 60; fill: #fde047; opacity: 1; }
            }

            .cloud {
                animation: cloud-float 20s linear infinite;
            }
            .cloud-1 { animation-duration: 40s; opacity: 0.8; }
            .cloud-2 { animation-duration: 60s; animation-delay: -10s; opacity: 0.6; }

            @keyframes cloud-float {
                from { transform: translateX(-200px); }
                to { transform: translateX(1200px); }
            }

            .tail {
                transform-origin: -40px 0;
                animation: tail-wag 2s ease-in-out infinite alternate;
            }
            @keyframes tail-wag {
                from { transform: rotate(-5deg); }
                to { transform: rotate(15deg); }
            }

            .cow {
                animation: cow-graze 10s ease-in-out infinite alternate;
            }
            @keyframes cow-graze {
                from { transform: translate(var(--x, 0px), var(--y, 0px)) rotate(0deg); }
                to { transform: translate(var(--x, 0px), var(--y, 0px)) rotate(1deg); } 
            }
            .cow-1 { --x: 200px; --y: 500px; }
            .cow-2 { --x: 450px; --y: 530px; }

            .custom-login-inner {
                position: relative;
                z-index: 1;
                width: 100%;
            }
            .bg-login {
                display: none;
            }
            .custom-row {
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .custom-row .card {
                background: rgba(255, 255, 255, 0.85) !important;
                backdrop-filter: blur(12px) !important;
                border: 1px solid rgba(255, 255, 255, 0.4) !important;
                box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15) !important;
                max-width: 450px;
                width: 100%;
                border-radius: 16px !important;
            }
        </style>
        <div class="custom-login-inner">
            <main class="custom-wrapper">
                <div class="custom-row">
                    <div class="card">
                        @yield('content')
                    </div>
                </div>
            </main>
            <footer>
                <div class="auth-footer">
                    <div class="container">
                        <div class="row">
                            <div class="col-12">
                                <span>&copy; {{ date('Y') }}
                                    {{ App\Models\Utility::getValByName('footer_text') ? App\Models\Utility::getValByName('footer_text') : config('app.name', 'Storego Saas') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    @if ($get_cookie == 'on')
        @include('layouts.cookie_consent')
    @endif

    <!-- [ auth-signup ] end -->

    <!-- Required Js -->
    <script src="{{ asset('assets/js/vendor-all.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/feather.min.js') }}"></script>
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="{{ asset('js/custom.js') }}"></script>


    <script>
        feather.replace();
    </script>

    @if (\App\Models\Utility::getValByName('cust_darklayout') == 'on')
        <style>
            .g-recaptcha {
                filter: invert(1) hue-rotate(180deg) !important;
            }
        </style>
    @endif


    <script>
        feather.replace();
        var pctoggle = document.querySelector("#pct-toggler");
        if (pctoggle) {
            pctoggle.addEventListener("click", function() {
                if (
                    !document.querySelector(".pct-customizer").classList.contains("active")
                ) {
                    document.querySelector(".pct-customizer").classList.add("active");
                } else {
                    document.querySelector(".pct-customizer").classList.remove("active");
                }
            });
        }

        var themescolors = document.querySelectorAll(".themes-color > a");
        for (var h = 0; h < themescolors.length; h++) {
            var c = themescolors[h];

            c.addEventListener("click", function(event) {
                var targetElement = event.target;
                if (targetElement.tagName == "SPAN") {
                    targetElement = targetElement.parentNode;
                }
                var temp = targetElement.getAttribute("data-value");
                removeClassByPrefix(document.querySelector("body"), "theme-");
                document.querySelector("body").classList.add(temp);
            });
        }
        function removeClassByPrefix(node, prefix) {
            for (let i = 0; i < node.classList.length; i++) {
                let value = node.classList[i];
                if (value.startsWith(prefix)) {
                    node.classList.remove(value);
                }
            }
        }
    </script>
    @stack('custom-scripts')

</body>

</html>
