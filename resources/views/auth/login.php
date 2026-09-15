<?php
$email ??= '';
$error ??= null;
$notice ??= null;
$locale = \App\Core\Lang::locale();
$enLocaleClasses = $locale === 'en' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
$frLocaleClasses = $locale === 'fr' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
?>
<style>
    .auth-login-shell {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    @media (min-width: 1024px) {
        .auth-login-shell {
            grid-template-columns: 1.15fr 0.85fr;
        }
    }
</style>
<div class="auth-login-shell min-h-screen grid">

    <!-- LEFT: hero panel -->
    <div class="relative bg-primary hidden lg:flex flex-col justify-center overflow-hidden px-10 py-10 xl:px-16 xl:py-12">
        <!-- decorative mesh background -->
        <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-40" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" fill="none">
            <g stroke="#3B82F6" stroke-width="1">
                <path d="M120 60 L340 20 L520 140 L460 320 L620 380 L560 560 L700 620 L640 800" />
                <path d="M340 20 L420 220 L520 140" />
                <path d="M420 220 L460 320 L280 360 L340 20" />
                <path d="M280 360 L120 60" />
                <path d="M460 320 L620 380 L520 500 L280 360" />
                <path d="M520 500 L560 560 L400 620 L280 360" />
                <path d="M560 560 L700 620 L620 760 L400 620" />
                <path d="M620 760 L640 800 L480 860 L400 620" />
            </g>
            <g fill="#60A5FA">
                <circle cx="120" cy="60" r="3" /><circle cx="340" cy="20" r="2.5" />
                <circle cx="520" cy="140" r="3" /><circle cx="460" cy="320" r="2.5" />
                <circle cx="620" cy="380" r="3" /><circle cx="560" cy="560" r="2.5" />
                <circle cx="700" cy="620" r="3" /><circle cx="640" cy="800" r="2.5" />
                <circle cx="420" cy="220" r="2" /><circle cx="280" cy="360" r="2.5" />
                <circle cx="520" cy="500" r="2" /><circle cx="400" cy="620" r="2.5" />
                <circle cx="480" cy="860" r="2" /><circle cx="620" cy="760" r="2" />
            </g>
        </svg>
        <div class="absolute inset-0 bg-gradient-to-r from-[#0A1628] via-[#0A1628]/60 to-transparent"></div>

        <div class="relative z-10 max-w-lg">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 mb-10 backdrop-blur">
                <img src="/assets/images/fiucc-seal.png" alt="FIUCC" class="h-6 w-6 rounded-md object-cover ring-1 ring-white/20" />
                <span class="text-xs font-semibold tracking-wide text-white/80"><?= e(t('app.university_name')) ?></span>
            </div>

            <h1 class="font-headline-lg font-extrabold uppercase leading-[0.95] text-white text-5xl xl:text-6xl">
                <?= e(t('auth.hero_line1')) ?>
            </h1>
            <h1 class="font-headline-lg font-extrabold uppercase leading-[0.95] text-blue-400 text-5xl xl:text-6xl">
                <?= e(t('auth.hero_line2')) ?>
            </h1>
            <h1 class="font-headline-lg font-extrabold uppercase leading-[0.95] text-white text-5xl xl:text-6xl mb-8">
                <?= e(t('auth.hero_line3')) ?>
            </h1>

            <p class="text-white/60 text-base leading-relaxed max-w-sm">
                <?= e(t('auth.hero_subtitle')) ?>
            </p>
        </div>

        <div class="relative z-10 mt-16 text-xs text-white/30 tracking-wide">
            <?= e(t('app.name')) ?> &middot; <?= date('Y') ?>
        </div>
    </div>

    <!-- RIGHT: sign-in panel -->
    <div class="flex items-center justify-center px-4 py-8 lg:py-12">
        <div class="w-full max-w-md p-6 md:p-8">
            <div class="mb-6 flex justify-end">
                <div class="inline-flex items-center gap-1 rounded-control border border-[#dbe3ee] bg-[#f8fafc] p-1">
                    <a href="/lang/en" class="px-2 py-1 rounded text-label-md font-label-md transition-colors <?= e($enLocaleClasses) ?>">EN</a>
                    <a href="/lang/fr" class="px-2 py-1 rounded text-label-md font-label-md transition-colors <?= e($frLocaleClasses) ?>">FR</a>
                </div>
            </div>

            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#e0f2fe] shadow-sm overflow-hidden ring-1 ring-[#dbe3ee]">
                    <img src="/assets/images/fiucc-seal.png" alt="FIUCC" class="h-full w-full object-cover" />
                </div>
                <h1 class="font-headline-lg text-headline-lg text-[#111827]">
                    <?= e(t('auth.sign_in')) ?>
                </h1>
                <p class="mt-2 text-sm text-[#64748b]"><?= e(t('auth.subtitle')) ?></p>
            </div>

            <?php if ($notice !== null): ?>
                <div class="mb-6"><?= component('alert', ['type' => 'info', 'message' => $notice]) ?></div>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <div class="mb-6"><?= component('alert', ['type' => 'error', 'message' => $error]) ?></div>
            <?php endif; ?>

            <form method="POST" action="/login" class="flex flex-col gap-5
                [&_label]:text-[#374151]
                [&_input]:bg-[#f8fafc] [&_input]:border-[#dbe3ee] [&_input]:text-[#111827]
                [&_input]:placeholder-[#64748b] [&_input]:rounded-control
                [&_input:focus]:border-[#0063C6] [&_input:focus]:ring-[#0063C6]/20">
                <?= csrf_field() ?>
                <?= component('form_field', [
                    'name' => 'email',
                    'label' => t('auth.email'),
                    'type' => 'email',
                    'value' => $email,
                    'required' => true,
                ]) ?>
                <?= component('form_field', [
                    'name' => 'password',
                    'label' => t('auth.password'),
                    'type' => 'password',
                    'required' => true,
                ]) ?>

                <div class="-mt-2 text-right">
                    <a href="/password/forgot" class="text-xs font-medium text-primary hover:text-primary/80 transition-colors">
                        <?= e(t('auth.forgot_password')) ?>
                    </a>
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-control bg-primary px-4 py-3 text-label-lg font-label-lg font-semibold text-white transition hover:bg-[#0055a4] focus:outline-none focus:ring-2 focus:ring-[#0063C6]/30">
                    <?= e(t('auth.sign_in')) ?>
                </button>
            </form>

            <div class="my-6 flex items-center gap-3">
                <div class="h-px flex-1 bg-[#e5e7eb]"></div>
                <span class="text-[11px] text-[#64748b]"><?= e(t('auth.or')) ?></span>
                <div class="h-px flex-1 bg-[#e5e7eb]"></div>
            </div>

            <a href="/register" class="flex items-center justify-center gap-2 rounded-control border border-[#dbe3ee] bg-[#f8fafc] px-4 py-3 text-sm font-semibold text-[#111827] hover:bg-[#eef4ff] transition-colors">
                <?= icon('user-plus', 'text-base text-[#111827]') ?>
                <?= e(t('auth.join_community')) ?>
            </a>
        </div>
    </div>
</div>