<?php

///////////////////////////////////////////////////////////
// Copy this file to config.php and edit as appropriate. //
///////////////////////////////////////////////////////////

/**
 * Set $webhook_secret to the same value you entered in the GitHub webhook
 * configuration.
 */
$webhook_secret = 'generate me at randomkeygen.com or wherever';

/**
 * The location of the private key for your GitHub app here.
 */
$private_key["github.com"] = '/opt/deploy/your-github-app.pem';

/**
 * The ID of your GitHub app
 */
$app_id["github.com"] = 15018;
