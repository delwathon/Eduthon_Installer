/* Eduthon Installer: runs installation steps and small form niceties. No dependencies. */
(function () {
    'use strict';

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    // Prevent double submits and show a busy label.
    document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');
            if (!button) return;
            button.disabled = true;
            var idle = button.querySelector('[data-idle]');
            var busy = button.querySelector('[data-busy]');
            if (idle && busy) { idle.hidden = true; busy.hidden = false; }
        });
    });

    // Show or hide the secret key.
    document.querySelectorAll('[data-reveal]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-reveal'));
            if (!input) return;
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            button.textContent = hidden ? 'Hide' : 'Show';
        });
    });

    var runner = document.querySelector('[data-runner]');
    if (!runner) return;

    var endpoint = runner.getAttribute('data-endpoint');
    var doneUrl = runner.getAttribute('data-done-url');
    var tasks = Array.prototype.slice.call(runner.querySelectorAll('[data-task]'));
    var progress = runner.querySelector('[data-progress]');
    var failure = document.querySelector('[data-failure]');

    function finished(task) {
        var status = task.getAttribute('data-status');
        return status === 'done' || status === 'warning';
    }

    function updateProgress() {
        var done = tasks.filter(finished).length;
        progress.style.width = Math.round((done / tasks.length) * 100) + '%';
    }

    function showFailure(message) {
        failure.querySelector('[data-failure-message]').textContent = message;
        failure.classList.remove('hidden');
        failure.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function run(index) {
        updateProgress();

        if (index >= tasks.length) {
            setTimeout(function () { window.location.href = doneUrl; }, 700);
            return;
        }

        var task = tasks[index];
        if (finished(task)) return run(index + 1);

        task.setAttribute('data-status', 'running');
        task.querySelector('[data-message]').textContent = 'Working…';

        fetch(endpoint + '&name=' + encodeURIComponent(task.getAttribute('data-task')), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': token },
        })
            .then(function (response) {
                return response.json().catch(function () {
                    return { status: 'failed', message: 'The server returned an unexpected response (HTTP ' + response.status + '). Check the PHP error log.' };
                });
            })
            .then(function (result) {
                task.setAttribute('data-status', result.status || 'failed');
                task.querySelector('[data-message]').textContent = result.message || '';

                if (result.status === 'done' || result.status === 'warning') {
                    run(index + 1);
                } else {
                    updateProgress();
                    showFailure(result.message || 'The installation stopped.');
                }
            })
            .catch(function () {
                task.setAttribute('data-status', 'failed');
                task.querySelector('[data-message]').textContent = 'The connection to this server was interrupted.';
                showFailure('The connection was interrupted. Your hosting may have stopped a long request. Start again; completed work is repeated safely.');
            });
    }

    var failed = tasks.filter(function (task) { return task.getAttribute('data-status') === 'failed'; })[0];

    if (failed) {
        updateProgress();
        showFailure(failed.querySelector('[data-message]').textContent);
    } else if (!runner.hasAttribute('data-paused')) {
        run(0);
    } else {
        updateProgress();
    }
})();
