<?php

namespace Eduthon\Installer;

use Eduthon\Installer\Checks\Requirements;
use Eduthon\Installer\Engine\EngineException;
use Eduthon\Installer\Http\Request;
use Eduthon\Installer\Http\Response;
use Eduthon\Installer\Http\Session;
use Eduthon\Installer\Installation\Pipeline;
use Eduthon\Installer\Installation\Run;
use Eduthon\Installer\Installation\TaskFailed;
use Eduthon\Installer\Support\Container;
use Throwable;

/**
 * Routes installer requests. Pages are addressed with ?page=… and
 * background steps with ?action=….
 */
final class App
{
    private Request $request;

    private Session $session;

    public function __construct(private Container $app, ?Request $request = null, ?Session $session = null)
    {
        $this->request = $request ?? Request::capture();
        $this->session = $session ?? new Session;
    }

    public function handle(): void
    {
        $this->respond()->send();
    }

    public function respond(): Response
    {
        $this->session->start($this->request->isSecure(), $this->request->installerPath());

        try {
            if ($this->request->isPost() && ! $this->session->validCsrf($this->request->input('_token', (string) $this->request->header('X-CSRF-Token')))) {
                return $this->request->wantsJson()
                    ? Response::json(['status' => 'failed', 'message' => 'Your session expired. Reload the page.'], 419)
                    : $this->redirect('', ['error' => 'Your session expired. Please try again.']);
            }

            $action = $this->request->query('action');

            if ($action !== null && $this->request->isPost()) {
                return match ($action) {
                    'task' => $this->runTask(),
                    'cancel' => $this->cancel(),
                    'signout' => $this->signOut(),
                    default => $this->notFound(),
                };
            }

            return match ($this->request->query('page', $this->defaultPage())) {
                'welcome' => $this->welcome(),
                'requirements' => $this->requirementsPage(),
                'license' => $this->request->isPost() ? $this->submitLicense() : $this->licensePage(),
                'review' => $this->request->isPost() ? $this->startRun() : $this->reviewPage(),
                'install' => $this->installPage(),
                'done' => $this->donePage(),
                'manage' => $this->managePage(),
                default => $this->notFound(),
            };
        } catch (Throwable $exception) {
            $this->app->log->error('Unhandled: '.$exception->getMessage(), ['exception' => $exception::class, 'at' => $exception->getFile().':'.$exception->getLine()]);

            return $this->view('error', [
                'title' => 'Something went wrong',
                'message' => $exception instanceof EngineException ? $exception->getMessage() : 'The installer hit an unexpected problem. Details were written to the installer log.',
            ], 500);
        }
    }

    private function defaultPage(): string
    {
        $run = Run::current($this->app->store);

        if ($run !== null && $this->session->get('token')) {
            return $run->isComplete() ? 'done' : 'install';
        }

        return $this->app->state->isInstalled() ? 'manage' : 'welcome';
    }

    private function welcome(): Response
    {
        if ($this->app->state->isInstalled()) {
            return $this->redirect('page=manage');
        }

        return $this->view('welcome', ['title' => 'Install your Eduthon portal', 'step' => 'welcome']);
    }

    private function requirementsPage(): Response
    {
        $checks = $this->checks();

        return $this->view('requirements', [
            'title' => 'Server check',
            'step' => 'requirements',
            'checks' => $checks,
            'passes' => Requirements::passes($checks),
            'hasKeys' => $this->app->verifier->hasTrustedKeys(),
        ]);
    }

    private function licensePage(): Response
    {
        if (! Requirements::passes($this->checks()) || ! $this->app->verifier->hasTrustedKeys()) {
            return $this->redirect('page=requirements');
        }

        return $this->view('license', [
            'title' => $this->mode() === 'update' ? 'Confirm your license' : 'Enter your license',
            'step' => 'license',
            'purchaseCode' => $this->session->pull('purchase_code', $this->app->state->get('purchase_code', '')),
            'bindUrl' => $this->bindUrl(),
        ]);
    }

