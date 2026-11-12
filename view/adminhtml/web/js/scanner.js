/**
 * "Website scanner" panel of Stores > Configuration > CookieScript.
 *
 * Flow:
 *   "Scan website"  -> asks the server whether scanning is possible   (cookiescript/scan/check)
 *   "Start scan"    -> starts the scan                                (cookiescript/scan/start)
 *                      then asks every few seconds how it is going    (cookiescript/scan/status)
 *   "Update banner script"                                            (cookiescript/scan/update)
 *
 * The page is never reloaded, so settings the admin has typed but not yet saved are kept.
 */
define(['mage/translate'], function ($t) {
    'use strict';

    var POLL_INTERVAL_MS = 5000;

    /**
     * @param {Object} config  Written by Scanner::getScriptConfigJson()
     * @param {HTMLElement} root  The #cookiescript-scanner element
     */
    return function (config, root) {
        var ui = {
            openFormButton: root.querySelector('[data-role="open-scan-form"]'),
            updateButton: root.querySelector('[data-role="update-script"]'),
            message: root.querySelector('[data-role="message"]'),
            form: root.querySelector('[data-role="scan-form"]'),
            policyUrl: root.querySelector('[data-role="policy-url"]'),
            language: root.querySelector('[data-role="language"]'),
            startButton: root.querySelector('[data-role="start-scan"]'),
            cancelButton: root.querySelector('[data-role="cancel-scan"]'),
            scanning: root.querySelector('[data-role="scanning"]'),
            reportWrapper: root.querySelector('[data-role="report-wrapper"]'),
            reportBody: root.querySelector('[data-role="report-body"]')
        };

        /**
         * Sends a POST request to one of the scanner actions.
         * Resolves with the answer, rejects with an Error whose message can be shown to the admin.
         *
         * @param {String} url
         * @param {Object} [data]
         * @returns {Promise<Object>}
         */
        function post(url, data) {
            var body = new URLSearchParams(Object.assign({
                form_key: config.formKey,
                scope: config.scope,
                scope_id: config.scopeId
            }, data || {}));

            return fetch(url, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (response) {
                return response.json();
            }).catch(function () {
                throw new Error($t('Unexpected answer from the server. Your admin session may have expired - please reload the page.'));
            }).then(function (result) {
                if (!result.success) {
                    throw new Error(result.message || $t('Something went wrong.'));
                }

                return result;
            });
        }

        /**
         * @param {String} text
         * @param {String} [type] "error" or "success"
         */
        function showMessage(text, type) {
            ui.message.textContent = text;
            ui.message.className = 'cookiescript-scanner__message' + (type ? ' cookiescript-scanner__message--' + type : '');
        }

        /**
         * @param {Boolean} isScanning
         */
        function setScanning(isScanning) {
            ui.scanning.hidden = !isScanning;
            ui.openFormButton.hidden = isScanning;
            ui.updateButton.disabled = isScanning;

            if (isScanning) {
                ui.form.hidden = true;
            }
        }

        /**
         * Fills the cookie report table with the scan result.
         *
         * @param {Array} groups  [{type_name: "...", cookies: [{name, domain, expire, desc}]}]
         */
        function renderReport(groups) {
            ui.reportBody.textContent = '';

            groups.forEach(function (group) {
                (group.cookies || []).forEach(function (cookie) {
                    var row = document.createElement('tr');

                    [cookie.name, group.type_name, cookie.domain, cookie.expire, cookie.desc].forEach(function (text) {
                        var cell = document.createElement('td');

                        cell.textContent = text == null ? '' : String(text);
                        row.appendChild(cell);
                    });

                    ui.reportBody.appendChild(row);
                });
            });

            ui.reportWrapper.hidden = ui.reportBody.children.length === 0;
        }

        /**
         * Asks the server how the scan is going, until it is finished.
         */
        function pollScanStatus() {
            post(config.statusUrl).then(function (result) {
                if (result.status === 'finish') {
                    setScanning(false);
                    renderReport(result.groups || []);
                    ui.updateButton.hidden = false;
                    ui.openFormButton.querySelector('span').textContent = $t('Re-scan website');
                    showMessage($t('Scan finished. Set "Enable CookieScript banner" to Yes and save the configuration.'), 'success');

                    return;
                }

                window.setTimeout(pollScanStatus, POLL_INTERVAL_MS);
            }).catch(function (error) {
                setScanning(false);
                showMessage(error.message, 'error');
            });
        }

        // "Scan website": make sure scanning is possible, then show the form.
        ui.openFormButton.addEventListener('click', function () {
            showMessage('');
            ui.openFormButton.disabled = true;

            post(config.checkUrl).then(function (result) {
                if (result.error) {
                    showMessage(result.error, 'error');

                    return;
                }

                ui.form.hidden = false;
                ui.policyUrl.focus();
            }).catch(function (error) {
                showMessage(error.message, 'error');
            }).then(function () {
                ui.openFormButton.disabled = false;
            });
        });

        ui.cancelButton.addEventListener('click', function () {
            ui.form.hidden = true;
        });

        // Enter inside the form fields must not save the whole settings page.
        ui.form.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        ui.startButton.addEventListener('click', function () {
            showMessage('');
            ui.startButton.disabled = true;

            post(config.startUrl, {
                policy_url: ui.policyUrl.value.trim(),
                language: ui.language.value
            }).then(function () {
                setScanning(true);
                pollScanStatus();
            }).catch(function (error) {
                showMessage(error.message, 'error');
            }).then(function () {
                ui.startButton.disabled = false;
            });
        });

        ui.updateButton.addEventListener('click', function () {
            showMessage('');
            ui.updateButton.disabled = true;

            post(config.updateUrl).then(function (result) {
                showMessage(result.message, 'success');
            }).catch(function (error) {
                showMessage(error.message, 'error');
            }).then(function () {
                ui.updateButton.disabled = false;
            });
        });

        // The page was opened while a scan is still running (for example after a reload).
        if (config.scanning) {
            setScanning(true);
            pollScanStatus();
        }
    };
});
