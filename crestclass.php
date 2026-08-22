<?php
/**
 * Bitrix24 REST API Client Class (CRest)
 */

class CRest
{
    const B24_BATCH_MAX_COUNT = 50;

    public static function call($method, $params = [])
    {
        return self::sendRequest($method, $params);
    }

    public static function callBatch($cmd, $halt = 0)
    {
        $arData = [];
        if (is_array($cmd)) {
            $arData['cmd'] = $cmd;
            $arData['halt'] = $halt;
        }
        return self::sendRequest('batch', $arData);
    }

    protected static function sendRequest($method, $params = [])
    {
        $sessionAuth = self::getAuthData();
        $domain = $sessionAuth['domain'] ?? '';
        $authToken = $sessionAuth['auth_token'] ?? '';

        if (defined('C_REST_WEB_HOOK_URL') && !empty(C_REST_WEB_HOOK_URL)) {
            $url = rtrim(C_REST_WEB_HOOK_URL, '/') . '/' . $method . '.json';
        } elseif (!empty($domain) && !empty($authToken)) {
            $url = 'https://' . $domain . '/rest/' . $method . '.json';
            $params['auth'] = $authToken;
        } else {
            return [
                'error' => 'NO_AUTH',
                'error_description' => 'Missing authorization token or domain'
            ];
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_SSL_VERIFYPEER => !C_REST_IGNORE_SSL,
            CURLOPT_SSL_VERIFYHOST => C_REST_IGNORE_SSL ? 0 : 2,
        ]);

        $result = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return ['error' => 'CURL_ERROR', 'error_description' => $error];
        }

        $response = json_decode($result, true);
        return $response ?: ['error' => 'INVALID_JSON', 'error_description' => 'Invalid JSON response from Bitrix24'];
    }

    public static function getAuthData()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $domain = $_SESSION['b24_domain'] ?? $_REQUEST['DOMAIN'] ?? $_POST['DOMAIN'] ?? '';
        $authToken = $_SESSION['b24_auth_id'] ?? $_REQUEST['AUTH_ID'] ?? $_POST['AUTH_ID'] ?? '';

        if (!empty($domain)) {
            $_SESSION['b24_domain'] = $domain;
        }
        if (!empty($authToken)) {
            $_SESSION['b24_auth_id'] = $authToken;
        }

        return [
            'domain' => $domain,
            'auth_token' => $authToken
        ];
    }
}
