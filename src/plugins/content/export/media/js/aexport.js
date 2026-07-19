/**
 * @copyright  Copyright (C) 2021 Alikon. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, document) => {

  document.addEventListener('DOMContentLoaded', () => {

    const toolbar = document.getElementById('toolbar-upload');
    if (!toolbar) return;

    // Keep this in sync with Export::MAX_BULK_IDS on the server, it's
    // only used here to fail fast client-side before hitting the AJAX call.
    const MAX_BULK_IDS = 200;

    toolbar.addEventListener('click', fetchData);

    /**
     * Small sprintf-like helper so user-facing messages can stay in the
     * language files instead of being hardcoded in JS.
     */
    function t(key, ...args) {
      const str = Joomla.Text._(key) || key;
      let i = 0;
      return str.replace(/%\d*\$?[sd]/g, () => args[i++]);
    }

    async function fetchData(e) {
      if (e) e.preventDefault();

      const options = window.Joomla.getOptions('a-export');
      const validation = hasValidConfig(options);

      if (!validation.ok) {
        showMessage(validation.message, 'error');
        return;
      }

      const oldLoader = document.getElementById('loader');
      if (oldLoader) oldLoader.remove();

      toolbar.insertAdjacentHTML('afterbegin', '<span id=\'loader\' class=\'spinner-grow spinner-grow-sm\' role=\'status\' aria-hidden=\'true\'></span>');

      // Wrapped in try/finally so the loader is always cleared, even if
      // checkArticle/postArticle/patchArticle throw (single-article path).
      try {
        if (await checkCategory(options)) {
          if (options.view === 'articles') {
            await processBulkExport(options);
          } else {
            await checkArticle(options, options.article, 1, 1);
          }
        }
      } catch (error) {
        console.error('[export] fetchData failed:', error);
      } finally {
        const loader = document.getElementById('loader');
        if (loader) loader.style.display = 'none';
      }
    }

    // Handles bulk export from the articles list view.
    async function processBulkExport(options) {
      const checkboxes = document.querySelectorAll('input[name="cid[]"]:checked');

      if (checkboxes.length === 0) {
        showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_BULK_NO_SELECTION'), 'error');
        return;
      }

      const maxIds = options.maxBulk || MAX_BULK_IDS;
      if (checkboxes.length > maxIds) {
        showMessage(t('PLG_CONTENT_EXPORT_BULK_TOO_MANY_SELECTED', maxIds), 'error');
        return;
      }

      const ids = Array.from(checkboxes).map(cb => cb.value);
      showMessage(t('PLG_CONTENT_EXPORT_BULK_SELECTED', ids.length), 'info');

      // Use Joomla's documented CSRF token API rather than guessing which
      // hidden field on the page happens to be the token.
      const csrfToken = Joomla.getOptions('csrf.token', '');

      try {
        const postParams = new URLSearchParams();
        if (csrfToken) {
          postParams.append(csrfToken, '1');
        }
        ids.forEach(id => {
          postParams.append('ids[]', id);
        });

        const localResp = await fetch('index.php?option=com_ajax&plugin=export&group=content&format=json', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: postParams
        });

        if (!localResp.ok) {
          showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_BULK_LOCAL_HTTP_ERROR'), 'error');
          return;
        }

        const localData = await localResp.json();

        if (!localData || !localData.success) {
          showMessage(localData && localData.message ? localData.message : Joomla.Text._('PLG_CONTENT_EXPORT_BULK_LOCAL_HTTP_ERROR'), 'error');
          return;
        }

        // com_ajax wraps the return value of the (single) event listener as
        // data[0]. Handle a direct array too, in case that ever changes.
        let articlesArray = [];
        if (Array.isArray(localData.data)) {
          articlesArray = Array.isArray(localData.data[0]) ? localData.data[0] : localData.data;
        }

        if (!articlesArray || articlesArray.length === 0) {
          showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_BULK_NO_ARTICLES'), 'error');
          return;
        }

        const totalArticles = articlesArray.length;

        // Sequential on purpose: avoids hammering the remote API with
        // concurrent requests and keeps progress messages readable.
        for (let i = 0; i < totalArticles; i++) {
          const currentCount = i + 1;
          const articlePayload = articlesArray[i];

          if (!articlePayload || !articlePayload.title) {
            console.warn(Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_MISSING_TITLE'));
            continue;
          }

          if (options.catid) {
            articlePayload.catid = parseInt(options.catid, 10);
          }

          try {
            await checkArticle(options, articlePayload, currentCount, totalArticles);
          } catch (singleErr) {
            console.error(`[export] article ${currentCount}/${totalArticles} failed:`, singleErr);
          }
        }

        showMessage(t('PLG_CONTENT_EXPORT_BULK_COMPLETE', totalArticles), 'success');

      } catch (err) {
        console.error('[export] bulk export failed:', err);
        showMessage(t('PLG_CONTENT_EXPORT_BULK_FATAL_ERROR', err.message), 'error');
      }
    }

    async function checkCategory(options) {
      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      const url = options.get + '/categories/' + options.catid;

      try {
        const response = await fetch(url, { method: 'GET', headers: myHeaders, redirect: 'follow' });

        if (response.ok) {
          showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_CATEGORY_CHECK'), 'success');
          return true;
        }
        showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_CATEGORY_CHECK_ERROR') + ': ' + response.status, 'error');
        return false;
      } catch (error) {
        console.error('[export] category check failed:', error);
        showMessage(t('PLG_CONTENT_EXPORT_CATEGORY_NETWORK_ERROR', error.message), 'error');
        return false;
      }
    }

    async function checkArticle(options, articleObj, current = 1, total = 1) {
      const targetArticle = articleObj || options.article;
      const titleToSearch = targetArticle?.title || options.title;

      if (!titleToSearch) {
        console.error('[export] checkArticle called without a title.');
        return;
      }

      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      const url = options.get + '/articles?filter[search]=' + encodeURIComponent(titleToSearch);

      showMessage(t('PLG_CONTENT_EXPORT_ARTICLE_CHECKING', current, total, titleToSearch), 'info');

      try {
        const response = await fetch(url, { method: 'GET', headers: myHeaders, redirect: 'follow' });

        if (!response.ok) {
          showMessage(t('PLG_CONTENT_EXPORT_ARTICLE_CHECK_HTTP_ERROR', titleToSearch, response.status), 'error');
          return;
        }

        const resp = await response.json();

        if (!resp.data || resp.data.length === 0) {
          await postArticle(options, targetArticle, current, total);
        } else {
          const remoteId = resp.data[0].id;
          await patchArticle(options, remoteId, targetArticle, current, total);
        }
      } catch (error) {
        console.error(`[export] checkArticle (${current}/${total}) failed:`, error);
        showMessage(t('PLG_CONTENT_EXPORT_ARTICLE_VERIFY_NETWORK_ERROR', error.message), 'error');
        throw error;
      }
    }

    async function postArticle(options, targetArticle, current, total) {
      if (!targetArticle || !targetArticle.title || !targetArticle.catid) {
        showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_SAVE_REQUIRED'), 'error');
        return false;
      }

      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      try {
        const response = await fetch(options.post, {
          method: 'POST',
          headers: myHeaders,
          body: JSON.stringify(targetArticle),
          redirect: 'follow'
        });

        if (response.ok) {
          showMessage(`${Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_CREATED')}: "${targetArticle.title}" (${current}/${total})`, 'success');
          return true;
        }
        showMessage(`${Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_NOT_CREATED')} (${response.status})`, 'error');
        return false;
      } catch (error) {
        console.error(`[export] postArticle (${current}/${total}) failed:`, error);
        showMessage(t('PLG_CONTENT_EXPORT_ARTICLE_CREATE_NETWORK_ERROR', error.message), 'error');
        throw error;
      }
    }

    async function patchArticle(options, articleid, targetArticle, current, total) {
      if (!targetArticle || !targetArticle.title) {
        showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_UPDATE_REQUIRED'), 'error');
        return false;
      }

      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      const url = options.post + '/' + articleid;

      try {
        const response = await fetch(url, {
          method: 'PATCH',
          headers: myHeaders,
          body: JSON.stringify(targetArticle),
          redirect: 'follow'
        });

        if (response.ok) {
          showMessage(`${Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_EXPORTED')}: "${targetArticle.title}" (${current}/${total})`, 'success');
          return true;
        }
        showMessage(t('PLG_CONTENT_EXPORT_ARTICLE_UPDATE_HTTP_ERROR', response.status), 'error');
        return false;
      } catch (error) {
        console.error(`[export] patchArticle (${current}/${total}) failed:`, error);
        showMessage(t('PLG_CONTENT_EXPORT_ARTICLE_UPDATE_NETWORK_ERROR', error.message), 'error');
        throw error;
      }
    }

    function hasValidConfig(options) {
      if (!options || typeof options !== 'object') {
        return { ok: false, message: Joomla.Text._('PLG_CONTENT_EXPORT_INVALID_CONFIG_OBJECT') };
      }
      const apiKey = String(options.apiKey ?? '').trim();
      const auth = String(options.auth ?? '').trim();
      const getUrl = String(options.get ?? '').trim();
      const postUrl = String(options.post ?? '').trim();

      if (!apiKey || !auth || !getUrl || !postUrl) {
        return { ok: false, message: Joomla.Text._('PLG_CONTENT_EXPORT_INVALID_CONFIG_REQUIRED') };
      }
      return { ok: true, apiKey, auth, getUrl, postUrl };
    }

    function showMessage(message, type = 'info') {
      const loader = document.getElementById('loader');
      if (loader) {
        loader.style.display = 'none';
      }

      const existingMsg = document.getElementById('msg');
      if (existingMsg) {
        existingMsg.remove();
      }

      const alertClass = type === 'error' ? 'alert-danger' : type === 'success' ? 'alert-success' : 'alert-info';
      const toolbarContainer = toolbar.closest('.subhead');
      const insertTarget = toolbarContainer || toolbar.parentElement;

      insertTarget.insertAdjacentHTML('afterend',
        `<div id="msg" class="alert ${alertClass}" role="alert" style="margin: 10px; border: 2px solid; border-radius: 4px;">${message}</div>`);

      const msgBox = document.getElementById('msg');
      if (msgBox) {
        setTimeout(() => {
          msgBox.remove();
        }, 5000);
      }
    }
  });
})(window.Joomla, document);
