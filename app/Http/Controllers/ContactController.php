<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // No line breaks: the name is used in the Subject and Reply-To headers.
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Mail::to(config('mail.contact_to'))
            ->send(new ContactMessageMail($data['name'], $data['email'], $data['message']));

        return redirect()->route('contact')->with('status', 'Thanks! Your message has been sent.');
    }
}
