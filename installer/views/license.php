<h1 class="text-[26px] font-bold tracking-tight"><?= e($title) ?></h1>
<p class="mt-2 text-[15px] text-ink-muted">Use the purchase code and secret key from your Delwathon welcome email. The secret key is sent straight to Delwathon over an encrypted connection and is never saved on this server.</p>

<form method="post" action="<?= e($url('page=license')) ?>" class="mt-8 space-y-5" data-submit-once>
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">

    <div>
        <label for="purchase_code" class="label">Purchase code</label>
        <input id="purchase_code" name="purchase_code" value="<?= e($purchaseCode) ?>" required autocomplete="off" spellcheck="false" placeholder="EDU-XXXX-XXXX-XXXX-XXXX" class="control font-mono uppercase" <?= $purchaseCode === '' ? 'autofocus' : '' ?>>
    </div>

    <div>
        <label for="secret_key" class="label">Secret key</label>
        <div class="relative">
            <input id="secret_key" name="secret_key" type="password" required autocomplete="off" spellcheck="false" placeholder="dsk_…" class="control pr-20 font-mono" <?= $purchaseCode !== '' ? 'autofocus' : '' ?>>
            <button type="button" data-reveal="secret_key" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md px-2 py-1 text-[12.5px] font-semibold text-brand hover:bg-surface-hover">Show</button>
        </div>
    </div>

    <div class="rounded-xl border border-line bg-surface-muted px-4 py-3 text-[13px] text-ink-muted">
        This license will be activated for <span class="font-mono font-medium text-ink"><?= e(rtrim($bindUrl, '/')) ?></span>. Each license works on one website; contact Delwathon if you need to move it.
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-primary"><span data-idle>Verify license</span><span data-busy hidden>Checking with Delwathon…</span></button>
        <a href="<?= e($url($mode === 'update' ? 'page=manage' : 'page=requirements')) ?>" class="btn-ghost">Back</a>
    </div>
</form>
