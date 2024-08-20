<?php
#####################################################################
# Config
#####################################################################
$webhookurl = "YOU-WEBHOOK-HERE";
$hiddenMessage = "This change has been marked as private.";
$reactions = ['🔥', '🎉', '🚀', '❤', '😩', '👌', '😚', '🤔', '👀'];
$thumbnailUrl = "YOUR-PNG-IMAGE-HERE";
$spaceGap = "‎";  // Invisible character used for spacing // not sure if this works but too lazy to check.
$privateCommitMarker = ";";  // Marker to identify private commits
$commitUrlLength = 7;  // Default length of the commit ID to display

#####################################################################
# Functions
#####################################################################
function retrieveJsonPostData($cached = FALSE) {
    $rawData = $cached ? file_get_contents($cached) : file_get_contents("php://input");
    return json_decode($rawData, false, 512, JSON_THROW_ON_ERROR);
}

function addReactions($webhookurl, $messageId, $reactions) {
    foreach ($reactions as $reaction) {
        $reactionUrl = "$webhookurl/messages/$messageId/reactions/$reaction/@me";
        $ch = curl_init($reactionUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}

function getRandomReaction($reactions) {
    return $reactions[array_rand($reactions)];
}

function getFormattedBranch($repo, $branch) {
    switch ($branch) {
        case 'dev':
            return "$repo - (Development) ";
        case 'main':
            return "$repo - (Public) ";
        default:
            return "$repo: $branch ";
    }
}

#####################################################################
# Main
#####################################################################
$data = retrieveJsonPostData();

if (empty($data->commits)) {
    die; // No commits to process
}

$repo = $data->repository->name;
$repourl = $data->repository->html_url;
$branch = basename($data->ref);

$authorName = $data->sender->login;
$authorAvatar = $data->sender->avatar_url;
$randomEmoji = getRandomReaction($reactions);
$formattedBranch = getFormattedBranch($repo, $branch);

$description = "";

foreach ($data->commits as $commit) {
    $committitle = strtok($commit->message, "\n");  // First line as title
    $fullDescription = trim(str_replace($committitle, '', $commit->message)); // Remove the title from the full description
    $shortCommitId = substr($commit->id, 0, $commitUrlLength);  // Shorten the commit ID to the desired length

    if (strpos($commit->message, $privateCommitMarker) !== false) {
        $description .= "- [{$shortCommitId}]({$commit->url}) - $hiddenMessage\n";
    } else {
        $description .= "- [{$shortCommitId}]({$commit->url}) - {$committitle}\n";
        if (!empty($fullDescription) && $committitle !== $fullDescription) {
            $description .= "↳ " . (strlen($fullDescription) > 2000 ? substr($fullDescription, 0, 2000) . "... (cont.)" : $fullDescription) . "\n";
        }
    }
}

$description = trim($description);

$json_data = json_encode([
    "embeds" => [
        [
            "author" => [
                "name" => $authorName,
                "icon_url" => $authorAvatar
            ],
            "thumbnail" => [
                "url" => $thumbnailUrl
            ],
            "title" => "$formattedBranch$spaceGap$randomEmoji",
            "type" => "rich",
            "description" => $description,
            "url" => $repourl,
            "color" => hexdec("0296e5"),
        ]
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

$ch = curl_init($webhookurl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// Add reactions if needed
$responseObj = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
if (isset($responseObj->id)) {
    addReactions($webhookurl, $responseObj->id, $reactions);
}

#####################################################################
# End of File
#####################################################################
?>
