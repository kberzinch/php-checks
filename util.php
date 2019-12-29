<?php

declare(strict_types=1);

// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.NotCamelCaps
// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableReturnTypeHintSpecification
// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableParameterTypeHintSpecification


use SimpleJWT\JWT;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\Keys\RSAKey;

/**
 * Verifies and parses the payload
 *
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function payload(): array
{
    global $webhook_secret;
    [$algo, $hash] = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2);
    $payload = file_get_contents('php://input');
    if (false === $payload) {
        http_response_code(500);
        die('Could not read php://input');
    }
    $payloadHash = hash_hmac($algo, $payload, $webhook_secret);
    if ($hash !== $payloadHash) {
        http_response_code(401);
        die('Signature verification failed');
    }

    return json_decode($payload, true);
}

/**
 * Injects an authentication token for the given URL if one is available in the config file
 *
 * @param string $url The URL to tokenize
 *
 * @return string      The URL, possibly with an authentication token inserted
 */
function add_access_token(string $url): string
{
    global $token;
    $clone_url = explode('/', $url);
    $clone_url[2] = 'x-access-token:' . $token . '@' . $clone_url[2];
    return implode('/', $clone_url);
}

/**
 * Sends $data to $url
 *
 * @param string $url  The GitHub API URL to hit
 * @param array  $data The data to send
 *
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function github(
    string $url,
    array $data,
    string $action = '',
    string $accept = 'application/vnd.github.machine-man-preview+json',
    string $method = 'POST',
    int $expected_status = 201
): array {
    global $token;
    global $app_id;
    $curl = curl_init($url);
    if (false === $curl) {
        http_response_code(500);
        exit('Could not initialize cURL');
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: ' . $accept,
        'User-Agent: GitHub App ID ' . $app_id[which_github()],
        'Authorization: Bearer ' . $token,
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($curl);
    if (false === $response || true === $response || curl_getinfo($curl, CURLINFO_HTTP_CODE) !== $expected_status) {
        echo 'Error ' . $action . "\n" . $url . "\n" . json_encode($data) . "\n"
            . curl_getinfo($curl, CURLINFO_HTTP_CODE) . ' ' . $response;
        curl_close($curl);
        exit;
    }
    curl_close($curl);
    return json_decode($response, true);
}

/**
 * Fetches an installation token for other components to use
 *
 * @return string a GitHub App access token for interacting with the repository
 */
function token(): string
{
    global $token;

    $token = app_token();

    $access_token = github(
        api_base() . '/installations/' . installation_id() . '/access_tokens',
        [],
        'getting access token'
    );

    return $access_token['token'];
}

/**
 * Checks the commit status for the current commit
 */
function get_commit_status(): array
{
    global $payload;

    return github(
        $payload['commit']['url'] . '/status',
        [],
        'getting commit status',
        'application/vnd.github.machine-man-preview+json',
        'GET',
        200
    );
}

/**
 * Provides the primary GitHub domain for this event. Used for looking up app
 * registration information.
 *
 * @return string primary GitHub domain
 */
function which_github(): string
{
    global $payload;
    return explode('/', $payload['repository']['clone_url'])[2];
}

/**
 * Gets an app JWT
 *
 * @return                                 string JWT for this GitHub
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function app_token(): string
{
    global $private_key;
    global $app_id;

    $key = file_get_contents($private_key[which_github()]);
    if (false === $key) {
        http_response_code(500);
        exit('Could not read private key for ' . which_github());
    }

    $key = new RSAKey($key, 'pem');
    $set = new KeySet();
    $set->add($key);

    $headers = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = ['iss' => $app_id[which_github()], 'exp' => time() + 5];
    $jwt = new JWT($headers, $claims);

    return $jwt->encode($set);
}

/**
 * Provides the installation ID for this event.
 */
function installation_id(): int
{
    global $payload;
    global $token;

    if (isset($payload['installation']['id'])) {
        return $payload['installation']['id'];
    }
    if (!isset($token)) {
        $token = app_token();
    }
    $installation = github(
        api_base() . '/repos/' . $payload['repository']['full_name'] . '/installation',
        [],
        'getting installation information',
        'application/vnd.github.machine-man-preview+json',
        'GET',
        200
    );
    return $installation['id'];
}

/**
 * Returns base API URL for this event
 *
 * @return string the GitHub API base URL
 */
function api_base(): string
{
    return 'https://' . ('github.com' === which_github() ? 'api.github.com' : which_github() . '/api/v3');
}

/**
 * Helper to call once a check run finishes to notify Slack if this is the last one
 *
 * @return void
 */
function check_run_finish(): void
{
    global $payload;

    $check_suite = github(
        $payload['check_run']['check_suite']['url'],
        [],
        'fetching check suite information',
        'application/vnd.github.antiope-preview+json',
        'GET',
        200
    );

    if ('completed' !== $check_suite['status']) {
        return;
    }

    $check_runs = github(
        $payload['check_run']['check_suite']['url'] . '/check-runs',
        [],
        'fetching check run information',
        'application/vnd.github.antiope-preview+json',
        'GET',
        200
    );
    $slack_message = [
        'text' => 'Checks completed for <' . $payload['repository']['html_url'] . '/commit/'
            . $payload['check_run']['check_suite']['head_sha'] . '|`'
            . substr($payload['check_run']['check_suite']['head_sha'], 0, 7)
            . '`> by <' . $payload['sender']['html_url'] . '|' . $payload['sender']['login'] . '> on <'
            . $payload['repository']['html_url'] . '/tree/' . $payload['check_run']['check_suite']['head_branch'] . '|'
            . $payload['repository']['name'] . ':' . $payload['check_run']['check_suite']['head_branch'] . '>',
        'attachments' => [],
    ];

    $gh_to_slack_colors = [
        'failure' => 'danger',
        'action_required' => 'danger',
        'success' => 'good',
    ];

    foreach ($check_runs['check_runs'] as $check_run) {
        $slack_message['attachments'][] = [
            'color' => $gh_to_slack_colors[$check_run['conclusion']],
            'title' => $check_run['name'],
            'title_link' => $check_run['html_url'],
            'text' => $check_run['output']['title'],
            'fallback' => $check_run['name'] . ': ' . $check_run['output']['title'],
        ];
    }

    notify_slack($slack_message);
}

/**
 * Fires a Slack notification when all checks complete successfully.
 *
 * @param array $data the message
 *
 * @return void
 *
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function notify_slack(array $data): void
{
    global $slack_webhook;
    $curl = curl_init($slack_webhook);
    if (false === $curl) {
        http_response_code(500);
        exit('Could not initialize cURL');
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($curl);
    if (false === $response || true === $response || 200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
        curl_close($curl);
        http_response_code(500);
        exit('Invalid response from Slack');
    }
    curl_close($curl);
}
