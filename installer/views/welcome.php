<h1 class="text-[28px] leading-tight font-bold tracking-tight">Install your Eduthon school portal</h1>
<p class="mt-3 text-[15px] leading-relaxed text-ink-muted">This installer puts your school's Eduthon portal on this website and connects it to your school's records, which Delwathon hosts securely. It takes about five minutes.</p>

<div class="mt-8 grid gap-3 sm:grid-cols-2">
    <?php foreach ([
        ['Your license', 'The purchase code and secret key Delwathon emailed to you.'],
        ['An SSL certificate', 'The site must open with https:// so your details stay private.'],
        ['PHP 8.1 or newer', 'With the curl, sodium and zip extensions. We check this next.'],
        ['About 150 MB free', 'For the download, a working copy and a backup of your current site.'],
    ] as [$heading, $text]): ?>
        <div class="rounded-xl border border-line bg-surface px-4 py-3.5">
            <p class="text-[14px] font-semibold"><?= e($heading) ?></p>
            <p class="mt-0.5 text-[13px] text-ink-muted"><?= e($text) ?></p>
        </div>
    <?php endforeach ?>
</div>

<h2 class="mt-10 text-[13px] font-semibold tracking-wide text-ink-muted uppercase">What the installer does</h2>
<ol class="mt-3 space-y-2.5 text-[14px] text-ink-muted">
    <li class="flex gap-3"><span class="step-dot">1</span>Confirms your license with Delwathon and fetches your school's settings.</li>
    <li class="flex gap-3"><span class="step-dot">2</span>Downloads the latest portal and verifies Delwathon's digital signature before unpacking anything.</li>
    <li class="flex gap-3"><span class="step-dot">3</span>Backs up anything already on this website, then deploys the portal.</li>
    <li class="flex gap-3"><span class="step-dot">4</span>Tests the result and restores your previous site automatically if something fails.</li>
</ol>

<div class="mt-10 flex items-center gap-3">
    <a href="<?= e($url('page=requirements')) ?>" class="btn-primary">Check this server
        <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.8.8 0 0 1 .8-.8h10.4l-3.7-3.6a.8.8 0 1 1 1.1-1.2l5 5a.8.8 0 0 1 0 1.2l-5 5a.8.8 0 1 1-1.1-1.2l3.7-3.6H3.8A.8.8 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
    </a>
    <span class="text-[13px] text-ink-subtle">Nothing is changed until you confirm.</span>
</div>
