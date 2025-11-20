<?php
// infobip_sms.php

function infobip_send_sms($to, $text) {
    // ==== REPLACE WITH YOUR INFOBIP CREDENTIALS ====
    $baseUrl = "https://69qpv8.api.infobip.com";   // e.g. https://abcd12.api.infobip.com
    $apiKey  = "84edfec2442bb4c998788856ec770467-9e57587c-493e-4e3b-8e19-8d82e48ec74a";           // e.g. abcdef123456...
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
