<?php /** @var array<string, mixed> $state */ ?>
<h1 class="text-[26px] font-bold tracking-tight">Eduthon portal</h1>
<p class="mt-2 text-[15px] text-ink-muted">The portal is installed on this website. Use this page to install updates or repair the installation.</p>

<div class="mt-7 grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-line bg-surface px-4 py-3.5">
        <p class="text-[12.5px] text-ink-muted">Installed version</p>
        <p class="mt-1 text-xl font-bold">v<?= e($state['version'] ?? '—') ?></p>
    </div>
    <div class="rounded-xl border border-line bg-surface px-4 py-3.5">
        <p class="text-[12.5px] text-ink-muted">Channel</p>
        <p class="mt-1 text-xl font-bold"><?= e(ucfirst((string) ($state['channel'] ?? 'stable'))) ?></p>
    </div>
    <div class="rounded-xl border border-line bg-surface px-4 py-3.5">
        <p class="text-[12.5px] text-ink-muted">Last change</p>
        <p class="mt-1 text-[15px] font-semibold"><?= e(isset($state['updated_at']) ? gmdate('j M Y, H:i', strtotime($state['updated_at'])).' UTC' : '—') ?></p>
    </div>
</div>

<dl class="mt-6 divide-y divide-line overflow-hidden rounded-xl border border-line bg-surface text-[14px]">
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3"><dt class="text-ink-muted">School</dt><dd class="font-medium sm:col-span-2"><?= e($state['school'] ?? '—') ?></dd></div>
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3"><dt class="text-ink-muted">Portal</dt><dd class="font-mono text-[13px] break-all sm:col-span-2"><a class="text-brand hover:underline" href="<?= e($state['portal_url'] ?? '#') ?>"><?= e($state['portal_url'] ?? '—') ?></a></dd></div>
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3"><dt class="text-ink-muted">Backend</dt><dd class="font-mono text-[13px] break-all sm:col-span-2"><?= e($state['backend_api_url'] ?? '—') ?></dd></div>
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3"><dt class="text-ink-muted">Purchase code</dt><dd class="font-mono text-[13px] sm:col-span-2"><?= e($state['purchase_code'] ?? '—') ?></dd></div>
</dl>

<div class="mt-8 flex flex-wrap items-center gap-3">
    <a href="<?= e($url('page=license')) ?>" class="btn-primary">Check for updates</a>
    <span class="text-[13px] text-ink-muted">You will be asked for your secret key.</span>
</div>

<?php if (! empty($state['history'])): ?>
    <h2 class="mt-10 text-[13px] font-semibold tracking-wide text-ink-muted uppercase">History</h2>
    <ul class="mt-3 space-y-2 text-[13.5px]">
        <?php foreach (array_slice($state['history'], 0, 6) as $entry): ?>
            <li class="flex justify-between gap-4"><span><?= e(ucfirst($entry['event'])) ?> v<?= e($entry['version'] ?? '?') ?></span><span class="text-ink-subtle"><?= e(gmdate('j M Y, H:i', strtotime($entry['at']))) ?> UTC</span></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<?php if ($log !== []): ?>
    <details class="mt-8 rounded-xl border border-line bg-surface">
        <summary class="cursor-pointer px-4 py-3 text-[13.5px] font-medium">Recent installer log</summary>
        <pre class="max-h-72 overflow-auto border-t border-line px-4 py-3 font-mono text-[12px] whitespace-pre-wrap text-ink-muted"><?= e(implode("\n", $log)) ?></pre>
    </details>
<?php endif ?>
