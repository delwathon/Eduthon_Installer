<?php
/** @var \Eduthon\Installer\Installation\Run $run */
$warnings = (array) $run->get('warnings', []);
$reportWarning = $run->taskStatus('report') === 'warning';
?>
<div class="flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
    <svg viewBox="0 0 20 20" fill="currentColor" class="size-7" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
</div>
<h1 class="mt-5 text-[28px] font-bold tracking-tight"><?= e($title) ?></h1>
<p class="mt-2 text-[15px] text-ink-muted">Eduthon portal v<?= e($run->release('version')) ?> is installed at <span class="font-mono text-[13px] text-ink"><?= e($run->get('portal_url')) ?></span> and connected to <?= e($run->config('school.name')) ?>.</p>

<?php if ($warnings !== [] || $reportWarning): ?>
    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[14px] text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        <p class="font-semibold">Installed, with things to check</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            <?php foreach ($warnings as $warning): ?><li><?= e($warning) ?></li><?php endforeach ?>
            <?php if ($reportWarning): ?><li><?= e($run->tasks()['report']['message']) ?></li><?php endif ?>
        </ul>
    </div>
<?php endif ?>

<?php if (! $apache): ?>
    <div class="mt-6 rounded-xl border border-line bg-surface px-4 py-4">
        <p class="text-[14px] font-semibold">One more step for this web server</p>
        <p class="mt-1 text-[13px] text-ink-muted">This server does not read <span class="font-mono">.htaccess</span> files. Add this rule to the site's configuration so every portal page loads correctly (Nginx example):</p>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950 px-4 py-3 font-mono text-[12.5px] text-slate-100" data-copyable>location /<?= e($installerPath) ?>/ { try_files $uri $uri/ /<?= e($installerPath) ?>/index.php?$query_string; }
location / { try_files $uri $uri/ /index.html; }</pre>
    </div>
<?php endif ?>

<div class="mt-8 grid gap-3 sm:grid-cols-2">
    <div class="rounded-xl border border-line bg-surface px-4 py-3.5">
        <p class="text-[14px] font-semibold">Keep this installer</p>
        <p class="mt-0.5 text-[13px] text-ink-muted">Return to <span class="font-mono">/<?= e($installerPath) ?>/</span> to install updates. It only works with your license details.</p>
    </div>
    <div class="rounded-xl border border-line bg-surface px-4 py-3.5">
        <p class="text-[14px] font-semibold">A backup was saved</p>
        <p class="mt-0.5 text-[13px] text-ink-muted">Your previous site is kept in the installer's storage folder.</p>
    </div>
</div>

<div class="mt-8 flex flex-wrap items-center gap-3">
    <a href="<?= e($run->get('portal_url')) ?>" class="btn-primary">Open your portal</a>
    <a href="<?= e($url('page=manage')) ?>" class="btn-ghost">Installer overview</a>
</div>
