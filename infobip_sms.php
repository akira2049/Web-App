<?php
// infobip_sms.php

function infobip_send_sms($to, $text) {
    // ==== REPLACE WITH YOUR INFOBIP CREDENTIALS ====
    $baseUrl = "4ed4v6.api.infobip.com";   // e.g. https://abcd12.api.infobip.com
    $apiKey  = "24817f74a2afaa722d8748347118e2a1-87b1545b-3fb1-4a53-aa43-37152f9f40ac";           // e.g. abcdef123456...
    // ===============================================

    $endpoint = $baseUrl . "/sms/2/text/advanced";

    $payload = [
        "messages" => [
            [
                "from" => "BankPortal",   // Or your approved sender ID
                "destinations" => [
                    ["to" => $to]
                ],
                "text" => $text
            ]
        ]
    ];

    $jsonPayload = json_encode($payload);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: App " . $apiKey,
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS     => $jsonPayload,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error: " . $error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        "http_code" => $httpCode,
        "body"      => json_decode($response, true),
    ];
}
