<?php
// sendWhatsApp.php — Send WhatsApp messages via Twilio
require_once __DIR__ . '/vendor/autoload.php'; // if you installed Twilio via composer

use Twilio\Rest\Client;

/**
 * sendWhatsApp()
 * Send WhatsApp message using Twilio API
 * @param string $to Phone number (10-digit Indian number)
 * @param string $message Message body
 * @return bool
 */
function sendWhatsApp($to, $message) {
    // Twilio credentials (replace with your own)
    $sid = 'YOUR_TWILIO_SID';
    $token = 'YOUR_TWILIO_AUTH_TOKEN';
    $from = 'whatsapp:+14155238886'; // Twilio Sandbox number

    // Convert number to international format for WhatsApp (India example)
    if (!str_starts_with($to, '+')) {
        $to = 'whatsapp:+91' . preg_replace('/\D/', '', $to);
    } else {
        $to = 'whatsapp:' . $to;
    }

    try {
        $client = new Client($sid, $token);
        $client->messages->create($to, [
            'from' => $from,
            'body' => $message
        ]);
        return true;
    } catch (Exception $e) {
        error_log("WhatsApp Error: " . $e->getMessage());
        return false;
    }
}
?>
