<?php

declare(strict_types=1);

/**
 * @var string $charset
 * @var string $id
 * @var string $language
 * @var string $pageJson
 * @var string $title
 * @var string $viteTags
 */
$encode = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
?>
<!DOCTYPE html>
<html lang="<?= $encode($language) ?>">
<head>
    <meta charset="<?= $encode($charset) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title data-inertia><?= $encode($title) ?></title>
    <?= $viteTags ?>
</head>
<body>
    <script data-page="<?= $encode($id) ?>" type="application/json"><?= $pageJson ?></script>
    <div id="<?= $encode($id) ?>"></div>
</body>
</html>
