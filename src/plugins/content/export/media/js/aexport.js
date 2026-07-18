/**
 * @copyright  Copyright (C) 2021 Alikon. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, document) => {

  document.addEventListener('DOMContentLoaded', () => {
   
    const toolbar = document.getElementById('toolbar-upload');
    if (!toolbar) return;
    
    toolbar.addEventListener('click', fetchData);

    async function fetchData(e) {
      if (e) e.preventDefault();
      console.log('%c[EXPORT] Avvio del processo di esportazione...', 'color: #00bcd4; font-weight: bold;');
      
      const options = window.Joomla.getOptions('a-export');
      const validation = hasValidConfig(options);

      if (!validation.ok) {
            console.error('[CONFIG] Configurazione non valida:', validation.message);
            showMessage(validation.message, 'error');
            return;
      }

      const oldLoader = document.getElementById('loader');
      if (oldLoader) oldLoader.remove();

      toolbar.insertAdjacentHTML('afterbegin', '<span id=\'loader\' class=\'spinner-grow spinner-grow-sm\' role=\'status\' aria-hidden=\'true\'></span>');

      console.log('[CATEGORY] Avvio controllo categoria remota ID:', options.catid);
      if (await checkCategory(options)) {
          console.log('[CATEGORY] Categoria valida. Controllo routing della vista...');
          
          if (options.view === 'articles') {
              console.log('[ROUTER] Vista LISTA ARTICOLI rilevata. Avvio processBulkExport.');
              await processBulkExport(options);
          } else {
              console.log('[ROUTER] Vista SINGOLO ARTICOLO rilevata. Invio singolo payload.');
              await checkArticle(options, options.article, 1, 1);
          }
      } else {
          console.warn('[CATEGORY] Controllo categoria fallito. Il processo si interrompe.');
      }
      
      const loader = document.getElementById('loader');
      if (loader) loader.style.display = 'none';
      console.log('%c[EXPORT] Processo globale terminato.', 'color: #00bcd4; font-weight: bold;');
    }

    // Gestione dell'esportazione massiva (Lista Articoli)
    async function processBulkExport(options) {
        const checkboxes = document.querySelectorAll('input[name="cid[]"]:checked');
        if (checkboxes.length === 0) {
            console.warn('[BULK] Nessun elemento selezionato dall\'utente.');
            showMessage('Seleziona almeno un articolo dalla lista.', 'error');
            return;
        }

        const ids = Array.from(checkboxes).map(cb => cb.value);
        console.log(`[BULK] ID selezionati dall'interfaccia: [${ids.join(', ')}]`);
        showMessage(`Trovati ${ids.length} articoli selezionati. Recupero testi dal server locale...`, 'info');

        // Estrazione del Token CSRF di Joomla
        const tokenElements = document.querySelectorAll('input[type="hidden"]');
        let csrfToken = '';
        for (const el of tokenElements) {
            if (el.name.length === 32) {
                csrfToken = el.name;
                break;
            }
        }
        if (!csrfToken) {
            csrfToken = Joomla.getOptions('csrf.token') || '';
        }

        try {
            console.log('[FETCH LOCAL] Invio richiesta POST a com_ajax...');
            
            const postParams = new URLSearchParams();
            postParams.append(csrfToken, '1');
            ids.forEach(id => {
                postParams.append('ids[]', id);
            });

            const localResp = await fetch('index.php?option=com_ajax&plugin=export&group=content&format=json', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: postParams
            });

            if (!localResp.ok) throw new Error('Errore nella risposta HTTP del server locale.');

            const localData = await localResp.json();
            console.log('[FETCH LOCAL] Dati JSON grezzi da com_ajax:', localData);

            let articlesArray = [];
            
            // FILTRO DI UNWRAPPING AGGIORNATO (Gestisce l'array annidato in data[0])
            if (localData && localData.success && localData.data) {
                if (Array.isArray(localData.data)) {
                    if (localData.data.length === 1 && Array.isArray(localData.data[0])) {
                        // Scenario riscontrato nel log: data contiene [ Array(2) ]
                        articlesArray = localData.data[0];
                    } else if (localData.data.length > 0 && typeof localData.data[0] === 'object') {
                        const firstKey = Object.keys(localData.data[0])[0];
                        if (Array.isArray(localData.data[0][firstKey])) {
                            articlesArray = localData.data[0][firstKey];
                        } else {
                            articlesArray = localData.data;
                        }
                    } else {
                        articlesArray = localData.data;
                    }
                } else if (typeof localData.data === 'object') {
                    const rootKeys = Object.keys(localData.data);
                    if (rootKeys.length > 0 && Array.isArray(localData.data[rootKeys[0]])) {
                        articlesArray = localData.data[rootKeys[0]];
                    } else {
                        articlesArray = Object.values(localData.data);
                    }
                }
            }

            console.log('[FETCH LOCAL] Coda finale articoli normalizzata per il ciclo:', articlesArray);

            if (!articlesArray || articlesArray.length === 0) {
                showMessage('Errore: Il server locale non ha restituito articoli validi.', 'error');
                return;
            }

            const totalArticles = articlesArray.length;
            console.log(`[LOOP START] Inizio invio remoto della coda di ${totalArticles} articoli.`);

            // Ciclo sequenziale per ciascun articolo reale estratto
            for (let i = 0; i < totalArticles; i++) {
                const currentCount = i + 1;
                const articlePayload = articlesArray[i];
                
                if (articlePayload && options.catid) {
                    articlePayload.catid = parseInt(options.catid, 10);
                }

                console.log(`%c[LOOP] >>> Elaborazione articolo ${currentCount} di ${totalArticles}: "${articlePayload.title}"`, 'color: #ff9800; font-weight: bold;');
                
                try {
                    await checkArticle(options, articlePayload, currentCount, totalArticles);
                    console.log(`%c[LOOP] <<< Concluso articolo ${currentCount} di ${totalArticles}`, 'color: #4caf50;');
                } catch (singleErr) {
                    console.error(`[LOOP ERROR] Fallito articolo ${currentCount}: ${articlePayload.title}`, singleErr);
                }
            }
            
            showMessage(`Esportazione di massa conclusa! Elaborati ${totalArticles} articoli.`, 'success');

        } catch (err) {
            console.error('[BULK FATAL ERROR] Errore critico nel recupero locale:', err);
            showMessage(`Errore bloccante durante l'esportazione: ${err.message}`, 'error');
        }
    }

    async function checkCategory(options) {
      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      const url = options.get + '/categories/' + options.catid;
      console.log(`[FETCH REMOTE] GET Categoria -> ${url}`);

      try {
        const response = await fetch(url, { method: 'GET', headers: myHeaders, redirect: 'follow' });
        
        if (response.ok) {
          showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_CATEGORY_CHECK'), 'success');
          return true;
        }
        showMessage(Joomla.Text._('PLG_CONTENT_EXPORT_CATEGORY_CHECK_ERROR') + ': ' + response.status, 'error');
        return false;
      } catch (error) {
        console.error('[FETCH REMOTE ERR] GET Categoria fallito:', error);
        showMessage('Errore di rete controllo categoria: ' + error.message, 'error');
        return false;
      }
    }

    async function checkArticle(options, articleObj, current = 1, total = 1) {
      const targetArticle = articleObj || options.article;
      const titleToSearch = targetArticle?.title || options.title;

      if (!titleToSearch) {
          console.error('[CHECK ARTICLE] Titolo mancante o non definito nell\'oggetto passato.');
          return;
      }

      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      const url = options.get + '/articles?filter[search]=' + encodeURIComponent(titleToSearch);
      console.log(`[FETCH REMOTE] (${current}/${total}) Titolo da cercare sul server remoto: "${titleToSearch}" -> ${url}`);
      
      showMessage(`[Esporta articolo ${current} di ${total}]: Controllo esistenza di "${titleToSearch}"`, 'info');

      try {
        const response = await fetch(url, { method: 'GET', headers: myHeaders, redirect: 'follow' });
        
        if (!response.ok) {
          showMessage(`Errore controllo esistenza per "${titleToSearch}" (Status ${response.status})`, 'error');
          return;
        }

        const resp = await response.json();
        console.log(`[FETCH REMOTE DATA] (${current}/${total}) Risposta ricerca esistenza:`, resp);

        if (!resp.data || resp.data.length === 0) {
           console.log(`[DECISION] (${current}/${total}) L'articolo non esiste sul server remoto. Avvio creazione (POST).`);
           await postArticle(options, targetArticle, current, total);
        } else {
           const remoteId = resp.data[0].id;
           console.log(`[DECISION] (${current}/${total}) L'articolo esiste in remoto con ID: ${remoteId}. Avvio aggiornamento (PATCH).`);
           await patchArticle(options, remoteId, targetArticle, current, total);
        }
      } catch (error) {
        console.error(`[FETCH REMOTE ERR] (${current}/${total}) Eccezione in checkArticle:`, error);
        showMessage(`Errore di rete durante la verifica dell'articolo: ${error.message}`, 'error');
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

      console.log(`[FETCH REMOTE] (${current}/${total}) POST Nuovo articolo -> ${options.post}`);

      try {
        const response = await fetch(options.post, {
          method: 'POST',
          headers: myHeaders,
          body: JSON.stringify(targetArticle),
          redirect: 'follow'
        });
        
        if (response.ok) {
          showMessage(`[Articolo ${current}/${total}] ${Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_CREATED')}: "${targetArticle.title}"`, 'success');
          return true;
        }
        showMessage(`${Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_NOT_CREATED')} (${response.status})`, 'error');
        return false;
      } catch (error) {
        console.error(`[FETCH REMOTE ERR] (${current}/${total}) POST Creazione fallita:`, error);
        showMessage('Errore di rete durante la creazione: ' + error.message, 'error');
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
      console.log(`[FETCH REMOTE] (${current}/${total}) PATCH Aggiornamento ID ${articleid} -> ${url}`);

      try {
        const response = await fetch(url, {
          method: 'PATCH',
          headers: myHeaders,
          body: JSON.stringify(targetArticle),
          redirect: 'follow'
        });
        
        if (response.ok) {
          showMessage(`[Articolo ${current}/${total}] ${Joomla.Text._('PLG_CONTENT_EXPORT_ARTICLE_EXPORTED')}: "${targetArticle.title}"`, 'success');
          return true;
        }
        showMessage(`Errore nell'aggiornamento dell'articolo remoto (Status ${response.status})`, 'error');
        return false;
      } catch (error) {
        console.error(`[FETCH REMOTE ERR] (${current}/${total}) PATCH Aggiornamento fallito:`, error);
        showMessage('Errore di rete durante l\'aggiornamento: ' + error.message, 'error');
        throw error;
      }
    }

    function hasValidConfig(options) {
        if (!options || typeof options !== 'object') {
            return { ok: false, message: Joomla.Text._('PLG_CONTENT_EXPORT_INVALID_CONFIG_OBJECT') };
        }
        const apiKey = String(options.apiKey ?? '').trim();
        const auth   = String(options.auth ?? '').trim();
        const getUrl = String(options.get ?? '').trim();
        const postUrl = String(options.post ?? '').trim();

        if (!apiKey || !auth || !getUrl || !postUrl) {
            return { ok: false, message: 'Invalid configuration: API key, auth, GET and POST URLs are required.' };
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
