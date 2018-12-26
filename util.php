<?php

/**
 * Verifies and parses the payload
 * @return array the GitHub webhook payload
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function payload()
{
    global $webhook_secret;
    list($algo, $hash) = explode('=', $_SERVER["HTTP_X_HUB_SIGNATURE"], 2);
    $payload = file_get_contents('php://input');
    $payloadHash = hash_hmac($algo, $payload, $webhook_secret);
    if ($hash !== $payloadHash) {
        http_response_code(401);
        die("Signature verification failed.");
    }

    return json_decode($payload, true);
}

/**
 * Injects an authentication token for the given URL if one is available in the config file
 * @param  string $url The URL to tokenize
 * @return string      The URL, possibly with an authentication token inserted
 */
function add_access_token(string $url)
{
    global $token;
    $clone_url = explode("/", $url);
    $clone_url[2] = "x-access-token:".$token."@".$clone_url[2];
    return implode("/", $clone_url);
}

/**
 * Sends $data to $url
 * @param  string $url  The GitHub API URL to hit
 * @param  array  $data The data to send
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function github(
    string $url,
    array $data,
    string $action = "",
    string $accept = "application/vnd.github.machine-man-preview+json",
    string $method = "POST",
    int $expected_status = 201
)
{
    global $token;
    global $is_slack;
    global $app_id;
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Accept: ".$accept,
        "User-Agent: GitHub App ID ".$app_id[which_github()],
        "Authorization: Bearer ".$token
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($curl);
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== $expected_status) {
        echo "Error ".$action."\n".$url."\n".json_encode($data)."\n".curl_getinfo($curl, CURLINFO_HTTP_CODE)." ".$response;
        if (!$is_slack) {
            http_response_code(500);
        }
        curl_close($curl);
        exit;
    }
    curl_close($curl);
    return json_decode($response, true);
}

/**
 * Fetches an installation token for other components to use
 * @return string a GitHub App access token for interacting with the repository
 */
function token()
{
    global $token;

    $token = app_token();

    $access_token = github(
        api_base()."/installations/".installation_id()."/access_tokens",
        [],
        "getting access token"
    );

    return $access_token["token"];
}

/**
 * Checks the commit status for the current commit
 * @return string one of pending, success, failure, error
 */
function get_commit_status()
{
    global $payload;

    return github(
        $payload["commit"]["url"]."/status",
        [],
        "getting commit status",
        "application/vnd.github.machine-man-preview+json",
        "GET",
        200
    );
}

/**
 * Provides the primary GitHub domain for this event. Used for looking up app
 * registration information.
 * @return string primary GitHub domain
 */
function which_github()
{
    global $payload;
    return explode("/", $payload["repository"]["clone_url"])[2];
}

/**
 * Gets an app JWT
 * @return string JWT for this GitHub
 */
function app_token()
{
    global $private_key;
    global $app_id;

    $key = new SimpleJWT\Keys\RSAKey(file_get_contents($private_key[which_github()]), 'pem');
    $set = new SimpleJWT\Keys\KeySet();
    $set->add($key);

    $headers = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = ['iss' => $app_id[which_github()], 'exp' => time() + 5];
    $jwt = new SimpleJWT\JWT($headers, $claims);

    return $jwt->encode($set);
}

/**
 * Provides the installation ID for this event.
 */
function installation_id()
{
    global $payload;
    global $token;

    if (isset($payload["installation"]["id"])) {
        return $payload["installation"]["id"];
    }
    if (!isset($token)) {
        $token = app_token();
    }
    $installation = github(
        api_base()."/repos/".$payload["repository"]["full_name"]."/installation",
        [],
        "getting installation information",
        "application/vnd.github.machine-man-preview+json",
        "GET",
        200
    );
    return $installation["id"];
}

/**
 * Returns base API URL for this event
 * @return string the GitHub API base URL
 */
function api_base()
{
    return "https://".(which_github() === "github.com" ? "api.github.com" : which_github()."/api/v3");
}
