### :warning: This project has been deprecated.

Most of this functionality has moved to https://github.com/RoboJackets/concourse-github-check-resource.

----

# php-checks
[![GitHub license](https://img.shields.io/github/license/kberzinch/php-checks.svg?style=flat-square)](https://raw.githubusercontent.com/kberzinch/php-checks/master/LICENSE.md)

This is a suite of integrations for the GitHub Checks API for PHP development. It supports pulling repositories on GitHub.com as well as GitHub Enterprise instances from the same installation.

## Initial server setup
1. Clone this repository to your server and set up a PHP web server.
2. Run `composer install` to install dependencies.
3. Validate by visiting `/webhook` - you should get a signature verification failure. **Stop here if using the Easy Way on github.com.**
4. Copy `config.sample.php` to `config.php`.
5. Generate the value for `$webhook_secret`.
7. Leave the rest, we'll come back in a bit.

## GitHub App setup

### Easy way
This is not supported on GitHub Enterprise as of version 2.15, but it can be used for github.com.

Once your server is set up, visit `/setup` and follow the prompts.

### Hard way

Most likely, you only want your GitHub App to be installed on the account or organization that owns it. Be sure to create the app from this context to ensure that other users can't install it.

1. Go to your account/organization settings on GitHub.com/your GitHub Enterprise instance, then navigate to Developer settings > GitHub Apps > New GitHub App. Most of the form can be filled out as you see fit, but these are the required configuration options.
  * Set the **Webhook URL** to https://example.com/webhook.
  * Set the **Webhook secret** to the same value you configured earlier.
  * Required permissions for full functionality (feel free to adjust)
    * **Checks:** Read & write
    * **Repository contents:** Read & write (write access used for auto-fix functionality)
  * Required event subscriptions for full functionality (feel free to adjust)
    * **Check run**
    * **Check suite**
  * I recommend only allowing the GitHub App to be installed on the owning account.
2. This is a good point to add a logo to your App if you'd like. It will be shown in some places in the UI.
3. Generate a private key and store it somewhere on your server (not accessible from the Internet!) Set its location in `config.php` in the `$private_key` array, keyed by the GitHub where the App is registered.
4. Get the ID under the "About" section and set it in `config.php` in the `$app_id` array, keyed by the GitHub where the App is registered.
5. Go to the **Advanced** tab and check your first webhook (at the bottom of the list). If everything is set up correctly, the response should be 200 with a message body that says "Hello GitHub!"

### Installing the GitHub App
1. Go to the **Install** tab and click "Install" next to your account.
2. Choose whether to set up the app for all repositories on the account, or a subset.
