<?php

declare(strict_types=1);

namespace Yii3\Inertia;

use PHPForge\Inertia\Page;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Json\Json;
use Yiisoft\View\WebView;

/**
 * Renders the initial Inertia HTML document through the application web view.
 *
 * Asset tags are an application concern. Register them on the shared {@see WebView} before the response is rendered,
 * or replace the root view entirely; the default root view marks Yii's head and body placeholders so registered
 * stylesheets, scripts, and links are injected during {@see WebView::endPage()}.
 *
 * Usage example:
 * ```php
 * $view->registerCssFile('/build/app.css');
 * $view->registerJsFile('/build/app.js', options: ['type' => 'module']);
 *
 * $html = $renderer->render($page, ['activeMenu' => 'dashboard']);
 * ```
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
