<?php /** @var \Eduthon\Installer\Installation\Run $run */ ?>
<h1 class="text-[26px] font-bold tracking-tight"><?= e($title) ?></h1>
<p class="mt-2 text-[15px] text-ink-muted">Installing Eduthon portal <span class="font-semibold text-ink">v<?= e($run->release('version')) ?></span> for <?= e($run->config('school.name')) ?>. Keep this page open; it takes a minute or two.</p>

<div class="mt-7 overflow-hidden rounded-xl border border-line bg-surface" data-runner data-endpoint="<?= e($url('action=task')) ?>" data-done-url="<?= e($url('page=done')) ?>" <?= $canContinue ? '' : 'data-paused' ?>>
    <div class="h-1 w-full bg-surface-hover"><div class="h-1 bg-brand transition-all duration-500" data-progress></div></div>
    <ol class="divide-y divide-line">
        <?php foreach ($tasks as $name => $label): $status = $run->taskStatus($name); ?>
            <li class="flex gap-3 px-4 py-3.5" data-task="<?= e($name) ?>" data-status="<?= e($status ?? 'pending') ?>">
                <span class="task-icon" aria-hidden="true"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-[14px] font-medium"><?= e($label) ?></p>
                    <p class="task-message mt-0.5 text-[13px] text-ink-muted" data-message><?= e($run->tasks()[$name]['message'] ?? '') ?></p>
                </div>
            </li>
        <?php endforeach ?>
    </ol>
</div>

<div class="mt-6 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-4 text-[14px] text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200" data-failure role="alert">
    <p class="font-semibold">The installation stopped</p>
    <p class="mt-1" data-failure-message></p>
    <div class="mt-4 flex flex-wrap gap-2">
        <form method="post" action="<?= e($url('page=review')) ?>"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><button class="btn-primary btn-sm">Start again</button></form>
        <form method="post" action="<?= e($url('action=cancel')) ?>"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><button class="btn-ghost btn-sm">Cancel</button></form>
    </div>
</div>

<?php if (! $canContinue): ?>
    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[14px] text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        Your session expired before the installation finished. <a class="font-semibold underline" href="<?= e($url('page=license')) ?>">Confirm your license</a> to start again.
    </div>
<?php endif ?>
