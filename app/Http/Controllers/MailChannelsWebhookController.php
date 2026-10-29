<?php

namespace App\Http\Controllers;

use App\Webhooks\WebhookVerificationException;
use App\Webhooks\WebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /webhooks/mailchannels: receives batches of delivery events.
 *
 * @see https://docs.mailchannels.com/email-api/webhooks
 */
class MailChannelsWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookVerifier $verifier): JsonResponse
    {
        try {
            $verifier->verify($request);
        } catch (WebhookVerificationException $e) {
            Log::warning('Rejected MailChannels webhook', ['reason' => $e->getMessage()]);

            // 503 asks MailChannels to retry when the failure is on our side.
            return $e->temporary
                ? response()->json(['error' => 'Webhook verification unavailable'], 503)
                : response()->json(['error' => 'Invalid signature'], 401);
        }

        $events = json_decode($request->getContent(), true);
        if (! is_array($events) || ! array_is_list($events)) {
            return response()->json(['error' => 'Body must be a JSON array of events'], 400);
        }

        foreach ($events as $event) {
            if (! is_array($event) || ! isset($event['customer_handle'], $event['event'], $event['timestamp'])) {
                return response()->json(['error' => 'Each event needs customer_handle, event and timestamp'], 400);
            }
        }

        foreach ($events as $event) {
            // Replace with your own handling (update delivery status, suppress bounced addresses, ...).
            Log::info('MailChannels webhook event', [
                'customer_handle' => $event['customer_handle'],
                'event' => $event['event'],
                'timestamp' => $event['timestamp'],
                'message_id' => $event['message_id'] ?? null,
            ]);
        }

        return response()->json(['received' => count($events)]);
    }
}
