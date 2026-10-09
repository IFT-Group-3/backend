<?php

function get_paystack_secret_key(): ?string
{
    $secretKey = getenv("PAYSTACK_SECRET_KEY");
    if (is_string($secretKey) && $secretKey !== "") {
        return $secretKey;
    }

    $secretsFile = __DIR__ . "/secrets.php";
    if (!is_file($secretsFile)) {
        return null;
    }

    $secrets = require $secretsFile;
    if (
        !is_array($secrets)
        || !isset($secrets["PAYSTACK_SECRET_KEY"])
        || !is_string($secrets["PAYSTACK_SECRET_KEY"])
        || $secrets["PAYSTACK_SECRET_KEY"] === ""
    ) {
        throw new RuntimeException("PAYSTACK_SECRET_KEY is missing from config/secrets.php.");
    }

    return $secrets["PAYSTACK_SECRET_KEY"];
}
