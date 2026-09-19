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
    /**
     * Character set declared by the root view.
     */
    private string $charset = 'UTF-8';

    /**
     * Value of the `id` attribute of the root element the client application mounts on.
     */
    private string $id = 'app';

    /**
     * Language declared by the root view.
     */
    private string $language = 'en';

    /**
     * Alias or path of the root view template.
     */
    private string $rootView = '@yii3InertiaViews/app.php';

    /**
     * Document title passed to the root view.
     */
    private string $title = 'Yii3 Inertia';

    /**
     * Creates a new instance.
     *
     * @param Aliases $aliases Alias resolver expanding the root view path.
     * @param WebView $view Web view rendering the root template and injecting the registered asset tags.
     */
    public function __construct(
        private readonly Aliases $aliases,
        private readonly WebView $view,
    ) {}

    /**
     * Renders the root view with the page payload and the document settings.
     *
     * @param Page $page Resolved page handed to the root view, both as an object and as its JSON encoding.
     * @param array<string, mixed> $viewData Extra variables exposed to the root view, expanded and as `viewData`.
     *
     * @throws \JsonException when the page cannot be encoded as JSON.
     *
     * @return string Rendered HTML document.
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

    /**
     * Returns a new instance with the specified character set.
     *
     * @param string $charset Character set declared by the root view.
     *
     * @return self New instance with the specified character set.
     */
    public function withCharset(string $charset): self
    {
        $new = clone $this;
        $new->charset = $charset;

        return $new;
    }

    /**
     * Returns a new instance with the specified root element identifier.
     *
     * @param string $id Value of the `id` attribute of the root element the client application mounts on.
     *
     * @return self New instance with the specified root element identifier.
     */
    public function withId(string $id): self
    {
        $new = clone $this;
        $new->id = $id;

        return $new;
    }

    /**
     * Returns a new instance with the specified language.
     *
     * @param string $language Language declared by the root view.
     *
     * @return self New instance with the specified language.
     */
    public function withLanguage(string $language): self
    {
        $new = clone $this;
        $new->language = $language;

        return $new;
    }

    /**
     * Returns a new instance with the specified root view template.
     *
     * @param string $rootView Alias or path of the root view template.
     *
     * @return self New instance with the specified root view template.
     */
    public function withRootView(string $rootView): self
    {
        $new = clone $this;
        $new->rootView = $rootView;

        return $new;
    }

    /**
     * Returns a new instance with the specified document title.
     *
     * @param string $title Document title passed to the root view.
     *
     * @return self New instance with the specified document title.
     */
    public function withTitle(string $title): self
    {
        $new = clone $this;
        $new->title = $title;

        return $new;
    }
}
