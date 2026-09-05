<!-- Démo temporaire — FIUCC-21, à supprimer une fois la revue visuelle terminée -->
<div class="max-w-layout mx-auto px-4 py-16 space-y-16">

    <section>
        <h2 class="font-headline-md text-headline-md text-ink mb-6">Buttons</h2>
        <div class="flex flex-wrap items-center gap-4">
            <?= component('button', ['label' => 'Primary', 'variant' => 'primary']) ?>
            <?= component('button', ['label' => 'Secondary', 'variant' => 'secondary']) ?>
            <?= component('button', ['label' => 'Accent', 'variant' => 'accent']) ?>
            <?= component('button', ['label' => 'Destructive', 'variant' => 'destructive']) ?>
            <?= component('button', ['label' => 'As a link', 'variant' => 'primary', 'href' => '#']) ?>
        </div>
    </section>

    <section>
        <h2 class="font-headline-md text-headline-md text-ink mb-6">Cards</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?= component('card', [
                'title' => 'Standard card',
                'content' => '<p class="text-body-md text-muted">Plain card body content.</p>',
            ]) ?>
            <?= component('card', [
                'title' => 'Featured card',
                'content' => '<p class="text-body-md text-muted">Has the primary top accent stripe.</p>',
                'featured' => true,
            ]) ?>
        </div>
    </section>

    <section>
        <h2 class="font-headline-md text-headline-md text-ink mb-6">Status badges</h2>
        <div class="flex flex-wrap gap-3">
            <?= component('status_badge', ['status' => 'student']) ?>
            <?= component('status_badge', ['status' => 'alumni']) ?>
            <?= component('status_badge', ['status' => 'mentor']) ?>
            <?= component('status_badge', ['status' => 'admin']) ?>
        </div>
    </section>

    <section>
        <h2 class="font-headline-md text-headline-md text-ink mb-6">Form field</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl">
            <?= component('form_field', [
                'name' => 'full_name',
                'label' => 'Full name',
                'placeholder' => 'Jane Doe',
            ]) ?>
            <?= component('form_field', [
                'name' => 'email',
                'label' => 'University email',
                'type' => 'email',
                'value' => 'jane.doe@final.edu.tr',
                'required' => true,
                'help' => 'Must end with @final.edu.tr',
            ]) ?>
            <?= component('form_field', [
                'name' => 'student_id',
                'label' => 'Student ID',
                'error' => 'This student ID is already registered.',
            ]) ?>
            <?= component('form_field', [
                'name' => 'escaping_test',
                'label' => 'Escaping test (value below contains a <script> tag)',
                'value' => '<script>alert("xss")</script>',
                'help' => 'If this renders as plain text and no alert popup appears, escaping works.',
            ]) ?>
        </div>
    </section>

    <section>
        <h2 class="font-headline-md text-headline-md text-ink mb-6">Alerts</h2>
        <div class="flex flex-col gap-4 max-w-2xl">
            <?= component('alert', ['type' => 'info', 'message' => 'Registration is open until 20 September 2026.']) ?>
            <?= component('alert', ['type' => 'success', 'message' => 'Your membership has been approved.']) ?>
            <?= component('alert', ['type' => 'warning', 'message' => 'Voting closes in less than 24 hours.']) ?>
            <?= component('alert', ['type' => 'error', 'message' => 'Escaping test: <b>this should show as literal text</b>, not bold.']) ?>
        </div>
    </section>

    <section>
        <h2 class="font-headline-md text-headline-md text-ink mb-6">Pagination</h2>
        <div class="flex flex-col gap-6">
            <?= component('pagination', ['currentPage' => 1, 'totalPages' => 5, 'baseUrl' => '#page={page}']) ?>
            <?= component('pagination', ['currentPage' => 3, 'totalPages' => 5, 'baseUrl' => '#page={page}']) ?>
            <?= component('pagination', ['currentPage' => 5, 'totalPages' => 5, 'baseUrl' => '#page={page}']) ?>
            <p class="text-body-sm text-muted">Single page (totalPages = 1) renders nothing below — by design:</p>
            <?= component('pagination', ['currentPage' => 1, 'totalPages' => 1, 'baseUrl' => '#page={page}']) ?>
        </div>
    </section>

</div>
