<?php

declare(strict_types=1);

return [
    'resource' => [
        'model_label' => 'monitor',
        'plural_model_label' => 'monitors',
        'navigation_label' => 'Uptime monitors',
        'empty_heading' => 'No monitors yet',
        'empty_description' => 'Add a site to check its uptime and SSL certificate on the package\'s schedule.',
    ],

    'form' => [
        'monitor' => 'Monitor',
        'url' => 'URL',
        'url_helper' => 'The full address to check, with http:// or https://.',
        'uptime_check_enabled' => 'Check uptime',
        'certificate_check_enabled' => 'Check the SSL certificate',
        'certificate_check_helper' => 'Only for https URLs. The package checks certificates on port 443.',
        'uptime_check' => 'Uptime check',
        'interval' => 'Check every',
        'interval_suffix' => 'minutes',
        'method' => 'HTTP method',
        'look_for_string' => 'Look for text',
        'look_for_string_helper' => 'The check fails when the response does not contain this text. Leave empty to only check the status code.',
        'payload' => 'Request body',
        'headers' => 'Extra request headers',
        'header_name' => 'Header',
        'header_value' => 'Value',
        'response_checker' => 'Response checker',
        'response_checker_default' => 'Default (from config)',
    ],

    'validation' => [
        'scheme' => 'Use a full http:// or https:// URL.',
        'credentials' => 'Leave the user name and password out of the URL. Send them as a request header instead.',
        'private_host' => 'This URL points to a private or local address, which is blocked by default. See allowPrivateHosts() in the plugin docs.',
        'unique' => 'This URL is already monitored.',
        'certificate_https' => 'Certificates can only be checked for https URLs.',
        'head_with_text' => 'A HEAD response has no body to search. Use GET to look for text.',
    ],

    'columns' => [
        'url' => 'URL',
        'uptime' => 'Uptime',
        'certificate' => 'Certificate',
        'last_checked' => 'Last checked',
        'interval' => 'Interval',
        'interval_value' => 'Every :count min',
        'never' => 'Never',
        'since' => 'since :time',
        'expires' => 'Expires :date',
        'expired' => 'Expired :date',
        'issuer' => 'Issuer: :issuer',
    ],

    'uptime' => [
        'up' => 'Up',
        'down' => 'Down',
        'unchecked' => 'Not checked yet',
        'disabled' => 'Off',
    ],

    'certificate' => [
        'valid' => 'Valid',
        'expiring' => 'Expires soon',
        'invalid' => 'Invalid',
        'unchecked' => 'Not checked yet',
        'off' => 'Off',
    ],

    'filters' => [
        'uptime' => 'Uptime',
        'certificate' => 'Certificate',
        'enabled' => 'Monitoring',
        'enabled_true' => 'On',
        'enabled_false' => 'Off',
        'problems' => 'Needs attention',
    ],

    'actions' => [
        'check' => [
            'label' => 'Check now',
            'bulk_label' => 'Check selected now',
            'up' => ':url is up',
            'down' => ':url is down',
            'certificate_invalid' => 'Certificate invalid: :reason',
            'certificate_valid' => 'Certificate valid until :date',
            'checked' => '{1} Checked 1 monitor|[2,*] Checked :count monitors',
            'summary' => ':up up, :down down.',
            'blocked' => '{1} 1 monitor was skipped: its URL points to a private address.|[2,*] :count monitors were skipped: their URLs point to private addresses.',
            'disabled' => '{1} 1 monitor is off and was not checked.|[2,*] :count monitors are off and were not checked.',
            'too_many' => 'Select at most :max monitors to check at once.',
            'rate_limited' => 'Too many checks. Try again in a minute.',
            'skipped' => '{1} 1 monitor was skipped: you may not check it.|[2,*] :count monitors were skipped: you may not check them.',
            'error' => 'The result was saved, but something after the check failed (often a notification channel): :message',
        ],
        'enable' => [
            'label' => 'Turn on',
            'bulk_label' => 'Turn on selected',
            'success' => '{1} Turned on 1 monitor|[2,*] Turned on :count monitors',
        ],
        'disable' => [
            'label' => 'Turn off',
            'bulk_label' => 'Turn off selected',
            'modal_heading' => 'Turn off this monitor?',
            'bulk_modal_heading' => 'Turn off the selected monitors?',
            'modal_description' => 'Uptime and certificate checks stop until you turn it on again. Its history is kept.',
            'success' => '{1} Turned off 1 monitor|[2,*] Turned off :count monitors',
        ],
        'skipped' => '{1} 1 monitor was skipped: you may not change it.|[2,*] :count monitors were skipped: you may not change them.',
    ],

    'widget' => [
        'heading' => 'Uptime',
        'up' => 'Up',
        'up_description' => 'of :count monitored',
        'down' => 'Down',
        'down_none' => 'Everything is reachable',
        'certificates' => 'Certificate problems',
        'certificates_description' => 'Invalid, or expiring within :days days',
        'certificates_none' => 'All certificates are fine',
        'last_check' => 'Last check',
        'last_check_never' => 'Never',
        'last_check_none' => 'Run the package\'s scheduler to start checks',
        'last_check_stale' => 'Is the scheduler running?',
        'last_check_ok' => ':count monitors on',
    ],
];
