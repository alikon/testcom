/**
 * @copyright  Copyright (C) 2021 Alikon. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, document) => {

  document.addEventListener('DOMContentLoaded', () => {
   
    const toolbar = document.getElementById('toolbar-upload');
    toolbar.addEventListener('click', fetchData);

    async function fetchData() {
      const options = window.Joomla.getOptions('a-export');
      console.log('options',options);
      const validation = hasValidConfig(options);

      if (!validation.ok) {
            showMessage(validation.message, 'error');
            return;
      }

      document.getElementById('toolbar-upload')
        .insertAdjacentHTML('afterbegin', '<span id=\'loader\' class=\'spinner-grow spinner-grow-sm\' role=\'status\' aria-hidden=\'true\'></span>')

      if (await checkCategory(options)) {
          await checkArticle(options)
      }
      const loader = document.getElementById('loader');
      loader.style.display = 'none';
    }

    async function checkCategory(options) {
      let response;
      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      let requestOptions = {
        method: 'GET',
        headers: myHeaders,
        redirect: 'follow'
      };

      try {
        response = await fetch(options.get + '/categories/' + options.catid, requestOptions);
        console.log('HTTP Response Code: ', response?.status)
        console.log('HTTP Response Text: ', response?.statusText)
        //showMessage(response?.status + ' ' + response?.statusText);
        if (response?.ok) {
          //console.log('The checkCategory response');
          const resp = await response.json();
          //console.log('the json', resp);
          const output = resp?.data?.attributes?.alias ?? 'No data';
          //console.log('the o', output);
          showMessage('Category check', 'success');
          return true;
        }
        showMessage('Category check: ' + response?.status + ' - ' + response?.statusText, 'error');
        return false;
      } catch (error) {
        let errorMsg = 'Network error occurred';
        if (error.name === 'TypeError' || error.message.includes('CORS')) {
          errorMsg = 'CORS error: The GET method is not allowed. Add "GET" to Access-Control-Allow-Methods header on the API server.';
        } else if (error.message) {
          errorMsg = error.message;
        }
        showMessage(errorMsg, 'error');
        return false;
      }
    }

    async function postArticle(options) {
      // Validate article data before posting
      if (!options.article || !options.article.title || !options.article.catid) {
        showMessage('Cannot create article: Article must be saved first with a title and category.', 'error');
        return false;
      }

      let response;
      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      //console.log('pay', options.article)
      let raw = JSON.stringify(options.article);
      let requestOptions = {
        method: 'POST',
        headers: myHeaders,
        body: raw,
        redirect: 'follow'
      };

      try {
        response = await fetch(options.post, requestOptions);
        console.log('HTTP Response Code: ', response?.status)
        console.log('HTTP Response Text: ', response?.statusText)
        //showMessage(response?.status + ' ' + response?.statusText);
        if (response?.ok) {
          //console.log('The checkCategory response');
          const resp = await response.json();
          //console.log('the json', resp);
          const output = resp?.data?.attributes?.alias ?? 'No data';
          //console.log('the o', output);
          showMessage('Article created', 'success');
          return true;
        }
        showMessage('Article not created ' + response?.status + ' ' + response?.statusText, 'error');
        return false;
      } catch (error) {
        let errorMsg = 'Network error occurred';
        if (error.name === 'TypeError' || error.message.includes('CORS')) {
          errorMsg = 'CORS error: The POST method is not allowed. Add "POST" to Access-Control-Allow-Methods header on the API server.';
        } else if (error.message) {
          errorMsg = error.message;
        }
        showMessage(errorMsg, 'error');
        return false;
      }
    }

    async function patchArticle(options, articleid) {
      // Validate article data before patching
      if (!options.article || !options.article.title) {
        showMessage('Cannot update article: Article must be saved first with a title.', 'error');
        return false;
      }

      let response;
      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      //console.log('pay', options.article)
      let raw = JSON.stringify(options.article);
      let requestOptions = {
        method: 'PATCH',
        headers: myHeaders,
        body: raw,
        redirect: 'follow'
      };

      try {
        response = await fetch(options.post + '/' + articleid, requestOptions);
        console.log('HTTP Response Code: ', response?.status)
        console.log('HTTP Response Text: ', response?.statusText)
        //showMessage(response?.status + ' ' + response?.statusText);
        if (response?.ok) {
         // console.log('The checkCategory response');
          const resp = await response.json();
          //console.log('the json', resp);
          const output = resp?.data?.attributes?.alias ?? 'No data';
          //console.log('the o', output);
          showMessage('Article exported', 'success');
          return true;
        }
        showMessage(response?.status + ' ' + response?.statusText, 'error');
        return false;
      } catch (error) {
        let errorMsg = 'Network error occurred';
        if (error.name === 'TypeError' || error.message.includes('CORS')) {
          errorMsg = 'CORS error: The PATCH method is not allowed. Add "PATCH" to Access-Control-Allow-Methods header on the API server.';
        } else if (error.message) {
          errorMsg = error.message;
        }
        showMessage(errorMsg, 'error');
        return false;
      }
    }

    async function checkArticle(options) {
      let response;
      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append(options.auth, options.apiKey);

      let requestOptions = {
        method: 'GET',
        headers: myHeaders,
        redirect: 'follow'
      };
  
  
      try {
        response = await fetch(options.get + '/articles?filter[search]=' + options.title, requestOptions);
        console.log('HTTP Response Code: ', response?.status)
        console.log('HTTP Response Text: ', response?.statusText)
        //showMessage(response?.status + ' ' + response?.statusText);
        if (response?.ok) {
          showMessage('Article Check', 'success');
          //console.log('The checkArticle response');
          const resp = await response.json();
          //console.log('the json', resp);
          const output = resp?.data[0]?.attributes?.title ?? 'No data';
          //console.log('the length', resp.data.length);
          if (resp.data.length === 0) {
             //console.log('length', resp.data.length)
             await postArticle(options);
             return;
          }
          await patchArticle(options,resp?.data[0]?.id);
          return;
        }
        showMessage(response?.status + ' ' + response?.statusText, 'error');
      } catch (error) {
        let errorMsg = 'Network error occurred';
        if (error.name === 'TypeError' || error.message.includes('CORS')) {
          errorMsg = 'CORS error: The GET method is not allowed. Add "GET" to Access-Control-Allow-Methods header on the API server.';
        } else if (error.message) {
          errorMsg = error.message;
        }
        showMessage(errorMsg, 'error');
      }
    }
    function hasValidConfig(options) {
        if (!options || typeof options !== 'object') {
            return { ok: false, message: 'Invalid configuration: options object is required.' };
        }

        // Coerce to string safely
        const apiKey = String(options.apiKey ?? '').trim();
        const auth   = String(options.auth ?? '').trim();
        const getUrl = String(options.get ?? '').trim();
        const postUrl = String(options.post ?? '').trim();

        const hasApiKey = apiKey.length > 0;
        const hasAuth   = auth.length > 0;
        const hasGet    = getUrl.length > 0;
        const hasPost   = postUrl.length > 0;

        if (!hasApiKey || !hasAuth || !hasGet || !hasPost) {
            return {
                ok: false,
                message: 'Invalid configuration: API key, auth, GET and POST URLs are required.',
            };
        }

        // Detect a bare Bearer header with no token
        if (apiKey.toLowerCase().startsWith('bearer')) {
            const bearerParts = apiKey.split(/\s+/).filter(Boolean); // ["Bearer", "token"] or ["Bearer"]
            if (bearerParts.length < 2 || bearerParts[1].length === 0) {
                return {
                    ok: false,
                    message: 'Invalid configuration: Bearer token is missing a value.',
                };
            }
        }

        return {
            ok: true,
            // return normalized values so fetchData can use them safely
            apiKey,
            auth,
            getUrl,
            postUrl,
        };
    }

    function showMessage(message, type = 'info') {
      const loader = document.getElementById('loader');
      if (loader) {
        loader.style.display = 'none';
      }

      // Remove any existing message
      const existingMsg = document.getElementById('msg');
      if (existingMsg) {
        existingMsg.remove();
      }

      const alertClass = type === 'error' ? 'alert-danger' : type === 'success' ? 'alert-success' : 'alert-info';
      // Insert after toolbar parent container to avoid affecting button layout
      const toolbarContainer = toolbar.closest('.subhead');
      const insertTarget = toolbarContainer || toolbar.parentElement;
      insertTarget.insertAdjacentHTML('afterend', 
        `<div id="msg" class="alert ${alertClass}" role="alert" style="margin: 10px; border: 2px solid; border-radius: 4px;">${message}</div>`);
      const msgBox = document.getElementById('msg');
      setTimeout(() => {
        msgBox.remove();
      }, 5000);
    }
    //
  });
})(window.Joomla, document);
