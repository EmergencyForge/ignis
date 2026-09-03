<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Pipeline;
use App\Http\RedirectException;
use App\Http\Request;
use App\Http\Requests\FormRequest;
use App\Http\Response;
use App\Http\Router;
use App\Http\RouterFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Der Router aus der Factory trägt die Haken, die ignis braucht: eine
 * RedirectException aus dem Handler wird zur Weiterleitung, Fragment-
 * Aufrufer (X-Requested-With: fragment) bekommen statt 3xx eine leere
 * 200-Antwort mit dem Ziel in X-Ignis-Location, und der Old-Input-
 * Zwischenspeicher beginnt mit jedem Dispatch frisch.
 */
final class RouterFactoryTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = RouterFactory::create($this->container, new Pipeline($this->container), cache: false);
    }

    #[Test]
    public function redirect_exception_wird_zur_weiterleitung(): void
    {
        $this->router->get('/go', static function (): Response {
            throw new RedirectException('/ziel');
        });

        $res = $this->router->dispatch(new Request('GET', '/go'));

        $this->assertSame(302, $res->status);
        $this->assertSame('/ziel', $res->headers['Location']);
    }

    #[Test]
    public function fragment_aufrufer_bekommen_das_ziel_im_header(): void
    {
        $this->router->post('/save', static fn (): Response => Response::redirect('/form'));

        $res = $this->router->dispatch(new Request('POST', '/save', server: ['HTTP_X_REQUESTED_WITH' => 'fragment']));

        $this->assertSame(200, $res->status);
        $this->assertSame('', $res->body);
        $this->assertSame('/form', $res->headers['X-Ignis-Location']);
        $this->assertArrayNotHasKey('Location', $res->headers);
        $this->assertSame('no-store', $res->headers['Cache-Control']);
    }

    #[Test]
    public function ohne_header_bleibt_die_weiterleitung(): void
    {
        $this->router->post('/save', static fn (): Response => Response::redirect('/form'));

        $res = $this->router->dispatch(new Request('POST', '/save'));

        $this->assertSame(302, $res->status);
        $this->assertSame('/form', $res->headers['Location']);
        $this->assertArrayNotHasKey('X-Ignis-Location', $res->headers);
    }

    #[Test]
    public function andere_antworten_bleiben_unberuehrt(): void
    {
        $this->router->get('/page', static fn (): Response => Response::html('<p>hi</p>'));

        $res = $this->router->dispatch(new Request('GET', '/page', server: ['HTTP_X_REQUESTED_WITH' => 'fragment']));

        $this->assertSame(200, $res->status);
        $this->assertSame('<p>hi</p>', $res->body);
        $this->assertArrayNotHasKey('X-Ignis-Location', $res->headers);
    }

    #[Test]
    public function old_input_gilt_genau_fuer_den_naechsten_request(): void
    {
        $sessionBefore = $_SESSION ?? [];
        $_SESSION = [];
        try {
            $this->router->post('/form', static function (): Response {
                FormRequest::rememberInput(['title' => 'Bremsen', 'csrf_token' => 'x', 'password' => 'geheim']);
                return Response::redirect('/form');
            });
            $this->router->get('/form', static fn (): Response => Response::text((string) old('title', '-') . '|' . (string) old('csrf_token', '-') . '|' . (string) old('password', '-')));

            $this->router->dispatch(new Request('POST', '/form'));
            $this->assertSame('Bremsen|-|-', $this->router->dispatch(new Request('GET', '/form'))->body);
            $this->assertSame('-|-|-', $this->router->dispatch(new Request('GET', '/form'))->body, 'Der Bag ist nach einem Request verbraucht.');
        } finally {
            $_SESSION = $sessionBefore;
        }
    }
}
