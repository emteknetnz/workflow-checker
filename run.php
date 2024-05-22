<?php

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

$nonNumericRepos = [];

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
            echo "Failed to get $url\n";
            var_dump($response);
            die;
        }
        $data = json_decode($response, true);
        $defaultBranch = $data['default_branch'];
        
        print_r([
            'github' => $ghrepo,
            'defaultBranch' => $defaultBranch
        ]);

        // check if default branch is numeric
        if (is_numeric($defaultBranch)) {
            echo "Skipping $ghrepo as default branch is numeric\n";
            continue;
        }
        $nonNumericRepos[] = $ghrepo;
    }
}

echo "The following nonsilverstripe repos are non-numeric:\n";
foreach ($nonNumericRepos as $repo) {
    echo "$repo\n";
}
