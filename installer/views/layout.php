<?php
/** @var string $title @var string $content @var ?string $step @var callable $asset @var callable $url */
$steps = [
    'welcome' => 'Welcome',
    'requirements' => 'Server check',
    'license' => 'License',
    'review' => 'Review',
    'install' => $mode === 'update' ? 'Update' : 'Install',
    'done' => 'Finish',
];
$keys = array_keys($steps);
$position = $step === null ? -1 : (int) array_search($step, $keys, true);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($title) ?> · Eduthon Installer</title>
    <link rel="icon" type="image/png" href="<?= e($asset('favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e($asset('installer.css')) ?>">
    <script src="<?= e($asset('installer.js')) ?>" defer></script>
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <div class="flex min-h-screen flex-col lg:flex-row">
        <aside class="relative overflow-hidden bg-rail px-6 py-6 text-white lg:w-[22rem] lg:shrink-0 lg:px-10 lg:py-10 xl:w-[26rem] xl:pl-[max(2.5rem,calc((100vw-80rem)/2+2.5rem))]">
            <div class="pointer-events-none absolute -top-24 -left-24 size-72 rounded-full bg-sky-500/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-24 bottom-0 size-72 rounded-full bg-emerald-500/15 blur-3xl"></div>

            <div class="relative flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-xl bg-white shadow-sm"><img src="<?= e($asset('eduthon-mark.png')) ?>" alt="" class="size-8"></span>
                <span class="leading-tight">
                    <span class="block text-[17px] font-bold tracking-tight">Eduthon</span>
                    <span class="block text-[11px] font-semibold tracking-[0.16em] text-emerald-300 uppercase">Installer</span>
                </span>
            </div>

            <?php if ($step !== null): ?>
                <ol class="relative mt-8 hidden space-y-1 lg:block" aria-label="Installation steps">
                    <?php foreach ($steps as $key => $label): $index = array_search($key, $keys, true); ?>
                        <li class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-[14px] <?= $index === $position ? 'bg-white/10 font-semibold text-white' : ($index < $position ? 'text-white/80' : 'text-white/45') ?>" <?= $index === $position ? 'aria-current="step"' : '' ?>>
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold <?= $index < $position ? 'bg-emerald-500 text-white' : ($index === $position ? 'bg-white text-rail' : 'border border-white/25') ?>">
                                <?php if ($index < $position): ?>
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-3.5" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                <?php else: ?>
                                    <?= $index + 1 ?>
                                <?php endif ?>
                            </span>
                            <?= e($label) ?>
                        </li>
                    <?php endforeach ?>
                </ol>

                <p class="relative mt-4 text-[13px] text-white/60 lg:hidden">Step <?= $position + 1 ?> of <?= count($steps) ?> · <?= e($steps[$step] ?? '') ?></p>
            <?php endif ?>

            <div class="relative mt-10 hidden text-[12.5px] leading-relaxed text-white/55 lg:block">
                <p class="flex items-center gap-2 font-medium text-white/75">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-4 text-emerald-300" aria-hidden="true"><path fill-rule="evenodd" d="M10 1.9a1 1 0 0 1 .6.2l6 4a1 1 0 0 1 .4.8V10c0 4.1-2.8 7-6.7 8.1a1 1 0 0 1-.6 0C5.8 17 3 14.1 3 10V6.9a1 1 0 0 1 .4-.8l6-4a1 1 0 0 1 .6-.2Zm3.2 6.3a.8.8 0 0 0-1.2-1l-3 3.4-1.1-1.1a.8.8 0 1 0-1.1 1.1l1.7 1.7a.8.8 0 0 0 1.2 0l3.5-4Z" clip-rule="evenodd"/></svg>
                    Signed &amp; verified
                </p>
                <p class="mt-1.5">Every package is checked against Delwathon's signature before anything is unpacked on your server.</p>
            </div>
        </aside>

        <main class="flex flex-1 flex-col px-5 py-8 sm:px-10 lg:px-14 lg:py-14">
            <div class="w-full max-w-2xl flex-1">
                <?php if (! empty($error)): ?>
                    <div class="mb-6 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[14px] text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200" role="alert">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 size-5 shrink-0" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.8.8 0 0 1 .8.8v4.5a.8.8 0 0 1-1.6 0V5.8A.8.8 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                        <p><?= e($error) ?></p>
                    </div>
                <?php endif ?>

                <?= $content ?>
            </div>

            <footer class="mt-12 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-5 text-[12.5px] text-ink-subtle">
                <span>Eduthon Installer v<?= e($installerVersion) ?> · by Delwathon IT Solutions</span>
                <span>Connected to <?= e($engineHost) ?></span>
            </footer>
        </main>
    </div>
</body>
</html>
