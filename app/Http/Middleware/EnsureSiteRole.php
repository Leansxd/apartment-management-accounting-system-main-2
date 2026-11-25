<?php

namespace App\Http\Middleware;

use App\Models\Apartment;
use App\Models\User;
use App\Support\SitePermissions;
use Closure;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Text;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sitedeki rol ve modül izinleri: yönetici her şeyi yapar; muhasebeci ve izleyici her modülde site ayarındaki
 * seviyeye (erişim yok / görüntüleme / işlem) göre sayfayı açar veya kayıt değiştirir. Yönetici olmayanlara
 * dönen sayfadan yetkisi olmayan formlar ve açamayacağı sayfalara giden bağlantılar çıkarılır.
 * Site erişimi ayrıca 'can:manage' ile denetlenir.
 */
class EnsureSiteRole
{
    public function __construct(private Router $router) {}

    public function handle(Request $request, Closure $next): Response
    {
        $apartment = $request->route('apartment');
        if (! $apartment instanceof Apartment) {
            return $next($request);
        }

        $user = $request->user();
        $role = $apartment->roleOf($user);
        abort_if($role === null, 403, 'Bu siteye erişiminiz yok.');
        if ($role === 'manager') {
            return $next($request);
        }

        $write = ! $request->isMethodSafe();
        if (! SitePermissions::allows($apartment, $user, (string) $request->route()->getName(), $write)) {
            abort(403, match (true) {
                ! $write => 'Bu modülü görüntüleme yetkiniz yok.',
                $role === 'viewer' => 'İzleyici rolü bu işlemi yapamaz.',
                default => 'Bu işlem için yetkiniz yok (muhasebecinin yetkileri site ayarlarından belirlenir).',
            });
        }

        return $this->stripForbiddenActions($next($request), $apartment, $user);
    }

    private function stripForbiddenActions(Response $response, Apartment $apartment, User $user): Response
    {
        $html = $response->getContent();
        if (! $response instanceof HttpResponse || ! is_string($html) || $html === ''
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        $cache = [];
        $allowed = function (?string $url, string $method) use (&$cache, $apartment, $user): bool {
            return $cache["{$method} {$url}"] ??= $this->allowsUrl((string) $url, $method, $apartment, $user);
        };

        $changed = false;
        foreach (iterator_to_array($document->querySelectorAll('form')) as $form) {
            $method = strtoupper($form->getAttribute('method') ?: 'GET');
            if ($method === 'GET') {
                continue;
            }
            $spoofed = $form->querySelector('input[name="_method"]')?->getAttribute('value');
            if (! $allowed($form->getAttribute('action'), strtoupper($spoofed ?: $method))) {
                $this->removeWithEmptyContainer($form);
                $changed = true;
            }
        }
        foreach (iterator_to_array($document->querySelectorAll('a[href]')) as $link) {
            if (! $allowed($link->getAttribute('href'), 'GET')) {
                $link->replaceWith(...iterator_to_array($link->childNodes));
                $changed = true;
            }
        }

        if ($changed) {
            $response->setContent($document->saveHtml());
        }

        return $response;
    }

    /** Yalnızca bu sitenin rotaları denetlenir; çözülemeyen veya başka siteye ait adresler olduğu gibi kalır. */
    private function allowsUrl(string $url, string $method, Apartment $apartment, User $user): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, "/sites/{$apartment->id}/")) {
            return true;
        }

        try {
            $route = $this->router->getRoutes()->match(Request::create($path, $method));
        } catch (HttpException) {
            return true;
        }

        return SitePermissions::allows($apartment, $user, (string) $route->getName(), $method !== 'GET');
    }

    /** Formu siler; içinde formdan başka bir şey kalmayan kart veya açılır panel de kaldırılır. */
    private function removeWithEmptyContainer(Element $form): void
    {
        $container = $form->parentElement?->closest('details, section.card, div.card');
        $form->remove();
        if ($container && $this->isEmptyContainer($container)) {
            $container->remove();
        }
    }

    private function isEmptyContainer(Element $container): bool
    {
        foreach ($container->childNodes as $node) {
            if ($node instanceof Text && trim($node->textContent) !== '') {
                return false;
            }
            if (! $node instanceof Element || $node->matches('summary, .card-head')) {
                continue;
            }
            $content = 'table, form, a, ul, ol, img, canvas, input, select, textarea, button';
            if (trim($node->textContent) !== '' || $node->matches($content) || $node->querySelector($content)) {
                return false;
            }
        }

        return true;
    }
}
