<?php

function start_app_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");

    $params = session_get_cookie_params();
    $isHttps = isset($_SERVER["HTTPS"])
        && $_SERVER["HTTPS"] !== ""
        && strtolower((string) $_SERVER["HTTPS"]) !== "off";

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}
