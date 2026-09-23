<?php
$backendReady = (bool) ($config['backend']['ready'] ?? false);
$upToDate = $mode === 'update' && $release !== null && ! $release['update_available'];
?>
<h1 class="text-[26px] font-bold tracking-tight"><?= e($title) ?></h1>
<p class="mt-2 text-[15px] text-ink-muted">License confirmed for <span class="font-semibold text-ink"><?= e($config['school']['name'] ?? 'your school') ?></span>. Check the details below, then start.</p>

<dl class="mt-7 divide-y divide-line overflow-hidden rounded-xl border border-line bg-surface text-[14px]">
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3"><dt class="text-ink-muted">School</dt><dd class="font-medium sm:col-span-2"><?= e($config['school']['name'] ?? '—') ?></dd></div>
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3"><dt class="text-ink-muted">Portal address</dt><dd class="font-mono text-[13px] break-all sm:col-span-2"><?= e($portalUrl) ?></dd></div>
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
        <dt class="text-ink-muted">School backend</dt>
        <dd class="sm:col-span-2">
            <?php if ($backendReady): ?>
                <span class="font-mono text-[13px] break-all"><?= e($config['backend']['url']) ?></span>
                <span class="block text-[12.5px] text-ink-subtle">Hosted by Delwathon · tenant <span class="font-mono"><?= e($config['backend']['tenant'] ?? '') ?></span></span>
            <?php else: ?>
                <span class="font-medium text-red-700 dark:text-red-300">Not assigned yet</span>
            <?php endif ?>
        </dd>
    </div>
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
        <dt class="text-ink-muted">Version</dt>
        <dd class="sm:col-span-2">
            <?php if ($release === null): ?>
                <span class="text-ink-muted">No release published yet</span>
            <?php else: ?>
                <span class="font-semibold"><?= $current ? e('v'.$current).' → ' : '' ?>v<?= e($release['version']) ?></span>
                <span class="ml-2 rounded-md bg-surface-hover px-1.5 py-0.5 text-[11.5px] font-medium text-ink-muted"><?= e(ucfirst($release['channel'] ?? 'stable')) ?> · <?= e(human_bytes((int) ($release['size'] ?? 0))) ?></span>
                <?php if (! empty($release['notes'])): ?>
                    <span class="mt-1.5 block text-[13px] whitespace-pre-line text-ink-muted"><?= e($release['notes']) ?></span>
                <?php endif ?>
            <?php endif ?>
        </dd>
    </div>
    <div class="grid gap-1 px-4 py-3 sm:grid-cols-3"><dt class="text-ink-muted">Install folder</dt><dd class="font-mono text-[13px] break-all sm:col-span-2"><?= e($target) ?></dd></div>
</dl>

<?php if (! $backendReady): ?>
    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[14px] text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        <p class="font-semibold">Your school's backend is still being prepared</p>
        <p class="mt-0.5">Delwathon sets this up after your license is issued. Contact <?= e($config['support']['email'] ?? 'Delwathon support') ?><?= ! empty($config['support']['phone']) ? ' or '.e($config['support']['phone']) : '' ?> and run the installer again once it is ready.</p>
    </div>
<?php elseif ($existing !== []): ?>
    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[14px] text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        <p class="font-semibold">This website already has content</p>
        <p class="mt-0.5">Files the portal replaces (such as <?= e(implode(', ', array_slice($existing, 0, 4))) ?><?= count($existing) > 4 ? ' and '.(count($existing) - 4).' more' : '' ?>) are backed up first and restored if the installation fails. Other files are left alone.</p>
    </div>
<?php endif ?>

<?php if ($upToDate): ?>
    <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[14px] text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
        You already have the latest version. You can reinstall it to repair the portal.
    </div>
<?php endif ?>

<form method="post" action="<?= e($url('page=review')) ?>" class="mt-8 flex flex-wrap items-center gap-3" data-submit-once>
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <button type="submit" class="btn-primary" <?= $backendReady && $release !== null ? '' : 'disabled' ?>>
        <span data-idle><?= $mode === 'update' ? ($upToDate ? 'Reinstall v'.e($release['version']) : 'Update to v'.e($release['version'] ?? '')) : 'Install the portal' ?></span>
        <span data-busy hidden>Preparing…</span>
    </button>
    <button type="submit" form="signout" class="btn-ghost">Use a different license</button>
</form>
<form id="signout" method="post" action="<?= e($url('action=signout')) ?>"><input type="hidden" name="_token" value="<?= e($csrf) ?>"></form>
