<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Libraries\Google2FA;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;

class Google2FAController extends Controller
{
    /**
     * Show the 2FA setup page with QR code.
     */
    public function showSetupForm()
    {
        $user = Auth::user();
        
        $qrCodeUrl = '';
        $secret = '';

        // Generate a new secret if not exists and not enabled
        if (!$user->google2fa_enabled) {
            if (empty($user->google2fa_secret)) {
                $user->google2fa_secret = Google2FA::generateSecret();
                $user->save();
            }
            $qrCodeUrl = Google2FA::getQRCodeText($user->email, $user->google2fa_secret);
            $secret = $user->google2fa_secret;
        }

        return view('auth.2fa.setup', [
            'user' => $user,
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret
        ]);
    }

    /**
     * Activate 2FA after verifying the first code.
     */
    public function activate(Request $request)
    {
        $request->validate([
            'one_time_password' => 'required|numeric|digits:6',
        ]);

        $user = Auth::user();

        if (Google2FA::verifyKey($user->google2fa_secret, $request->one_time_password)) {
            $user->google2fa_enabled = true;
            $user->save();
            
            // Mark as verified for this session
            session(['2fa_verified' => true]);

            return redirect()->to(url(RouteServiceProvider::HOME))->with('success', '2FA has been enabled successfully.');
        }

        return redirect()->back()->with('error', 'Invalid verification code. Please try again.');
    }

    /**
     * Show the 2FA verification form during login.
     */
    public function showVerifyForm()
    {
        if (session('2fa_verified')) {
            return redirect()->to(url(RouteServiceProvider::HOME));
        }

        return view('auth.2fa.verify');
    }

    /**
     * Verify the 2FA code during login.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'one_time_password' => 'required|numeric|digits:6',
        ]);

        $user = Auth::user();

        if (Google2FA::verifyKey($user->google2fa_secret, $request->one_time_password)) {
            session(['2fa_verified' => true]);
            return redirect()->intended(url(RouteServiceProvider::HOME));
        }

        return redirect()->back()->with('error', 'Invalid verification code.');
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request)
    {
        $user = Auth::user();
        $user->google2fa_enabled = false;
        $user->google2fa_secret = null;
        $user->save();

        session(['2fa_verified' => false]);

        return redirect()->back()->with('success', '2FA has been disabled.');
    }
}
