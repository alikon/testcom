/**
 * @copyright  Copyright (C) 2021 Alikon. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, document) => {

  document.addEventListener('DOMContentLoaded', () => {
    //
    //var element = document.getElementById('toolbar-flash');
    const toolbar = document.getElementById('toolbar-flash');
    toolbar.addEventListener('click', fetchData);

    async function fetchData() {
      const options = window.Joomla.getOptions('ai-metadesc');
      let apiKey = options.apiKey;
      const text = document.getElementById('jform_articletext').value
      const strWithoutHTmlTags = text.replace(/(<([^>]+)>)/gi, '');
      let response;
      let myHeaders = new Headers();
      myHeaders.append('Content-Type', 'application/json');
      myHeaders.append('Authorization', 'Bearer ' + apiKey);

      document.getElementById('toolbar-flash')
        .insertAdjacentHTML('afterend', '<span id=\'loader\' class=\'spinner-border spinner-border-sm\' role=\'status\' aria-hidden=\'true\'></span>')

      let raw = JSON.stringify({
        'prompt': strWithoutHTmlTags,
        'model': 'text-davinci-003',
        'max_tokens': 160,
        'temperature': 0.5
      });

      let requestOptions = {
        method: 'POST',
        headers: myHeaders,
        body: raw,
        redirect: 'follow'
      };
      try {
        response = await fetch('https://api.openai.com/v1/completions', requestOptions);
        //response = await fetch('https://list.ly/api/v4/meta?url=http%3A%2F%2Fabc.com');
        //response = await fetch('https://httpbin.org/status/429');
        console.log('HTTP Response Code: ', response?.status)
        console.log('HTTP Response Text: ', response?.statusText)
        if (response?.ok) {
          console.log('The response');
          const resp = await response.json();
          console.log('the json', resp);
          output = response?.choices[0]?.text ?? 'No data';

          //output = resp?.metadata?.descriptione ?? 'No data';
          document.getElementById('jform_metadesc').value = output.trim();
        }
        showMessage(response?.status + ' ' + response?.statusText);
      } catch (error) {
        showMessage(error);

        return;
      }
    }

    function showMessage(message) {
      var loader = document.getElementById('loader');
      loader.style.display = 'none';

      toolbar.insertAdjacentHTML('afterend', '<div id=\'msg\'>' + message + '</div>');
      const msgBox = document.getElementById('msg');
      setTimeout(() => {
        msgBox.remove();
      }, 5000);
    }
    //
  });
})(window.Joomla, document);
