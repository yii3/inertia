<?php

declare(strict_types=1);

namespace Yii3\Inertia;

use PHPForge\Inertia\Page;
use PHPForge\Vite\Html\HtmlRenderer;
use PHPForge\Vite\Vite;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Json\Json;
use Yiisoft\View\WebView;

/**
 * Renders the common initial Inertia HTML document.
 */
final class RootViewRenderer
{
    private string $charset = 'UTF-8';
    private string $id = 'app';
    private string $language = 'en';
    private string $rootView = '@yii3InertiaViews/app.php';
    private string $title = 'Yii3 Inertia';

    public function __construct(
        private readonly Aliases $aliases,
        private readonly WebView $view,
        private readonly Vite $vite,
    ) {}

    /**
     * @param array<string, mixed> $viewData
     */
    public function render(Page $page, array $viewData = []): string
    {
        return $this->view->render(
            $this->aliases->get($this->rootView),
            [
                ...$viewData,
                'viewData' => $viewData,
                'id' => $this->id,
                'language' => $this->language,
                'charset' => $this->charset,
                'title' => $this->title,
                'page' => $page,
                'pageJson' => Json::htmlEncode($page),
                'viteTags' => HtmlRenderer::create()->render($this->vite->resolve()),
            ],
        );
    }

    public function withCharset(string $charset): self
    {
        $new = clone $this;
        $new->charset = $charset;

        return $new;
    }

    public function withId(string $id): self
    {
        $new = clone $this;
        $new->id = $id;

        return $new;
    }

    public function withLanguage(string $language): self
    {
        $new = clone $this;
        $new->language = $language;

        return $new;
    }

    public function withRootView(string $rootView): self
    {
        $new = clone $this;
        $new->rootView = $rootView;

        return $new;
    }

    public function withTitle(string $title): self
    {
        $new = clone $this;
        $new->title = $title;

        return $new;
    }
}
