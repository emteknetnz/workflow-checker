<?php

function req($url, $allowFail = false) {
    global $token;
    echo "Making curl request to $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github+json',
        "Authorization: Bearer $token",
        "X-GitHub-Api-Version: 2022-11-28",
        "User-Agent: PHP",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    if ($info['http_code'] !== 200) {
        if ($allowFail) {
            return false;
        }
        echo "Failed to get $url\n";
        echo $response;
        die;
    }
    return $response;
}

$token = getenv('GITHUB_TOKEN');
if (!$token) {
    echo "Please set GITHUB_TOKEN environment variable\n";
    exit(1);
}

if (!file_exists('repositories.json')) {
    $url = 'https://raw.githubusercontent.com/silverstripe/supported-modules/main/repositories.json';
    $contents = file_get_contents($url);
    file_put_contents('repositories.json', $contents);
}

$defaultBranchMissingWorkflows = [];

$contents = file_get_contents('repositories.json');
$json = json_decode($contents, true);
foreach (array_keys($json) as $type) {
    foreach ($json[$type] as $repo) {
        $ghrepo = $repo['github'];
        $hasWildcard = array_key_exists('*', $repo['majorVersionMapping']);
        if ($hasWildcard) {
            continue;
        }
        // just assume that all modules on the silverstripe account are already correct
        if (strpos($ghrepo, 'silverstripe/') === 0) {
            continue;
        }
        // get the default branch using github api using curl
        $url = "https://api.github.com/repos/$ghrepo";
        $response = req($url);
        $data = json_decode($response, true);
        $defaultBranch = $data['default_branch'];

        // check if .github/workflows/merge-up.yml exists on the default branch
        $url = "https://api.github.com/repos/$ghrepo/contents/.github/workflows/merge-up.yml?ref=$defaultBranch";
        $contents = req($url, true);
        if (!$contents) {
            $defaultBranchMissingWorkflows[] = $ghrepo;
            continue;
        }

        // check if .github/workflows/dispatch-ci.yml exists on the default branch
        $url = "https://api.github.com/repos/$ghrepo/contents/.github/workflows/dispatch-ci.yml?ref=$defaultBranch";
        $contents = req($url, true);
        if (!$contents) {
            $defaultBranchMissingWorkflows[] = $ghrepo;
        }
    }
}

echo "The following non-silverstripe repos missing workflows on the default branch:\n";
foreach ($defaultBranchMissingWorkflows as $repo) {
    echo "$repo\n";
}