    private function submitLicense(): Response
    {
        $code = strtoupper($this->request->input('purchase_code'));
        $secret = $this->request->input('secret_key');

        if ($code === '' || $secret === '') {
            $this->session->flash('purchase_code', $code);

            return $this->redirect('page=license', ['error' => 'Enter both your purchase code and secret key.']);
        }

        $installedCode = $this->app->state->get('purchase_code');

        if ($installedCode !== null && strcasecmp($installedCode, $code) !== 0) {
            return $this->redirect('page=license', ['error' => 'This website is already installed with a different purchase code.']);
        }

        try {
            $auth = $this->app->engine()->authenticate($secret);
            $engine = $this->app->engine($auth['token']);
            $engine->verify($code, $this->bindUrl());
            $config = $engine->installerConfig();
        } catch (EngineException $exception) {
            $this->app->log->error('License check failed: '.$exception->getMessage(), ['status' => $exception->status]);
            $this->session->flash('purchase_code', $code);

            return $this->redirect('page=license', ['error' => $exception->getMessage()]);
        }

        $this->session->regenerate();
        $this->session->put('token', $auth['token']);
        $this->session->put('purchase_code', $code);
        $this->session->put('config', $config);
        $this->app->log->info('License confirmed', ['school' => $config['school']['name'] ?? null]);

        return $this->redirect('page=review');
    }

    private function reviewPage(): Response
    {
        [$engine, $config] = $this->authenticated();

        if ($engine === null) {
            return $this->redirect('page=license', ['error' => 'Enter your license details to continue.']);
        }

        $current = $this->app->state->get('version');

        try {
            $release = $engine->latestRelease((string) $this->app->config->get('component'), $config['releases']['channel'] ?? null, $current);
        } catch (EngineException $exception) {
            return $this->view('error', ['title' => 'Could not check for releases', 'message' => $exception->getMessage(), 'step' => 'review'], 502);
        }

        return $this->view('review', [
            'title' => $this->mode() === 'update' ? 'Review the update' : 'Review and install',
            'step' => 'review',
            'config' => $config,
            'release' => $release,
            'current' => $current,
            'portalUrl' => $this->request->portalUrl(),
            'target' => $this->app->paths->target,
            'existing' => $this->mode() === 'install' ? $this->app->deployer->existingEntries() : [],
        ]);
    }

    private function startRun(): Response
    {
        [$engine, $config] = $this->authenticated();

        if ($engine === null) {
            return $this->redirect('page=license', ['error' => 'Your session expired. Enter your license details again.']);
        }

        if (! ($config['backend']['ready'] ?? false)) {
            return $this->redirect('page=review', ['error' => 'Your school backend is not ready yet.']);
        }

        try {
            $release = $engine->latestRelease((string) $this->app->config->get('component'), $config['releases']['channel'] ?? null, null);
        } catch (EngineException $exception) {
            return $this->redirect('page=review', ['error' => $exception->getMessage()]);
        }

        if ($release === null) {
            return $this->redirect('page=review', ['error' => 'Delwathon has not published a portal release yet.']);
        }

        Run::current($this->app->store)?->discard();
        Run::start($this->app->store, $this->mode(), $config, $release, $this->request->portalUrl(), (string) $this->session->get('purchase_code'));

        return $this->redirect('page=install');
    }

    private function installPage(): Response
    {
        $run = Run::current($this->app->store);

        if ($run === null) {
            return $this->redirect('');
        }

        return $this->view('install', [
            'title' => $run->get('mode') === 'update' ? 'Updating your portal' : 'Installing your portal',
            'step' => 'install',
            'mode' => $run->get('mode'),
            'run' => $run,
            'tasks' => Pipeline::TASKS,
            'canContinue' => (bool) $this->session->get('token'),
        ]);
    }

