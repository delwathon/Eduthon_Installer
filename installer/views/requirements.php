<h1 class="text-[26px] font-bold tracking-tight">Server check</h1>
<p class="mt-2 text-[15px] text-ink-muted">Everything marked required must pass before the portal can be installed.</p>

<?php if (! $hasKeys): ?>
    <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[14px] text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
        <p class="font-semibold">This copy of the installer cannot verify packages</p>
        <p class="mt-0.5">It was built without Delwathon's signing key. Download a fresh installer from Delwathon before continuing.</p>
    </div>
<?php endif ?>

<ul class="mt-6 divide-y divide-line overflow-hidden rounded-xl border border-line bg-surface">
    <?php foreach ($checks as $check): ?>
        <li class="flex gap-3 px-4 py-3.5">
            <?php if ($check['ok']): ?>
                <span class="status-ok" aria-label="Passed"><svg viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg></span>
            <?php elseif ($check['required']): ?>
                <span class="status-fail" aria-label="Failed"><svg viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path d="M6.3 5.2a.8.8 0 0 0-1.1 1.1L8.9 10l-3.7 3.7a.8.8 0 1 0 1.1 1.1l3.7-3.7 3.7 3.7a.8.8 0 1 0 1.1-1.1L11.1 10l3.7-3.7a.8.8 0 0 0-1.1-1.1L10 8.9 6.3 5.2Z"/></svg></span>
            <?php else: ?>
                <span class="status-warn" aria-label="Recommended">!</span>
            <?php endif ?>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                    <p class="text-[14px] font-medium"><?= e($check['label']) ?><?= $check['required'] ? '' : ' <span class="font-normal text-ink-subtle">(recommended)</span>' ?></p>
                    <p class="truncate text-[12.5px] text-ink-subtle" title="<?= e($check['value']) ?>"><?= e($check['value']) ?></p>
                </div>
                <?php if (! $check['ok']): ?>
                    <p class="mt-1 text-[13px] text-ink-muted"><?= e($check['help']) ?></p>
                <?php endif ?>
            </div>
        </li>
    <?php endforeach ?>
</ul>

<div class="mt-8 flex flex-wrap items-center gap-3">
    <?php if ($passes && $hasKeys): ?>
        <a href="<?= e($url('page=license')) ?>" class="btn-primary">Continue</a>
    <?php else: ?>
        <a href="<?= e($url('page=requirements')) ?>" class="btn-primary">Check again</a>
        <span class="text-[13px] text-ink-muted">Fix the items above, then check again.</span>
    <?php endif ?>
    <a href="<?= e($url('page=welcome')) ?>" class="btn-ghost">Back</a>
</div>
