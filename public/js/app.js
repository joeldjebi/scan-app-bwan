/**
 * Back-office Pass Parking : navigation et formulaires en AJAX, chargement réutilisable,
 * notifications, téléchargements avec progression et exports en arrière-plan.
 *
 * Conventions (amélioration progressive : sans JavaScript, tout fonctionne normalement) :
 * - tout formulaire et tout lien interne de #app-page passe en AJAX, sauf `data-ajax="false"` ;
 * - `a[data-download]` : téléchargement avec barre de progression ;
 * - `[data-background-export]` (formulaire) : export préparé en arrière-plan, puis téléchargé ;
 * - API globale : window.App.{loader, progress, toast, visit, request}.
 */
(() => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

    /* ------------------------------------------------------------------ Chargement */

    const loader = {
        pending: 0,
        timer: null,
        el: () => document.getElementById('app-loader'),
        start() {
            this.pending++;
            const bar = this.el();
            if (!bar || this.pending > 1) return;
            clearTimeout(this.timer);
            bar.style.transition = 'none';
            bar.style.width = '0%';
            bar.style.opacity = '1';
            requestAnimationFrame(() => {
                bar.style.transition = 'width 8s cubic-bezier(.1,.7,.3,1)';
                bar.style.width = '85%';
            });
        },
        set(ratio) {
            const bar = this.el();
            if (!bar) return;
            bar.style.transition = 'width .2s ease-out';
            bar.style.width = `${Math.max(5, Math.min(100, ratio * 100))}%`;
        },
        done() {
            this.pending = Math.max(0, this.pending - 1);
            const bar = this.el();
            if (!bar || this.pending > 0) return;
            bar.style.transition = 'width .25s ease-out, opacity .4s .25s';
            bar.style.width = '100%';
            this.timer = setTimeout(() => (bar.style.opacity = '0'), 300);
        },
        /** Bouton en cours : désactivé + roue. */
        button(button, busy) {
            if (!button) return;
            if (busy) {
                button.dataset.loading = '1';
                button.disabled = true;
                button.insertAdjacentHTML('afterbegin', '<span class="app-spinner" aria-hidden="true"></span>');
            } else {
                delete button.dataset.loading;
                button.disabled = false;
                button.querySelector('.app-spinner')?.remove();
            }
        },
    };

    /** Panneau de progression pour les opérations longues (envoi de fichiers, exports, téléchargements). */
    const progress = {
        el: () => document.getElementById('app-progress'),
        open(title, detail = '') {
            const panel = this.el();
            if (!panel) return;
            panel.querySelector('[data-title]').textContent = title;
            this.update(null, detail);
            panel.hidden = false;
            requestAnimationFrame(() => panel.classList.add('is-open'));
        },
        /** ratio entre 0 et 1, ou null pour une progression indéterminée. */
        update(ratio, detail) {
            const panel = this.el();
            if (!panel) return;
            const bar = panel.querySelector('[data-bar]');
            bar.classList.toggle('is-indeterminate', ratio === null);
            bar.style.width = ratio === null ? '35%' : `${Math.round(ratio * 100)}%`;
            panel.querySelector('[data-percent]').textContent = ratio === null ? '' : `${Math.round(ratio * 100)} %`;
            if (detail !== undefined) panel.querySelector('[data-detail]').textContent = detail;
        },
        close() {
            const panel = this.el();
            if (!panel) return;
            panel.classList.remove('is-open');
            setTimeout(() => (panel.hidden = true), 200);
        },
    };

    /* ------------------------------------------------------------------ Notifications */

    function toast(message, type = 'success') {
        const container = document.getElementById('app-toasts');
        if (!container || !message) return;
        const styles = {
            success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
            error: 'border-red-200 bg-red-50 text-red-900',
            info: 'border-slate-200 bg-white text-slate-900',
        };
        const item = document.createElement('div');
        item.setAttribute('role', type === 'error' ? 'alert' : 'status');
        item.className = `app-toast pointer-events-none rounded-lg border px-4 py-3 text-sm shadow-lg ${styles[type] ?? styles.info}`;
        item.textContent = message;
        container.appendChild(item);
        requestAnimationFrame(() => item.classList.add('is-visible'));
        setTimeout(() => {
            item.classList.remove('is-visible');
            setTimeout(() => item.remove(), 300);
        }, type === 'error' ? 7000 : 4000);
    }

    /* ------------------------------------------------------------------ Requêtes */

    /**
     * XMLHttpRequest (et non fetch) pour suivre la progression d'envoi et de téléchargement.
     * @returns {Promise<{status:number, data:any, xhr:XMLHttpRequest}>}
     */
    function request({ method = 'GET', url, body = null, accept = 'application/json', responseType = 'text', onUpload, onDownload }) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url);
            xhr.responseType = responseType;
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', accept);
            if (method !== 'GET' && csrf()) xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
            if (onUpload) xhr.upload.onprogress = (e) => e.lengthComputable && onUpload(e.loaded / e.total);
            if (onDownload) xhr.onprogress = (e) => onDownload(e.lengthComputable ? e.loaded / e.total : null, e.loaded);
            xhr.onload = () => {
                let data = xhr.response;
                if (responseType === 'text' && (xhr.getResponseHeader('Content-Type') || '').includes('application/json')) {
                    try { data = JSON.parse(xhr.response); } catch { /* réponse non JSON */ }
                }
                resolve({ status: xhr.status, data, xhr });
            };
            xhr.onerror = () => reject(new Error('Connexion impossible. Vérifiez votre réseau.'));
            xhr.send(body);
        });
    }

    function errorMessage(status, data) {
        if (data && typeof data === 'object' && data.message) return data.message;
        return {
            403: 'Action non autorisée.',
            404: 'Élément introuvable.',
            419: 'Votre session a expiré : la page va être rechargée.',
            429: 'Trop de tentatives, réessayez dans un instant.',
        }[status] ?? 'Une erreur est survenue. Réessayez.';
    }

    /* ------------------------------------------------------------------ Navigation */

    /** Exécute les scripts d'un contenu injecté (dans l'ordre, en attendant les scripts externes). */
    async function runScripts(root) {
        for (const old of root.querySelectorAll('script')) {
            const script = document.createElement('script');
            [...old.attributes].forEach((attr) => script.setAttribute(attr.name, attr.value));
            script.textContent = old.textContent;
            const loaded = script.src ? new Promise((resolve) => { script.onload = script.onerror = resolve; }) : null;
            old.replaceWith(script);
            if (loaded) await loaded;
        }
    }

    /** Charge une page du back-office et remplace son contenu sans recharger le navigateur. */
    async function visit(url, { push = true, scroll = true } = {}) {
        loader.start();
        try {
            const { status, data, xhr } = await request({ url, accept: 'text/html' });
            const finalUrl = xhr.responseURL || url;
            const doc = new DOMParser().parseFromString(data, 'text/html');
            const page = doc.getElementById('app-page');

            // Hors back-office (connexion, erreur serveur…) : navigation classique.
            if (!page || status >= 500) {
                window.location.href = finalUrl;
                return;
            }

            document.getElementById('app-page').replaceWith(page);
            document.title = doc.title;
            if (push && finalUrl !== window.location.href) history.pushState({ app: true }, '', finalUrl);
            if (scroll) window.scrollTo({ top: 0 });
            await runScripts(page);
        } catch (error) {
            window.location.href = url;
        } finally {
            loader.done();
        }
    }

    window.addEventListener('popstate', () => visit(window.location.href, { push: false, scroll: false }));

    const isAjaxLink = (link, event) =>
        link.closest('#app-page') &&
        !event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey &&
        link.origin === window.location.origin &&
        !link.target && !link.hasAttribute('download') && link.dataset.ajax !== 'false' &&
        !/^\/(docs|phpmyadmin|storage|p)\//.test(link.pathname) &&
        !(link.hash && link.pathname === window.location.pathname);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;

        if (link.dataset.download !== undefined) {
            event.preventDefault();
            download(link.href, link.dataset.download || 'Préparation du fichier…');
            return;
        }

        if (isAjaxLink(link, event)) {
            event.preventDefault();
            visit(link.href);
        }
    });

    /* ------------------------------------------------------------------ Formulaires */

    function clearErrors(form) {
        form.querySelectorAll('.app-field-error').forEach((el) => el.remove());
        form.querySelectorAll('.app-invalid').forEach((el) => el.classList.remove('app-invalid'));
        document.querySelectorAll(`[form="${form.id}"].app-invalid`).forEach((el) => el.classList.remove('app-invalid'));
    }

    /** Affiche chaque erreur de validation sous son champ (« user_ids.0 » → « user_ids[] »). */
    function showErrors(form, errors) {
        let first = null;
        Object.entries(errors).forEach(([key, messages]) => {
            const parts = key.split('.');
            const bracketed = parts[0] + parts.slice(1).map((p) => (/^\d+$/.test(p) ? '[]' : `[${p}]`)).join('');
            const field = form.querySelector(`[name="${bracketed}"]`) || form.querySelector(`[name="${parts[0]}[]"]`) || form.querySelector(`[name="${parts[0]}"]`);
            if (!field) return;
            const anchor = field.type === 'hidden' ? field.parentElement : (field.closest('.relative') ?? field);
            field.classList.add('app-invalid');
            if (field.type !== 'hidden') first ??= field;
            if (anchor.nextElementSibling?.classList.contains('app-field-error')) return;
            anchor.insertAdjacentHTML('afterend', `<p class="app-field-error mt-1 text-sm text-red-600"></p>`);
            anchor.nextElementSibling.textContent = messages[0];
        });
        first?.focus({ preventScroll: true });
        first?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (event.defaultPrevented || !form.closest('#app-page') || form.dataset.ajax === 'false') return;
        event.preventDefault();

        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const action = form.getAttribute('action') || window.location.href;

        // Filtres et recherches : simple navigation.
        if (method === 'GET') {
            const query = new URLSearchParams(new FormData(form));
            [...query.keys()].forEach((key) => query.get(key) === '' && query.delete(key));
            const url = new URL(action, window.location.href);
            url.search = query.toString();
            visit(url.toString());
            return;
        }

        if (form.dataset.backgroundExport !== undefined) {
            backgroundExport(form, event.submitter);
            return;
        }

        const button = event.submitter ?? form.querySelector('[type=submit], button:not([type])');
        const body = new FormData(form);
        if (event.submitter?.name) body.append(event.submitter.name, event.submitter.value);
        const hasFiles = [...form.querySelectorAll('input[type=file]')].some((input) => input.files.length);

        clearErrors(form);
        loader.start();
        loader.button(button, true);
        if (hasFiles) progress.open('Envoi en cours…', 'Envoi des fichiers');

        try {
            const { status, data } = await request({
                method: 'POST',
                url: action,
                body,
                onUpload: hasFiles ? (ratio) => progress.update(ratio, ratio < 1 ? 'Envoi des fichiers' : 'Traitement…') : null,
            });

            if (status === 419) {
                toast(errorMessage(status), 'error');
                setTimeout(() => window.location.reload(), 1500);
            } else if (status === 422 && data?.errors) {
                showErrors(form, data.errors);
                toast(data.message, 'error');
            } else if (status >= 400 || data?.ok === false) {
                toast(errorMessage(status, data), 'error');
            } else {
                toast(data?.message || 'Enregistré.');
                await visit(data?.redirect || window.location.href, { scroll: (data?.redirect || '') !== window.location.href });
            }
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            if (hasFiles) progress.close();
            loader.button(button, false);
            loader.done();
        }
    });

    /* ------------------------------------------------------------------ Téléchargements */

    function filenameFrom(xhr, fallback) {
        const header = xhr.getResponseHeader('Content-Disposition') || '';
        const utf8 = header.match(/filename\*=UTF-8''([^;]+)/i);
        if (utf8) return decodeURIComponent(utf8[1]);
        return header.match(/filename="?([^";]+)"?/i)?.[1] ?? fallback;
    }

    function formatSize(bytes) {
        return bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} Mo` : `${Math.max(1, Math.round(bytes / 1024))} Ko`;
    }

    /** Télécharge un fichier en affichant la progression, puis l'enregistre. */
    async function download(url, title) {
        progress.open(title, 'Préparation par le serveur…');
        loader.start();
        try {
            const { status, data, xhr } = await request({
                url,
                accept: '*/*',
                responseType: 'blob',
                onDownload: (ratio, loaded) => progress.update(ratio, `Téléchargement · ${formatSize(loaded)}`),
            });
            if (status >= 400) {
                toast(errorMessage(status), 'error');
                return;
            }
            const link = document.createElement('a');
            link.href = URL.createObjectURL(data);
            link.download = filenameFrom(xhr, 'export');
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(link.href), 10000);
            toast('Téléchargement terminé.');
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            progress.close();
            loader.done();
        }
    }

    /** Lance un export en arrière-plan, suit sa progression, puis le télécharge. */
    async function backgroundExport(form, submitter) {
        loader.button(submitter, true);
        progress.open(form.dataset.backgroundExport || 'Export en cours…', 'Mise en file d\'attente…');
        try {
            const body = new FormData(form);
            if (submitter?.name) body.append(submitter.name, submitter.value);
            const { status, data } = await request({ method: 'POST', url: form.action, body });
            if (status >= 400) throw new Error(errorMessage(status, data));

            let state = data;
            while (!['done', 'failed'].includes(state.status)) {
                progress.update(state.total ? state.processed / state.total : null,
                    state.status === 'pending' ? 'En attente de traitement…' : `${state.processed} / ${state.total} QR codes générés`);
                await new Promise((resolve) => setTimeout(resolve, 1000));
                state = (await request({ url: state.status_url })).data;
            }
            if (state.status === 'failed') throw new Error(state.error || 'L\'export a échoué.');

            progress.update(1, 'Prêt : téléchargement…');
            window.location.href = state.download_url;
            toast('Export prêt : le téléchargement commence.');
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            setTimeout(() => progress.close(), 600);
            loader.button(submitter, false);
        }
    }

    window.App = { loader, progress, toast, visit, request, download };
})();
