<?php

declare(strict_types=1);

use Yiisoft\View\WebView;

/**
 * @var string $charset
 * @var string $id
 * @var string $language
 * @var string $pageJson
 * @var string $title
 * @var WebView $this
 */
$encode = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, $charset);

$this->beginPage();
?>
<!DOCTYPE html>
<html lang="<?= $encode($language) ?>">
<head>
    <meta charset="<?= $encode($charset) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title data-inertia><?= $encode($title) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>
    <script data-page="<?= $encode($id) ?>" type="application/json"><?= $pageJson ?></script>
    <div id="<?= $encode($id) ?>"></div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage();