    private function runTask(): Response
    {
        $run = Run::current($this->app->store);
        [$engine] = $this->authenticated();

        if ($run === null || $engine === null) {
            return Response::json(['status' => 'failed', 'message' => 'The installation session has ended. Start again from the beginning.'], 409);
        }

        $task = (string) $this->request->query('name');

        try {
            $result = $this->app->pipeline()->run($run, $task, $engine);
        } catch (TaskFailed $exception) {
            return Response::json(['status' => 'failed', 'message' => $exception->getMessage(), 'rolled_back' => $exception->rolledBack], 422);
        }

        return Response::json($result + ['complete' => $run->isComplete()]);
    }

    private function donePage(): Response
    {
        $run = Run::current($this->app->store);

        if ($run === null || ! $run->isComplete()) {
            return $this->redirect('');
        }

        $page = $this->view('done', [
            'title' => $run->get('mode') === 'update' ? 'Update complete' : 'Your portal is live',
            'step' => 'done',
            'mode' => $run->get('mode'),
            'run' => $run,
            'apache' => $this->isApache(),
            'installerPath' => $this->app->paths->installerRelative(),
        ]);

        // The installation is finished: end the session and forget the run.
        $run->discard();
        $this->session->forget('token', 'config');

        return $page;
    }

    private function managePage(): Response
    {
        if (! $this->app->state->isInstalled()) {
            return $this->redirect('page=welcome');
        }

        return $this->view('manage', [
            'title' => 'Eduthon portal',
            'state' => $this->app->state->all(),
            'log' => $this->app->log->tail(12),
        ]);
    }

    private function cancel(): Response
    {
        $run = Run::current($this->app->store);

        if ($run !== null && ! $run->get('deployed')) {
            $run->discard();
        }

        return $this->redirect('');
    }

    private function signOut(): Response
    {
        $this->session->forget('token', 'config', 'purchase_code');

        return $this->redirect('');
    }

    /**
     * @return array{0: ?\Eduthon\Installer\Engine\EngineClient, 1: array<string, mixed>}
     */
    private function authenticated(): array
    {
        $token = $this->session->get('token');

        return is_string($token) ? [$this->app->engine($token), (array) $this->session->get('config', [])] : [null, []];
    }

    private function mode(): string
    {
        return $this->app->state->isInstalled() ? 'update' : 'install';
    }

    /**
     * The address the license is bound to: this site's host and path.
     */
    private function bindUrl(): string
    {
        return (string) preg_replace('#^https?://#', '', $this->request->portalUrl());
    }

    /**
     * @return list<array{label: string, ok: bool, required: bool, value: string, help: string}>
     */
    private function checks(): array
    {
        return $this->app->requirements->check($this->request->isSecure(), $this->request->host(), (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
    }

    private function isApache(): bool
    {
        $software = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');

        return stripos($software, 'apache') !== false || stripos($software, 'litespeed') !== false;
    }

    /**
     * @param  array<string, string>  $flash
     */
    private function redirect(string $query, array $flash = []): Response
    {
        foreach ($flash as $key => $value) {
            $this->session->flash($key, $value);
        }

        return Response::redirect($this->request->installerUrl($query));
    }

    private function notFound(): Response
    {
        return $this->view('error', ['title' => 'Page not found', 'message' => 'That installer page does not exist.'], 404);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function view(string $template, array $data, int $status = 200): Response
    {
        $data += [
            'step' => null,
            'error' => $this->session->pull('error'),
            'csrf' => $this->session->csrfToken(),
            'url' => fn (string $query = ''): string => $this->request->installerUrl($query),
            'asset' => fn (string $file): string => $this->request->installerPath().'assets/'.$file.'?v='.$this->app->version,
            'installerVersion' => $this->app->version,
            'engineHost' => parse_url($this->app->config->engineUrl(), PHP_URL_HOST),
            'mode' => $this->mode(),
        ];

        return Response::html(View::render($this->app->paths->installer.'/views', $template, $data), $status);
    }
}
