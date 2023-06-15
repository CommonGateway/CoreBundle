<?php

/**
 * Fetches repository metadata from the GitHub API.
 *
 * @param string $token  The GitHub token
 * @return array The repository metadata
 */
function get_repo_metadata($token) {
    $repo_slug = getenv('GITHUB_REPOSITORY');
    $api_url = "https://api.github.com/repos/$repo_slug";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: token $token", "User-Agent: GitHub-Action"));
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

/**
 * Generates a publiccode.yml file from the repository metadata.
 *
 * @param array $metadata The repository metadata
 * @return array
 */
function generatePubliccode($metadata) {
    $publiccode = [
        'name' => $metadata['name'],
        'description' => [
            'en' => $metadata['description']
        ],
        'url' => $metadata['homepage'],
        'releaseDate' =>  $metadata['updated_at'],
        'categories' => $metadata['topics'],
        'codeRepository' => $metadata['html_url'],
        'license' => $metadata['license']['spdx_id'],
        'mainCopyrightOwner' => $metadata['owner']['login'],
        'repoOwner' => $metadata['owner']['login']
    ];

    // Check if files exist
    if (file_exists('AUTHORS.md')) {
        $publiccode['authorsFile'] = 'https://github.com/' . $metadata['full_name'] . '/blob/main/AUTHORS.md';

    }
    if (file_exists('ROADMAP.md')) {
        $publiccode['roadmap'] = 'https://github.com/' . $metadata['full_name'] . '/blob/main/ROADMAP.md';

    }

    // Lets set some defaults
    if(isset($publiccode['publiccode-yml-version']) === false){
        $publiccode['publiccode-yml-version'] = '0.2';
    }
    if(isset($publiccode['softwareType']) === false){
        $publiccode['softwareType'] = 'standalone';
    }
    if(isset($publiccode['developmentStatus']) === false){
        $publiccode['developmentStatus'] = 'development';
    }
    if(isset($publiccode['softwareVersion']) === false){
        $publiccode['softwareVersion'] = '0.1';
    }
    if(isset($publiccode['platforms']) === false) {
        $publiccode['platforms'] = 'linux', 'windows', 'macos';
    }
    if(isset($publiccode['url']) === false){
        $publiccode['url'] = $metadata['homepage'];
    }
    if(isset($publiccode['intendedAudience']) === false){
        $publiccode['intendedAudience'] = [
            'scope' => 'worldwide',
            'countries' => []
        ];
    }
    if(isset($publiccode['maintenance']) === false){
        $publiccode['maintenance'] = [
            'type' => 'community',
            'contacts' => [
                [
                    'name' => $metadata['owner']['login']
                ]
            ]
        ];
    }
    if(isset($publiccode['localisation']) === false){
        $publiccode['localisation'] = [
            'localisationReady' => true,
            'availableLanguages' => ['en']
        ];
    }

    return $publiccode;
}

/**
 * Get the array content of the current yaml file or an empty array
 *
 * @param $filename
 * @return array
 */
function getPubliccode($filename = '../publiccode.yml') {
    if (file_exists($filename)) {
        $yaml = yaml_parse_file($filename);
        if ($yaml === FALSE) {
            // Error during parsing, return an empty array
            return [];
        } else {
            // Successful parse, return the parsed data
            return $yaml;
        }
    } else {
        // File does not exist, return an empty array
        return [];
    }
}

// Get the github stuf
$token = getenv('GITHUB_TOKEN');
$metadata = get_repo_metadata($token);

// Get the old publiccode values
$oldPubliccode = getPubliccode();
// Create the new publiccode values
$publiccode = generatePubliccode($metadata, $oldPubliccode);

// Turn it into a yaml and post it to the repro
$publiccode_yaml = yaml_emit($publiccode);
file_put_contents('../publiccode.yml', $publiccode_yaml);
