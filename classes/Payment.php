<?php

class Payment
{
    private const API_BASE_URL = "https://api.paystack.co";

    public static function initialize(
        string $secretKey,
        string $email,
        int $amountKobo,
        string $reference,
        int $allocationId
    ): array {
        return self::request(
            $secretKey,
            "POST",
            "/transaction/initialize",
            [
                "email" => $email,
                "amount" => $amountKobo,
                "currency" => "NGN",
                "reference" => $reference,
                "metadata" => [
                    "allocation_id" => $allocationId
                ]
            ]
        );
    }

    public static function verify(string $secretKey, string $reference): array
    {
        return self::request(
            $secretKey,
            "GET",
            "/transaction/verify/" . rawurlencode($reference)
        );
    }

    private static function request(
        string $secretKey,
        string $method,
        string $path,
        ?array $payload = null
    ): array {
        $headers = [
            "Authorization: Bearer " . $secretKey,
            "Accept: application/json"
        ];
        $content = "";

        if ($payload !== null) {
            $encodedPayload = json_encode($payload);
            if (!is_string($encodedPayload)) {
                throw new RuntimeException("Could not encode Paystack request.");
            }

            $headers[] = "Content-Type: application/json";
            $content = $encodedPayload;
        }

        $context = stream_context_create([
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers),
                "content" => $content,
                "timeout" => 15,
                "ignore_errors" => true
            ]
        ]);

        $responseBody = @file_get_contents(
            self::API_BASE_URL . $path,
            false,
            $context
        );
        if (!is_string($responseBody)) {
            throw new RuntimeException("Could not reach Paystack.");
        }

        $response = json_decode($responseBody, true);
        if (!is_array($response)) {
            throw new RuntimeException("Paystack returned an invalid response.");
        }

        $statusLine = $http_response_header[0] ?? "";
        if (
            !str_contains($statusLine, " 200 ")
            || ($response["status"] ?? false) !== true
            || !is_array($response["data"] ?? null)
        ) {
            $message = $response["message"] ?? "Paystack request failed.";
            throw new RuntimeException((string) $message);
        }

        return $response["data"];
    }
}