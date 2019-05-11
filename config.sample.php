<?php declare(strict_types = 1);

///////////////////////////////////////////////////////////
// Copy this file to config.php and edit as appropriate. //
///////////////////////////////////////////////////////////

/**
 * Set $webhook_secret to the same value you entered in the GitHub webhook
 * configuration.
 */
$webhook_secret = 'generate me at randomkeygen.com or wherever';

/**
 * If you'd like to host this tool on a shared domain, you may set a URL prefix here. Add a trailing slash if you set
 * one.
 */
$url_prefix = '';

/**
 * The location of the private key for your GitHub app here.
 */
$private_key['github.com'] = '/opt/deploy/your-github-app.pem';

/**
 * The ID of your GitHub app
 */
$app_id['github.com'] = 15018;

$checks = [];
$checks['Syntax'] = 'syntax';
$checks['CodeSniffer'] = 'codesniffer';
$checks['Mess Detector'] = 'messdetector';
$checks['PHPStan'] = 'phpstan';
$checks['Phan'] = 'phan';

$slack_webhook = '';
