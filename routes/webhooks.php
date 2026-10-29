<?php

use App\Http\Controllers\MailChannelsWebhookController;
use Illuminate\Support\Facades\Route;

// Loaded with the stateless "api" middleware group (no session, no CSRF):
// requests are authenticated by their MailChannels signature instead.
Route::post('/webhooks/mailchannels', MailChannelsWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.mailchannels');
