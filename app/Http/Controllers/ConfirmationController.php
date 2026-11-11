<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConfirmationController extends Controller
{
    public function __invoke(Request $request)
    {
        $confirmation = $request->session()->get('confirmation');

        if (! $confirmation) {
            return redirect()->route('dashboard');
        }

        return view('confirmation', compact('confirmation'));
    }
}
