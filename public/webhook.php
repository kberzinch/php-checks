<?php declare(strict_types = 1);

require __DIR__ . '/../vendor/autoload.php';
require_once '../config.php';

global $app_id;
global $checks;
global $url_prefix;

$payload = payload();

switch ($_SERVER['HTTP_X_GITHUB_EVENT']) {
    case 'ping':
        echo 'Hello GitHub!';
        break;
    case 'check_run':
        if ($payload['check_run']['check_suite']['app']['id'] !== $app_id[which_github()]) {
            exit(
                'App ID is ' . $payload['check_run']['check_suite']['app']['id'] . ' not ' . $app_id[which_github()]
                . ', ignoring'
            );
        }
        if ('requested_action' === $payload['action']) {
            if ('phpcbf' !== $payload['requested_action']['identifier']) {
                exit('Requested action is ' . $payload['requested_action']['identifier'] . ', ignoring');
            }
            $token = token();
            passthru(
                '/bin/bash -x -e -o pipefail ' . __DIR__ . '/../phpcbf.sh ' . $payload['repository']['name'] . ' '
                . $payload['check_run']['head_sha'] . ' ' . $payload['check_run']['check_suite']['head_branch'] . ' '
                . add_access_token($payload['repository']['clone_url']) . ' > '
                . __DIR__ . '/' . $payload['repository']['name'] . '/' . $payload['check_run']['head_sha']
                . '/codesniffer/phpcbf.txt 2>&1',
                $return_value
            );
            exit('phpcbf completed with return value ' . $return_value);
        }
        if ('created' !== $payload['action'] && 'rerequested' !== $payload['action']) {
            exit('Action is ' . $payload['action'] . ', ignoring');
        }
        $token = token();
        github(
            $payload['check_run']['url'],
            [
                'status' => 'in_progress',
                'started_at' => date(DATE_ATOM),
            ],
            'reporting check_run in progress',
            'application/vnd.github.antiope-preview+json',
            'PATCH',
            200
        );
        $log_location = __DIR__ . '/' . $payload['repository']['name'] . '/' . $payload['check_run']['head_sha'] . '/'
            . $payload['check_run']['external_id'];
        $log_file = $log_location . '/plain.txt';
        $plain_log_url = 'https://' . $_SERVER['SERVER_NAME'] . '/' . $url_prefix . $payload['repository']['name'] . '/'
            . $payload['check_run']['head_sha'] . '/' . $payload['check_run']['external_id'] . '/plain.txt';
        mkdir($log_location, 0700, true);
        copy(__DIR__ . '/../log-index.html', $log_location . '/index.html');
        copy(__DIR__ . '/../worker.js', $log_location . '/worker.js');
        copy(__DIR__ . '/../app.js', $log_location . '/app.js');
        $return_value = 0;

        file_put_contents(
            $log_location . '/title',
            $payload['check_run']['name'] . ' | ' . $payload['repository']['full_name'],
            FILE_APPEND
        );

        switch ($payload['check_run']['external_id']) {
            case 'syntax':
                passthru(
                    '/bin/bash -e -o pipefail ' . __DIR__ . '/../syntax.sh ' . $payload['repository']['name'] . ' > '
                    . $log_file . ' 2>&1',
                    $return_value
                );
                if (0 !== $return_value) {
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'action_required',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'Syntax check exited with an unexpected return code ' . $return_value,
                                'summary' => 'Please review the output.',
                            ],
                        ],
                        'reporting syntax check_run failure',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                    exit('Syntax check failed with return value ' . $return_value);
                }
                $syntax_log = file_get_contents($log_location . '/plain.txt');
                if (false === $syntax_log) {
                    http_response_code(500);
                    exit('Could not read syntax log.');
                }
                $syntax_log = explode("\n", $syntax_log);
                $syntax_log_count = count($syntax_log);
                $files_with_issues = 0;
                $issues = 0;
                $annotations = [];
                for ($i = 0; $i < $syntax_log_count; $i++) {
                    if (false !== strpos($syntax_log[$i], 'No syntax errors detected in ')) {
                        continue;
                    }

                    if (false !== strpos($syntax_log[$i], 'Errors parsing ')) {
                        $files_with_issues++;
                        continue;
                    }

                    if (false !== strpos($syntax_log[$i], 'PHP Parse error:  syntax error, ')) {
                        $matches = [];
                        if (1 !== preg_match(
                            '/in (\S+) on line ([[:digit:]]+)$/',
                            $syntax_log[$i],
                            $matches,
                            PREG_OFFSET_CAPTURE
                        )
                        ) {
                            github(
                                $payload['check_run']['url'],
                                [
                                    'conclusion' => 'action_required',
                                    'completed_at' => date(DATE_ATOM),
                                    'details_url' => $plain_log_url,
                                    'output' => [
                                        'title' => 'Could not parse syntax error output',
                                        'summary' => 'Please send the output to the check developer.',
                                    ],
                                ],
                                'reporting check_run failure',
                                'application/vnd.github.antiope-preview+json',
                                'PATCH',
                                200
                            );
                            http_response_code(500);
                            exit('Could not parse output from PHP syntax check ' . $syntax_log[$i]);
                        }
                        $issues++;
                        $annotations[] = [
                            'path' => substr($matches[1][0], 2, strlen($matches[1][0])),
                            'start_line' => intval($matches[2][0]),
                            'end_line' => intval($matches[2][0]),
                            'annotation_level' => 'failure',
                            'message' => substr($syntax_log[$i], 32, $matches[0][1] - 33),
                        ];
                    } elseif (false !== strpos($syntax_log[$i], 'PHP Fatal error: ')) {
                        $matches = [];
                        if (1 !== preg_match(
                            '/in (\S+) on line ([[:digit:]]+)$/',
                            $syntax_log[$i],
                            $matches,
                            PREG_OFFSET_CAPTURE
                        )
                        ) {
                            github(
                                $payload['check_run']['url'],
                                [
                                    'conclusion' => 'action_required',
                                    'completed_at' => date(DATE_ATOM),
                                    'details_url' => $plain_log_url,
                                    'output' => [
                                        'title' => 'Could not parse fatal error output',
                                        'summary' => 'Please send the output to the check developer.',
                                    ],
                                ],
                                'reporting check_run failure',
                                'application/vnd.github.antiope-preview+json',
                                'PATCH',
                                200
                            );
                            http_response_code(500);
                            exit('Could not parse output from PHP syntax check ' . $syntax_log[$i]);
                        }
                        $issues++;
                        $annotations[] = [
                            'path' => substr($matches[1][0], 2, strlen($matches[1][0])),
                            'start_line' => intval($matches[2][0]),
                            'end_line' => intval($matches[2][0]),
                            'annotation_level' => 'failure',
                            'message' => substr($syntax_log[$i], 18, $matches[0][1] - 19),
                        ];
                    } elseif (0 === strlen($syntax_log[$i])) {
                        continue;
                    } else {
                        github(
                            $payload['check_run']['url'],
                            [
                                'conclusion' => 'action_required',
                                'completed_at' => date(DATE_ATOM),
                                'details_url' => $plain_log_url,
                                'output' => [
                                    'title' => 'Encountered unexpected output from syntax check',
                                    'summary' => 'Please send the output to the check developer.',
                                ],
                            ],
                            'reporting check_run failure',
                            'application/vnd.github.antiope-preview+json',
                            'PATCH',
                            200
                        );
                        http_response_code(500);
                        exit('Unexpected output from PHP syntax check: ' . $syntax_log[$i]);
                    }
                }
                if (0 === $issues) {
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'success',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'All files successfully parsed',
                                'summary' => 'All PHP files in the repository were successfully parsed.',
                            ],
                        ],
                        'reporting syntax check success',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                } else {
                    $total_annotations = count($annotations);
                    $chunks = array_chunk($annotations, 50);
                    for ($i = 0; $i < ($total_annotations / 50); $i++) {
                        github(
                            $payload['check_run']['url'],
                            [
                                'conclusion' => 'failure',
                                'completed_at' => date(DATE_ATOM),
                                'details_url' => $plain_log_url,
                                'output' => [
                                    'title' => 'Found ' . $issues . ' issue' . ( 1 === $issues ? '' : 's') . ' in '
                                        . $files_with_issues . ' file' . ( 1 === $files_with_issues ? '' : 's' ),
                                    'summary' => 'PHP was unable to parse the below file'
                                        . ( 1 === $files_with_issues ? '.' : 's.' ),
                                    'annotations' => $chunks[$i],
                                ],
                            ],
                            'reporting syntax check failure',
                            'application/vnd.github.antiope-preview+json',
                            'PATCH',
                            200
                        );
                    }
                }
                break;
            case 'codesniffer':
                passthru(
                    '/bin/bash -x -e -o pipefail ' . __DIR__ . '/../codesniffer.sh ' . $payload['repository']['name']
                    . ' ' . $log_location . '/output.json > ' . $log_file . ' 2>&1',
                    $return_value
                );
                if (0 !== $return_value && 1 !== $return_value) {
                    echo 'codesniffer check_run failed with return value ' . $return_value;
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'action_required',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'CodeSniffer exited with an unexpected return code ' . $return_value,
                                'summary' => 'Please review the output.',
                            ],
                        ],
                        'reporting codesniffer check_run failure',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                    exit;
                }
                $log = file_get_contents($log_location . '/output.json');
                if (false === $log) {
                    exit('Could not read codesniffer log.');
                }
                $log = json_decode($log, true);
                $files_with_issues = 0;
                $issues = 0;
                $fixable = 0;
                $annotations = [];
                $phpcs_to_github = [];
                $phpcs_to_github['ERROR'] = 'failure';
                $phpcs_to_github['WARNING'] = 'warning';
                foreach ($log['files'] as $path => $file) {
                    foreach ($file['messages'] as $message) {
                        $issues++;
                        $annotations[] = [
                            'path' => substr(
                                $path,
                                strlen(__DIR__ . $payload['repository']['name']) + 5,
                                strlen($path)
                            ),
                            'start_line' => $message['line'],
                            'end_line' => $message['line'],
                            'annotation_level' => $phpcs_to_github[$message['type']],
                            'message' => $message['message'],
                        ];
                        if (true !== $message['fixable']) {
                            continue;
                        }

                        $fixable++;
                    }
                    if (count($file['messages']) <= 0) {
                        continue;
                    }

                    $files_with_issues++;
                }
                if (0 === $issues) {
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'success',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'All files meet code style requirements',
                                // phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
                                'summary' => 'All PHP files in the repository comply with the configured style '
                                    . 'standard.',
                                // phpcs:enable
                            ],
                        ],
                        'reporting codesniffer check success',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                } else {
                    $total_annotations = count($annotations);
                    $chunks = array_chunk($annotations, 50);
                    for ($i = 0; $i < ($total_annotations / 50); $i++) {
                        github(
                            $payload['check_run']['url'],
                            [
                                'conclusion' => 'failure',
                                'completed_at' => date(DATE_ATOM),
                                'details_url' => $plain_log_url,
                                'output' => [
                                    'title' => 'Found ' . $issues . ' issue' . (1 === $issues ? '' : 's') . ' in '
                                        . $files_with_issues . ' file' . (1 === $files_with_issues ? '' : 's'),
                                    'summary' => 'The below file' . (1 === $files_with_issues ? '' : 's') . ' do'
                                        . (1 === $files_with_issues ? 'es' : '') . ' not comply with the configured '
                                        . "style standard.\n\n" . $fixable . ' issue' . (1 === $fixable ? '' : 's')
                                        . ' can be fixed automatically.',
                                    'annotations' => $chunks[$i],
                                ],
                                'actions' => ( $fixable > 0 ? [
                                    [
                                        'label' => 'Fix Issues',
                                        'description' => 'Automatically fix ' . $fixable . ' issue'
                                            . (1 === $fixable ? '' : 's'),
                                        'identifier' => 'phpcbf',
                                    ],
                                ] : []),
                            ],
                            'reporting codesniffer check failure',
                            'application/vnd.github.antiope-preview+json',
                            'PATCH',
                            200
                        );
                    }
                }
                break;
            case 'messdetector':
                passthru(
                    '/bin/bash -x -e -o pipefail ' . __DIR__ . '/../messdetector.sh ' . $payload['repository']['name']
                    . ' ' . $log_location . '/output.xml > ' . $log_file . ' 2>&1',
                    $return_value
                );
                if (0 !== $return_value) {
                    echo 'messdetector check_run failed with return value ' . $return_value;
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'action_required',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'Mess Detector exited with an unexpected return code ' . $return_value,
                                'summary' => 'Please review the output.',
                            ],
                        ],
                        'reporting messdetector check_run failure',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                    exit;
                }
                $xml = simplexml_load_file($log_location . '/output.xml');
                if (false === $xml) {
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'action_required',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'Mess Detector did not output valid XML',
                                'summary' => 'Please review the output.',
                            ],
                        ],
                        'reporting messdetector check_run failure',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                    exit;
                }

                $files_with_issues = 0;
                $issues = 0;
                $annotations = [];

                foreach ($xml->children() as $file) {
                    $files_with_issues++;
                    foreach ($file->children() as $violation) {
                        $issues++;
                        $annotations[] = [
                            'path' => substr(
                                $file['name']->__toString(),
                                strlen(__DIR__ . $payload['repository']['name']) + 5,
                                strlen($file['name']->__toString())
                            ),
                            'start_line' => intval($violation['beginline']->__toString()),
                            'end_line' => intval($violation['endline']->__toString()),
                            'annotation_level' => 'failure',
                            'message' => trim($violation->__toString()),
                        ];
                    }
                }
                if (0 === $issues) {
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'success',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'No messes detected',
                                'summary' => 'Mess Detector did not detect any messes.',
                            ],
                        ],
                        'reporting mess detector check success',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                } else {
                    $total_annotations = count($annotations);
                    $chunks = array_chunk($annotations, 50);
                    for ($i = 0; $i < ($total_annotations / 50); $i++) {
                        github(
                            $payload['check_run']['url'],
                            [
                                'conclusion' => 'failure',
                                'completed_at' => date(DATE_ATOM),
                                'details_url' => $plain_log_url,
                                'output' => [
                                    'title' => 'Found ' . $issues . ' issue' . ( 1 === $issues ? '' : 's') . ' in '
                                        . $files_with_issues . ' file' . ( 1 === $files_with_issues ? '' : 's' ),
                                    'summary' => ( 1 === $issues ? 'A mess was' : 'Messes were' )
                                        . ' detected in the below file' . ( 1 === $files_with_issues ? '' : 's' ) . '.',
                                    'annotations' => $chunks[$i],
                                ],
                            ],
                            'reporting messdetector check failure',
                            'application/vnd.github.antiope-preview+json',
                            'PATCH',
                            200
                        );
                    }
                }
                break;
            case 'phpstan':
                passthru(
                    '/bin/bash -e -o pipefail ' . __DIR__ . '/../phpstan.sh ' . $payload['repository']['name'] . ' > '
                    . $log_file . ' 2>&1',
                    $return_value
                );
                if (0 !== $return_value && 1 !== $return_value) {
                    echo 'phpstan check_run failed with return value ' . $return_value;
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'action_required',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'PHPStan exited with an unexpected return code ' . $return_value,
                                'summary' => 'Please review the output.',
                            ],
                        ],
                        'reporting phpstan check_run failure',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                    exit;
                }
                $xml = simplexml_load_file($log_location . '/plain.txt');
                if (false === $xml) {
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'action_required',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'PHPStan did not output valid XML',
                                'summary' => 'Please review the output.',
                            ],
                        ],
                        'reporting phpstan check_run failure',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                    exit;
                }

                $files_with_issues = 0;
                $issues = 0;
                $annotations = [];
                $phpstan_to_github = [];
                $phpstan_to_github['error'] = 'failure';

                foreach ($xml->children() as $file) {
                    $files_with_issues++;
                    foreach ($file->children() as $violation) {
                        if (null === $file['name']) {
                            // this is an unmatched ignore
                            // find in the neon file

                            $matches = [];
                            if (1 !== preg_match(
                                '/Ignored error pattern (.+) was not matched in reported errors\./',
                                trim($violation['message']->__toString()),
                                $matches
                            )
                            ) {
                                github(
                                    $payload['check_run']['url'],
                                    [
                                        'conclusion' => 'action_required',
                                        'completed_at' => date(DATE_ATOM),
                                        'details_url' => $plain_log_url,
                                        'output' => [
                                            'title' => 'Could not parse PHPStan output',
                                            'summary' => 'Please send the output to the check developer.',
                                        ],
                                    ],
                                    'reporting check_run failure',
                                    'application/vnd.github.antiope-preview+json',
                                    'PATCH',
                                    200
                                );
                                http_response_code(500);
                                exit(
                                    'Could not parse output from PHPStan ' . trim($violation['message']->__toString())
                                );
                            }

                            $search = $matches[1];
                            $lines = file(
                                __DIR__ . '/../workspace/' . $payload['repository']['name'] . '/phpstan.neon'
                            );

                            $found = false;
                            $line_counter = 0;

                            if (false === $lines) {
                                github(
                                    $payload['check_run']['url'],
                                    [
                                        'conclusion' => 'action_required',
                                        'completed_at' => date(DATE_ATOM),
                                        'details_url' => $plain_log_url,
                                        'output' => [
                                            'title' => 'Could not parse PHPStan output',
                                            'summary' => 'Please send the output to the check developer.',
                                        ],
                                    ],
                                    'reporting check_run failure',
                                    'application/vnd.github.antiope-preview+json',
                                    'PATCH',
                                    200
                                );
                                http_response_code(500);
                                exit(
                                    'Could not parse output from PHPStan ' . trim($violation['message']->__toString())
                                );
                            }

                            foreach ($lines as $line) {
                                $line_counter++;
                                if (false !== strpos($line, $search)) {
                                    $found = true;
                                    break;
                                }
                            }

                            if (false === $found) {
                                github(
                                    $payload['check_run']['url'],
                                    [
                                        'conclusion' => 'action_required',
                                        'completed_at' => date(DATE_ATOM),
                                        'details_url' => $plain_log_url,
                                        'output' => [
                                            'title' => 'Could not parse PHPStan output',
                                            'summary' => 'Please send the output to the check developer.',
                                        ],
                                    ],
                                    'reporting check_run failure',
                                    'application/vnd.github.antiope-preview+json',
                                    'PATCH',
                                    200
                                );
                                http_response_code(500);
                                exit(
                                    'Could not parse output from PHPStan ' . trim($violation['message']->__toString())
                                );
                            }

                            $issues++;
                            $annotations[] = [
                                'path' => 'phpstan.neon',
                                'start_line' => $line_counter,
                                'end_line' => $line_counter,
                                'annotation_level' => $phpstan_to_github[$violation['severity']->__toString()],
                                'message' => trim($violation['message']->__toString()),
                            ];
                            continue;
                        }

                        $issues++;
                        $annotations[] = [
                            'path' => $file['name']->__toString(),
                            'start_line' => intval($violation['line']->__toString()),
                            'end_line' => intval($violation['line']->__toString()),
                            'annotation_level' => $phpstan_to_github[$violation['severity']->__toString()],
                            'message' => trim($violation['message']->__toString()),
                        ];
                    }
                }
                if (0 === $issues) {
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'success',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'No issues found',
                                'summary' => 'PHPStan did not find any issues.',
                            ],
                        ],
                        'reporting phpstan check success',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                } else {
                    $total_annotations = count($annotations);
                    $chunks = array_chunk($annotations, 50);
                    for ($i = 0; $i < ($total_annotations / 50); $i++) {
                        github(
                            $payload['check_run']['url'],
                            [
                                'conclusion' => 'failure',
                                'completed_at' => date(DATE_ATOM),
                                'details_url' => $plain_log_url,
                                'output' => [
                                    'title' => 'Found ' . $issues . ' issue' . ( 1 === $issues ? '' : 's') . ' in '
                                        . $files_with_issues . ' file' . ( 1 === $files_with_issues ? '' : 's' ),
                                    'summary' => (1 === $issues ? 'An issue was' : 'Issues were')
                                        . ' found in the below file' . ( 1 === $files_with_issues ? '' : 's' ) . '.',
                                    'annotations' => $chunks[$i],
                                ],
                            ],
                            'reporting phpstan check failure',
                            'application/vnd.github.antiope-preview+json',
                            'PATCH',
                            200
                        );
                    }
                }
                break;
            case 'phan':
                echo "Phan tends to take a very long time, so we're closing the connection before it finishes.";
                fastcgi_finish_request();
                passthru(
                    '/bin/bash -x -e -o pipefail ' . __DIR__ . '/../phan.sh ' . $payload['repository']['name'] . ' '
                    . $log_location . '/output.json > ' . $log_file . ' 2>&1',
                    $return_value
                );
                if (0 !== $return_value && 1 !== $return_value) {
                    echo 'phan check_run failed with return value ' . $return_value;
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'action_required',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'Phan exited with an unexpected return code ' . $return_value,
                                'summary' => 'Please review the output.',
                            ],
                        ],
                        'reporting phan check_run failure',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                    exit;
                }
                $log = file_get_contents($log_location . '/output.json');
                if (false === $log) {
                    http_response_code(500);
                    exit('Could not read phan output.');
                }
                $log = json_decode($log, true);
                $files_with_issues = 0;
                $files = [];
                $issues = 0;
                $annotations = [];
                foreach ($log as $message) {
                    $issues++;
                    $annotations[] = [
                        'path' => $message['location']['path'],
                        'start_line' => $message['location']['lines']['begin'],
                        'end_line' => $message['location']['lines']['end'],
                        'annotation_level' => 'failure',
                        'message' => str_replace(
                            '/var/tmp/php-checks/' . $payload['repository']['name'],
                            '',
                            $message['description']
                        ),
                    ];
                    if (in_array($message['location']['path'], $files)) {
                        continue;
                    }

                    $files[] = $message['location']['path'];
                }
                $files_with_issues = count($files);
                if (0 === $issues) {
                    github(
                        $payload['check_run']['url'],
                        [
                            'conclusion' => 'success',
                            'completed_at' => date(DATE_ATOM),
                            'details_url' => $plain_log_url,
                            'output' => [
                                'title' => 'No issues found',
                                'summary' => 'Phan did not find any issues.',
                            ],
                        ],
                        'reporting phan check success',
                        'application/vnd.github.antiope-preview+json',
                        'PATCH',
                        200
                    );
                } else {
                    $total_annotations = count($annotations);
                    $chunks = array_chunk($annotations, 50);
                    for ($i = 0; $i < ($total_annotations / 50); $i++) {
                        github(
                            $payload['check_run']['url'],
                            [
                                'conclusion' => 'failure',
                                'completed_at' => date(DATE_ATOM),
                                'details_url' => $plain_log_url,
                                'output' => [
                                    'title' => 'Found ' . $issues . ' issue' . ( 1 === $issues ? '' : 's') . ' in '
                                        . $files_with_issues . ' file' . ( 1 === $files_with_issues ? '' : 's' ),
                                    'summary' => ( 1 === $issues ? 'An issue was' : 'Issues were' )
                                        . ' found in the below file' . ( 1 === $files_with_issues ? '' : 's' ) . '.',
                                    'annotations' => $chunks[$i],
                                ],
                            ],
                            'reporting phan check failure',
                            'application/vnd.github.antiope-preview+json',
                            'PATCH',
                            200
                        );
                    }
                }
                break;
        }
        check_run_finish();
        break;
    case 'check_suite':
        if ('requested' !== $payload['action'] && 'rerequested' !== $payload['action']) {
            echo 'Action is ' . $payload['action'] . ', ignoring';
            exit;
        }

        $log_location = __DIR__ . '/' . $payload['repository']['name'] . '/' . $payload['check_suite']['head_sha']
            . '/composer';
        $log_file = $log_location . '/plain.txt';
        $plain_log_url = 'https://' . $_SERVER['SERVER_NAME'] . '/' . $url_prefix . $payload['repository']['name'] . '/'
            . $payload['check_suite']['head_sha'] . '/composer/plain.txt';
        mkdir($log_location, 0700, true);
        copy(__DIR__ . '/../log-index.html', $log_location . '/index.html');
        copy(__DIR__ . '/../worker.js', $log_location . '/worker.js');
        copy(__DIR__ . '/../app.js', $log_location . '/app.js');

        $token = token();

        $response = github(
            $payload['repository']['url'] . '/check-runs',
            [
                'name' => 'Composer Install',
                'head_sha' => $payload['check_suite']['head_sha'],
                'details_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $url_prefix
                    . $payload['repository']['name'] . '/' . $payload['check_suite']['head_sha'] . '/composer/',
                'external_id' => 'composer',
            ],
            'creating check run for composer',
            'application/vnd.github.antiope-preview+json'
        );

        $return_value = 0;
        passthru(
            '/bin/bash -x -e -o pipefail ' . __DIR__ . '/../checkout.sh ' . $payload['repository']['name'] . ' '
            . add_access_token($payload['repository']['clone_url']) . ' ' . $payload['check_suite']['head_sha']
            . ' >> ' . $log_file . ' 2>&1',
            $return_value
        );
        if (0 !== $return_value) {
            github(
                $response['url'],
                [
                    'conclusion' => 'failure',
                    'completed_at' => date(DATE_ATOM),
                    'details_url' => $plain_log_url,
                    'output' => [
                        'title' => 'Composer failed to install dependencies',
                        'summary' => 'Please review the output.',
                    ],
                ],
                'reporting composer install failure',
                'application/vnd.github.antiope-preview+json',
                'PATCH',
                200
            );

            echo 'Checkout failed with return value ' . $return_value . ', see output above.';
            exit;
        }

        github(
            $response['url'],
            [
                'conclusion' => 'success',
                'completed_at' => date(DATE_ATOM),
                'details_url' => $plain_log_url,
                'output' => [
                    'title' => 'Composer successfully installed dependencies',
                    'summary' => 'All required dependencies were successfully installed.',
                ],
            ],
            'reporting composer check success',
            'application/vnd.github.antiope-preview+json',
            'PATCH',
            200
        );

        foreach ($checks as $name => $external_id) {
            github(
                $payload['repository']['url'] . '/check-runs',
                [
                    'name' => $name,
                    'head_sha' => $payload['check_suite']['head_sha'],
                    'details_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $url_prefix
                        . $payload['repository']['name'] . '/' . $payload['check_suite']['head_sha'] . '/'
                        . $external_id . '/',
                    'external_id' => $external_id,
                ],
                'creating check run for ' . $external_id,
                'application/vnd.github.antiope-preview+json'
            );
        }
        break;
    default:
        echo 'Unrecognized event ' . $_SERVER['HTTP_X_GITHUB_EVENT'];
        break;
}
