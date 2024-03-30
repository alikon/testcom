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
      //console.log('options',options);

      document.getElementById('toolbar-upload')
        .insertAdjacentHTML('afterbegin', '<span id=\'loader\' class=\'spinner-grow spinner-grow-sm\' role=\'status\' aria-hidden=\'true\'></span>')

      if (await checkCategory(options)) {
          await checkArticle(options)
      }
      var loader = document.getElementById('loader');
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
          output = resp?.data?.attributes?.alias ?? 'No data';
          //console.log('the o', output);
          showMessage('Category check');
          return true
        }
        showMessage('Category check: ' + response?.status + ' - ' + response?.statusText);
        return false
      } catch (error) {
        showMessage(error);
        return false;
      }
    }

    async function postArticle(options) {
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
          output = resp?.data?.attributes?.alias ?? 'No data';
          //console.log('the o', output);
          showMessage('Article created')
          return true
        }
        showMessage('Article not created ' + response?.status + ' ' + response?.statusText)
        return false
      } catch (error) {
        showMessage(error);
        return false;
      }
    }

    async function patchArticle(options, articleid) {
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
          output = resp?.data?.attributes?.alias ?? 'No data';
          //console.log('the o', output);
          showMessage('Article exported');
          return true
        }
        showMessage(response?.status + ' ' + response?.statusText);
        return false
      } catch (error) {
        showMessage(error);
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
          showMessage('Article Check');
          //console.log('The checkArticle response');
          const resp = await response.json();
          //console.log('the json', resp);
          output = resp?.data[0]?.attributes?.title ?? 'No data';
          //console.log('the length', resp.data.length);
          if (resp.data.length === 0) {
             //console.log('length', resp.data.length)
             await postArticle(options)
             return
          }
          await patchArticle(options,resp?.data[0]?.id)
          return
        }
        showMessage(response?.status + ' ' + response?.statusText);
      } catch (error) {
        showMessage(error);
      }
    }

    function showMessage(message) {
      var loader = document.getElementById('loader');
      //loader.style.display = 'none';

      toolbar.insertAdjacentHTML('afterend', '<div id=\'msg\'>' + message + '</div>');
      const msgBox = document.getElementById('msg');
      setTimeout(() => {
        msgBox.remove();
        
      }, 3000);
    }
    //
  });
})(window.Joomla, document);
